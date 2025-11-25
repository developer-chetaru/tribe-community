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
use App\Mail\VerifyUserEmail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
  
      /**
     * Authenticate user and return JWT token with user details.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
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

    $user = auth()->user()->load(['organisation', 'office', 'department']);

   
    if (! $user->status) {
        return response()->json([
            'status'  => false,
            'message' => 'Your account is not active. Please activate your account.',
        ], 401);
    }

  
	$roleName = $user->getRoleNames()->first(); // "basecamp"


    if (! $roleName) {
        return response()->json(['status' => false, 'message' => 'User has no role assigned'], 401);
    }
  
    $user->update([
        'fcmToken'   => $request->fcmToken,
        'deviceType' => $request->deviceType,
        'deviceId'   => $request->deviceId,
    ]);

  
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
  * Register a new user and assign default role.
  *
  * @param  \Illuminate\Http\Request $request
  * @return \Illuminate\Http\JsonResponse
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
        	    'first_name' => $request->first_name,
        	    'last_name'  => $request->last_name,
        	    'email'      => $request->email,
        	    'password'   => Hash::make($request->password),
    	        'is_active'  => true,
	        ]);
   
	        $user->assignRole('basecamp');

        	$token = JWTAuth::fromUser($user);

			try {
                $oneSignal = new OneSignalService();

                $oneSignal->registerEmailUserFallback($user->email, $user->id);

                $verifyBody = view('emails.verify-user-inline', [
                    'user' => $user,
                    'verificationUrl' => $verificationUrl,
                ])->render();

                $oneSignal->registerEmailUserFallback($user->email, $user->id, [
                    'subject' => 'Activate Your Tribe365 Account',
                    'body'    => $verifyBody,
                ]);

                \Log::info('✅ OneSignal verification email sent (with inline Blade)', [
                    'email' => $user->email,
                ]);            

            } catch (\Throwable $e) {
                \Log::error('❌ OneSignal registration/email failed for new user', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }

        	return response()->json([
            	'message' => 'User registered successfully',
            	'user'    => $user,
            	'token'   => $token,
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

            JWTAuth::setToken($token)->invalidate();

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

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully. You are now logged in!',
                'user'    => $user,
                'token'   => $token,
            ]);
        }

        if (Hash::check($request->password, $user->password)) {
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => 'Login successful!',
                'user'    => $user,
                'token'   => $token,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials!',
            ], 401);
        }
    }

    $user = User::create([
        'first_name' => '',
        'last_name'  => '',
        'email'      => $request->email,
        'password'   => Hash::make($request->password),
        'status'     => false, 
    ]);

    $user->assignRole('basecamp');

    $token = JWTAuth::fromUser($user);

    $expires = Carbon::now()->addMinutes(1440); 
    $verificationUrl = URL::temporarySignedRoute(
        'user.verify', $expires, ['id' => $user->id]
    );

   // Mail::to($user->email)->send(new VerifyUserEmail($user, $verificationUrl));

	try {
        // Render Mailable into HTML
        $htmlBody = (new VerifyUserEmail($user, $verificationUrl))->render();
        $payload = [
            'app_id' => config('services.onesignal.app_id'),
            'include_email_tokens' => [$user->email],
            'email_subject' => 'Verify Your Email - Tribe365',
            'email_body' => $htmlBody,
        ];
        Http::withHeaders([
            'Authorization' => 'Basic ' . config('services.onesignal.rest_api_key'),
            'Content-Type'  => 'application/json',
        ])->post('https://onesignal.com/api/v1/notifications', $payload);
        Log::info('OneSignal verification email sent', ['email' => $user->email]);
    } catch (\Throwable $e) {
        Log::error('OneSignal verification email failed', [
            'email' => $user->email,
            'error' => $e->getMessage(),
        ]);
    }

	try {
        $oneSignal = new OneSignalService();
        $oneSignal->registerEmailUser($user->email, $user->id);
    } catch (\Throwable $e) {
        \Log::error('❌ OneSignal registration failed for new user', [
            'user_id' => $user->id,
            'error'   => $e->getMessage(),
        ]);
    }

    return response()->json([
        'success' => true,
        'message' => 'Registration successful! Please check your email to activate your account.',
        	'user'    => $user,
        	'token'   => $token,
    	]);
	}
}
