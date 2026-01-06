<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Office;
use App\Models\Department;
use App\Models\Organisation;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use App\Services\OneSignalService;
use App\Services\SessionManagementService;
use App\Mail\VerifyUserEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication endpoints"
 * )
 */
class AuthController extends Controller
{
  
    /**
     * @OA\Post(
     *     path="/api/login-admin",
     *     tags={"Authentication"},
     *     summary="Admin/User Login",
     *     description="Authenticate user and return JWT token with user details",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password", "deviceType", "deviceId", "fcmToken"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="deviceType", type="string", example="ios"),
     *             @OA\Property(property="deviceId", type="string", example="device-uuid-123"),
     *             @OA\Property(property="fcmToken", type="string", example="fcm-token-123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGc..."),
     *             @OA\Property(property="user", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     )
     * )
     */
 	public function adminLogin(Request $request)
	{
    	$request->validate([
        	'email'      => 'required|email',
        	'password'   => 'required',
        	'deviceType' => 'required',
        	'deviceId'   => 'required',
        	'fcmToken'   => 'required',
    	]);

    $credentials = $request->only('email', 'password');

    if (! $token = JWTAuth::attempt($credentials)) {
        return response()->json(['status' => false, 'message' => 'Invalid credentials'], 401);
    }

    $user = auth()->user()->load(['organisation', 'office', 'department', 'roles']);

   
    // Check if email is verified - email_verified_at must be set
    if (!$user->email_verified_at) {
        return response()->json([
            'status'  => false,
            'message' => 'Please verify your email first. Check your email and click the verification link to activate your account.',
        ], 401);
    }
    
    // Check if user status is not active
    // Status is boolean: true = active, false = inactive
    if (!$user->status) {
        return response()->json([
            'status'  => false,
            'message' => 'Your account is not activated yet. Please check your email and verify your account.',
        ], 401);
    }

    // Get role name - check for api guard first (since we're using JWT)
    $roleName = $user->getRoleNames('api')->first();
    
    // If no role for api guard, check web guard
    if (!$roleName) {
        $roleName = $user->getRoleNames('web')->first();
    }
    
    // If still no role, try to assign basecamp role
    if (! $roleName) {
        try {
            // Ensure role exists for api guard
            Role::firstOrCreate(['name' => 'basecamp', 'guard_name' => 'api']);
            
            // Assign role for api guard
            $apiRole = Role::where('name', 'basecamp')->where('guard_name', 'api')->first();
            if ($apiRole) {
                $user->roles()->syncWithoutDetaching([$apiRole->id]);
                $user->refresh();
                $user->load('roles');
                $roleName = $user->getRoleNames('api')->first();
            }
        } catch (\Exception $e) {
            Log::error('Failed to assign role during login', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    if (! $roleName) {
        return response()->json(['status' => false, 'message' => 'User has no role assigned'], 401);
    }
  
    // ✅ Update device info FIRST, then invalidate previous sessions
    // This ensures device ID is available for session management
    $user->update([
        'fcmToken'   => $request->fcmToken,
        'deviceType' => $request->deviceType,
        'deviceId'   => $request->deviceId,
    ]);
    
    // Refresh user model to get updated deviceId
    $user->refresh();
  
    // ✅ Invalidate all previous sessions/tokens for other devices
    try {
        $sessionService = new SessionManagementService();
        $sessionService->invalidatePreviousSessions($user, $token, null);
    } catch (\Exception $e) {
        Log::warning('Failed to invalidate previous sessions on login', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);
    }

    // ✅ Set OneSignal tags on login (for automation)
    try {
        $oneSignal = new OneSignalService();
        $oneSignal->setUserTagsOnLogin($user);
        
        // ✅ Login user to OneSignal (associate device with external_user_id)
        // Equivalent to OneSignal.login("user_{userId}") on client-side
        if ($request->fcmToken) {
            $oneSignal->loginUser($request->fcmToken, $user->id);
        }
    } catch (\Throwable $e) {
        Log::warning('OneSignal tag update failed on login', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);
    }

  
    	$data = [
        	'name'              => $user->first_name,
        	'last_name'         => $user->last_name,
        	'email'             => $user->email,
        	'token'             => $token,
        	'role'              => $roleName,
        	'orgId'             => optional($user->organisation)->id,
        	'orgname'           => optional($user->organisation)->name,
        	'officeId'          => optional($user->office)->id,
        	'office'            => optional($user->office)->name,
        	'departmentId'      => optional($user->department)->id,
        	'department'        => optional($user->department)->name,
        	'organisation_logo' => optional($user->department)->image,
        	'profileImage'      => $user->image,
        	'deviceType'        => $user->deviceType,
        	'deviceId'          => $user->deviceId,
        	'fcmToken'          => $user->fcmToken,
        	'appPaymentVersion' => '1',
    	];

    	return response()->json([
        	'status'  => true,
        	'message' => 'Login successful',
        	'data'    => $data,
    	]);
	}

  /**
   * @OA\Post(
   *     path="/api/register",
   *     tags={"Authentication", "Basecamp Users"},
   *     summary="Register a new basecamp user",
   *     description="Register a new basecamp user account. Basecamp users are individual users who pay $10/month subscription. The user is automatically assigned the 'basecamp' role and receives a verification email. Returns JWT token for immediate authentication.",
   *     @OA\RequestBody(
   *         required=true,
   *         @OA\JsonContent(
   *             required={"first_name", "last_name", "email", "password", "password_confirmation"},
   *             @OA\Property(property="first_name", type="string", example="John", description="User's first name"),
   *             @OA\Property(property="last_name", type="string", example="Doe", description="User's last name"),
   *             @OA\Property(property="email", type="string", format="email", example="john@example.com", description="User's email address (must be unique)"),
   *             @OA\Property(property="password", type="string", format="password", example="Password123!@#", description="Password must contain uppercase, lowercase, number and special character, minimum 8 characters"),
   *             @OA\Property(property="password_confirmation", type="string", format="password", example="Password123!@#", description="Password confirmation (must match password)")
   *         )
   *     ),
   *     @OA\Response(
   *         response=201,
   *         description="Registration successful",
   *         @OA\JsonContent(
   *             @OA\Property(property="message", type="string", example="User registered successfully"),
   *             @OA\Property(property="user", type="object", description="Created user object"),
   *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGc...", description="JWT token for authentication")
   *         )
   *     ),
   *     @OA\Response(
   *         response=422,
   *         description="Validation error",
   *         @OA\JsonContent(
   *             @OA\Property(property="error", type="string", example="Validation failed"),
   *             @OA\Property(property="details", type="string", example="The email has already been taken.")
   *         )
   *     ),
   *     @OA\Response(
   *         response=500,
   *         description="Server error",
   *         @OA\JsonContent(
   *             @OA\Property(property="error", type="string", example="Registration failed"),
   *             @OA\Property(property="details", type="string", example="Error message")
   *         )
   *     )
   * )
   */
  public function register(Request $request)
  {
    	try {
           $request->validate([
                'first_name' => 'required|string|max:255',
                'last_name'  => 'required|string|max:255',
                'email'      => 'required|email|unique:users,email',

                'password'   => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/[A-Z]/',
                    'regex:/[a-z]/',
                    'regex:/[0-9]/',
                    'regex:/[@$!%*#?&^._-]/',
                ],

                'password_confirmation' => 'required|same:password',
            ], [
                'password.regex' => 'Password must contain uppercase, lowercase, number and special character.',
                'password_confirmation.same' => 'Confirm password does not match.',
            ]);

       	 $user = User::create([
        	    'first_name' => $request->first_name ?? '',
        	    'last_name'  => $request->last_name ?? '',
        	    'email'      => $request->email,
        	    'password'   => Hash::make($request->password),
    	        'status'     => false, // Set to false initially, will be activated after email verification
	        ]);
   
	        // Ensure role exists for both guards
	        Role::firstOrCreate(['name' => 'basecamp', 'guard_name' => 'web']);
	        Role::firstOrCreate(['name' => 'basecamp', 'guard_name' => 'api']);
	        
	        // Assign role
	        if (!$user->hasRole('basecamp')) {
	            $user->assignRole('basecamp');
	        }
	        
	        // Also assign for api guard since login uses JWT (api guard)
	        $apiRole = Role::where('name', 'basecamp')->where('guard_name', 'api')->first();
	        if ($apiRole && !$user->hasRole('basecamp', 'api')) {
	            $user->roles()->syncWithoutDetaching([$apiRole->id]);
	        }
	        
	        $user->refresh();

        	// Send verification email - DO NOT return token
			try {
                $oneSignal = new OneSignalService();

                $expires = Carbon::now()->addMinutes(1440);
                $verificationUrl = URL::temporarySignedRoute(
                    'user.verify', $expires, ['id' => $user->id]
                );

                $verifyBody = view('emails.verify-user-inline', [
                    'user' => $user,
                    'verificationUrl' => $verificationUrl,
                ])->render();

                $oneSignal->registerEmailUserFallback($user->email, $user->id, [
                    'subject' => 'Activate Your Tribe365 Account',
                    'body'    => $verifyBody,
                ]);

                // ✅ Set initial OneSignal tags on registration
                $oneSignal->setUserTagsOnLogin($user);

                Log::info('✅ OneSignal verification email sent (with inline Blade)', [
                    'email' => $user->email,
                ]);            

            } catch (\Throwable $e) {
                Log::error('❌ OneSignal registration/email failed for new user', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

        	// Return success message WITHOUT token - user must verify email first
        	return response()->json([
            	'status'  => true,
            	'message' => 'Registration successful! Please check your email to verify your account. After verification, you can login.',
            	'data'    => [
                    'email' => $user->email,
                    'email_verified' => false,
                ],
        	], 201);

    	} catch (\Exception $e) {
       	 return response()->json([
            	'error'   => 'Registration failed',
            	'details' => $e->getMessage(),
        	], 500);
    	}
	}
  
    /**
     * Logout user by invalidating JWT token.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            // Check for token manually
            $token = $request->bearerToken();

            if (! $token) {
                return response()->json(['error' => 'Token not provided'], 400);
            }

            // Get user before invalidating token
            $user = JWTAuth::setToken($token)->authenticate();
            
            // Clear OneSignal device token (fcmToken) from database
            if ($user && $user->fcmToken) {
                $fcmToken = $user->fcmToken;
                
                // ✅ Logout user from OneSignal (clear external_user_id)
                // Equivalent to await OneSignal.logout() on client-side
                try {
                    $oneSignal = new OneSignalService();
                    $oneSignal->logoutUser($fcmToken);
                } catch (\Exception $e) {
                    Log::warning('Failed to logout OneSignal user on logout', [
                        'user_id' => $user->id,
                        'fcmToken' => $fcmToken,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                // Optionally remove device from OneSignal
                try {
                    $oneSignal = new OneSignalService();
                    $oneSignal->removePushDevice($fcmToken);
                } catch (\Exception $e) {
                    Log::warning('Failed to remove OneSignal device on logout', [
                        'user_id' => $user->id,
                        'fcmToken' => $fcmToken,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                // Clear fcmToken from database
                $user->update([
                    'fcmToken' => null,
                    'deviceType' => null,
                    'deviceId' => null,
                ]);
                
                Log::info('OneSignal device token cleared on logout', [
                    'user_id' => $user->id,
                ]);
            }

            // Invalidate JWT token
            JWTAuth::setToken($token)->invalidate();
            
            // ✅ Clear session tracking
            try {
                $sessionService = new SessionManagementService();
                $sessionService->clearSessionTracking($user);
            } catch (\Exception $e) {
                Log::warning('Failed to clear session tracking on logout', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'message' => 'User logged out successfully',
            ], 200);

        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['error' => 'Token is invalid'], 401);
        } catch (\Exception $e) {
            return response()->json([
                'error'   => 'Something went wrong',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/user-set-password",
     *     tags={"Authentication", "Basecamp Users"},
     *     summary="Set password for basecamp or organisation user",
     *     description="Set or change password for a user by email. For organisation users, this also activates the account (sets status to true). Returns JWT token for authentication. All previous sessions are invalidated for security.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com", description="User's email address"),
     *             @OA\Property(property="password", type="string", format="password", example="NewPassword123!@#", description="New password (must contain uppercase, lowercase, number and special character, minimum 8 characters)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password set successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password set successfully"),
     *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJhbGc...", description="JWT token for authentication"),
     *             @OA\Property(property="user", type="object", description="User object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="User not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Password must include uppercase, lowercase, number & special character.")
     *         )
     *     )
     * )
     */
     /**
     * Set/Change password for user by email.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
	public function setPassword(Request $request)
	{
    	$validator = Validator::make($request->all(), [
          'email' => 'required|email',

          'password' => [
            'required',
            'string',
            'min:4',
            'regex:/[A-Z]/',
            'regex:/[a-z]/',
            'regex:/[0-9]/',
            'regex:/[@$!%*#?&^._-]/'
          ],

        ], [
          'password.min'       => 'Password must be at least 8 characters.',
          'password.regex'     => 'Password must include uppercase, lowercase, number & special character.',
        ]);

    	if ($validator->fails()) {
        	return response()->json([
            	'success' => false,
            	'message' => $validator->errors()->first(),
        	], 422);
    	}

   	 $user = User::where('email', $request->email)->first();

    	if ($user) {
     	
        if ($user->hasRole('organisation_user')) {
				$user->update([
                'password' => Hash::make($request->password),
                'status'   => true,
            ]);
            
            // Refresh user to get updated data
            $user->refresh();
            $user->load(['organisation', 'office', 'department', 'roles']);

            $token = JWTAuth::fromUser($user);
            
            // ✅ Invalidate all previous sessions/tokens
            try {
                $sessionService = new SessionManagementService();
                $sessionService->invalidatePreviousSessions($user, $token, null);
            } catch (\Exception $e) {
                Log::warning('Failed to invalidate previous sessions on setPassword', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
            
            $roleName = $user->getRoleNames()->first() ?? 'No role assigned';

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully. You are now logged in!',
                'data'    => [
                    'name'              => $user->first_name,
                    'last_name'         => $user->last_name,
                    'email'             => $user->email,
                    'token'             => $token,
                    'role'              => $roleName,
                    'orgId'             => optional($user->organisation)->id,
                    'orgname'           => optional($user->organisation)->name,
                    'officeId'          => optional($user->office)->id,
                    'office'            => optional($user->office)->name,
                    'departmentId'      => optional($user->department)->id,
                    'department'        => optional($user->department)->name,
                    'organisation_logo' => optional($user->organisation)->image,
                    'profileImage'      => $user->profile_photo_path ? url('storage/' . $user->profile_photo_path) : null,
                    'deviceType'        => $user->deviceType,
                    'deviceId'          => $user->deviceId,
                    'fcmToken'          => $user->fcmToken,
                    'appPaymentVersion' => '1',
                ],
            ]);
        }

        if (Hash::check($request->password, $user->password)) {
            // Load relationships and roles
            $user->load(['organisation', 'office', 'department', 'roles']);
            
            $token = JWTAuth::fromUser($user);
            
            // ✅ Invalidate all previous sessions/tokens
            try {
                $sessionService = new SessionManagementService();
                $sessionService->invalidatePreviousSessions($user, $token, null);
            } catch (\Exception $e) {
                Log::warning('Failed to invalidate previous sessions on setPassword login', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // DO NOT send any email for existing users logging in
            // Email should only be sent during registration, not on login
            Log::info('Existing user login - no email sent', [
                'email' => $user->email,
                'user_id' => $user->id,
                'email_verified_at' => $user->email_verified_at,
            ]);
            
            $roleName = $user->getRoleNames()->first() ?? 'No role assigned';

            return response()->json([
                'success' => true,
                'message' => 'Login successful!',
                'data'    => [
                    'name'              => $user->first_name,
                    'last_name'         => $user->last_name,
                    'email'             => $user->email,
                    'token'             => $token,
                    'role'              => $roleName,
                    'orgId'             => optional($user->organisation)->id,
                    'orgname'           => optional($user->organisation)->name,
                    'officeId'          => optional($user->office)->id,
                    'office'            => optional($user->office)->name,
                    'departmentId'      => optional($user->department)->id,
                    'department'        => optional($user->department)->name,
                    'organisation_logo' => optional($user->organisation)->image,
                    'profileImage'      => $user->profile_photo_path ? url('storage/' . $user->profile_photo_path) : null,
                    'deviceType'        => $user->deviceType,
                    'deviceId'          => $user->deviceId,
                    'fcmToken'          => $user->fcmToken,
                    'appPaymentVersion' => '1',
                ],
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials!',
            ], 401);
        }
    }

    // Create user - let default status handle it, then update if needed
    $user = User::create([
        'first_name' => $request->first_name ?? '',
        'last_name'  => $request->last_name ?? '',
        'email'      => $request->email,
        'password'   => Hash::make($request->password),
    ]);
    
    // Update status using DB raw to avoid type conversion issues
    // Use explicit cast to handle boolean column type
    try {
        DB::table('users')
            ->where('id', $user->id)
            ->update(['status' => DB::raw('0')]);
        $user->refresh();
    } catch (\Exception $e) {
        // If status update fails, log but continue (user is created)
        Log::warning('Failed to set user status to false', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
        ]);
    }
    
    // Refresh user model
    $user->refresh();

    // Assign basecamp role - ensure role exists for both guards
    try {
        // Ensure role exists for both web and api guards
        Role::firstOrCreate(
            ['name' => 'basecamp', 'guard_name' => 'web']
        );
        Role::firstOrCreate(
            ['name' => 'basecamp', 'guard_name' => 'api']
        );
        
        // Assign role to user (will assign for current guard)
        if (!$user->hasRole('basecamp')) {
            $user->assignRole('basecamp');
        }
        
        // Also explicitly assign for api guard since login uses JWT (api guard)
        $apiRole = Role::where('name', 'basecamp')->where('guard_name', 'api')->first();
        if ($apiRole && !$user->hasRole('basecamp', 'api')) {
            $user->roles()->syncWithoutDetaching([$apiRole->id]);
        }
        
        // Force refresh to ensure role is loaded
        $user->refresh();
        $user->load('roles');
        
        Log::info('Role assigned to user', [
            'user_id' => $user->id,
            'roles' => $user->getRoleNames()->toArray(),
        ]);
    } catch (\Exception $e) {
        Log::error('Failed to assign basecamp role', [
            'user_id' => $user->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
    
    // Only send verification email for NEW users (not existing users who are just setting password)
    // Check if user already has verified email - if yes, this is an existing user login, don't send verification email
    if (!$user->email_verified_at) {
        // This is a NEW user registration - send verification email
        $expires = Carbon::now()->addMinutes(1440); 
        $verificationUrl = URL::temporarySignedRoute(
            'user.verify', $expires, ['id' => $user->id]
        );

        try {
            $oneSignal = new OneSignalService();
            
            $htmlBody = (new VerifyUserEmail($user, $verificationUrl))->render();
            
            $oneSignal->registerEmailUserFallback($user->email, $user->id, [
                'subject' => 'Activate Your Tribe365 Account',
                'body'    => $htmlBody,
            ]);
            
            Log::info('✅ OneSignal verification email sent for NEW user', [
                'email' => $user->email,
                'user_id' => $user->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ OneSignal verification email failed', [
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
        }
        
        // DO NOT return token - user must verify email first
        // Return success message asking user to verify email
        return response()->json([
            'success' => true,
            'message' => 'Password set successfully! Please check your email to verify your account. After verification, you can login.',
            'data'    => [
                'email' => $user->email,
                'email_verified' => false,
            ],
        ]);
    } else {
        // This should not happen - new user created but email_verified_at is already set
        // This is a rare edge case, but handle it by just logging in without sending email
        Log::warning('New user created but email_verified_at already set', [
            'email' => $user->email,
            'user_id' => $user->id,
        ]);
        
        // Generate token since email is already verified
        $token = JWTAuth::fromUser($user);
        
        // Load relationships
        $user->load(['organisation', 'office', 'department', 'roles']);
        
        $roleName = $user->getRoleNames()->first() ?? 'No role assigned';
        
        return response()->json([
            'success' => true,
            'message' => 'Password set successfully! You are now logged in.',
            'data'    => [
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                    'role' => $roleName,
                    'orgId' => optional($user->organisation)->id,
                    'officeId' => optional($user->office)->id,
                    'departmentId' => optional($user->department)->id,
                ],
            ],
        ]);
    }
}
}
