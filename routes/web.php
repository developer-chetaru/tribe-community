<?php

use App\Http\Controllers\Admin\AdminCOTController;
use App\Http\Controllers\Admin\AdminCOTquestionController;
use App\Http\Controllers\Admin\AdminCultureStructureController;
use App\Http\Controllers\Admin\AdminDiagnosticController;
use App\Http\Controllers\Admin\AdminIOTController;
use App\Http\Controllers\Admin\AdminLoginController;
use App\Http\Controllers\Admin\AdminMotivationController;
use App\Http\Controllers\Admin\AdminPersonalityTypeController;
use App\Http\Controllers\Admin\AdminTribeometerController;
use App\Http\Controllers\AppRedirectController;
use App\Http\Controllers\Auth\ForgotResetPasswordController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Billing\RefundController;
use App\Http\Controllers\Billing\StripeSubscriptionController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\ConnectingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DiagnosticsController;
use App\Http\Controllers\HPTMController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentGatewayController;
use App\Http\Controllers\SuperchargingController;
use App\Http\Controllers\TestEmailController;
use App\Http\Controllers\TribeometerController;
use App\Http\Controllers\VerificationController;
use App\Livewire\AddDepartment;
use App\Livewire\AddLearningChecklist;
use App\Livewire\AddLearningType;
use App\Livewire\AddOffice;
use App\Livewire\AddPrinciple;
use App\Livewire\AddStaff;
use App\Livewire\AddTeamFeedbackQuestion;
use App\Livewire\Admin\ActivityLogComponent;
use App\Livewire\Admin\LoginSessionsComponent;
use App\Livewire\Admin\ManagePrompts;
use App\Livewire\Admin\ManageSubscriptions;
use App\Livewire\Admin\SendNotificationList;
use App\Livewire\Admin\SendNotifications;
use App\Livewire\BaseCampUser;
use App\Livewire\Department;
use App\Livewire\DirectingValue;
use App\Livewire\DirectingValueAdd;
use App\Livewire\DirectingValueEdit;
use App\Livewire\EditLearningChecklist;
use App\Livewire\EditLearningType;
use App\Livewire\EditPrinciple;
use App\Livewire\EditTeamFeedbackQuestion;
use App\Livewire\HelpSupportChat;
use App\Livewire\Industry;
use App\Livewire\IndustryAdd;
use App\Livewire\IndustryEdit;
use App\Livewire\LearningChecklistList;
use App\Livewire\LearningTypeList;
use App\Livewire\MonthlySummary;
use App\Livewire\Myteam;
use App\Livewire\Office;
use App\Livewire\OfficeStaff;
use App\Livewire\OffloadingChat;
use App\Livewire\OffloadingCreate;
use App\Livewire\OffloadingList;
use App\Livewire\Organisations\OrganisationIndex;
use App\Livewire\Organisations\OrganisationPage;
use App\Livewire\Principles;
use App\Livewire\ReflectionCreate;
use App\Livewire\ReflectionList;
use App\Livewire\Staff;
use App\Livewire\Subscription\Billing;
use App\Livewire\TeamFeedbackQuestionsList;
use App\Livewire\UpdateBasecampUser;
use App\Livewire\UpdateDepartment;
use App\Livewire\UpdateOffice;
use App\Livewire\UpdateOrganisation;
use App\Livewire\UpdateStaff;
use App\Livewire\User\Notifications;
use App\Livewire\Userhptm;
use App\Livewire\ViewOrganisation;
use App\Livewire\WeeklySummary;
use Illuminate\Support\Facades\Route;

Route::get('/test-email', [TestEmailController::class, 'sendTestEmail']);

// App redirect route for mobile deep linking
Route::get('/app-redirect', [AppRedirectController::class, 'redirect'])->name('app.redirect');
Route::get('/open', [AppRedirectController::class, 'redirect']); // optional alias

// Public invoice sharing route (no authentication required) with rate limiting
Route::middleware(['throttle:10,1'])->group(function () {
    Route::get('/invoices/shared/{token}', [InvoiceController::class, 'shared'])->name('invoices.shared');
    Route::get('/invoices/shared/{token}/pay', [InvoiceController::class, 'initiateSharedPayment'])->name('invoices.shared.pay');
    Route::get('/invoices/shared/{token}/payment/success', [InvoiceController::class, 'handleSharedPaymentSuccess'])->name('invoices.shared.payment.success');
});

Route::post('/forgot-password', [ForgotResetPasswordController::class, 'sendResetLinkEmail'])
    ->name('password.email');
Route::get('/reset-password', [ForgotResetPasswordController::class, 'showResetForm'])
    ->name('custom.password.reset')
    ->middleware('guest');

Route::post('/reset-password', [ForgotResetPasswordController::class, 'store'])
    ->name('password.update')
    ->middleware('guest');

Route::get('/send-notification', [NotificationController::class, 'sendTest']);
Route::get('/verify-user/{id}', [VerificationController::class, 'verify'])
    ->name('user.verify')
    ->middleware('signed');

// Terms of Service and Privacy Policy Routes
Route::get('/terms', function () {
    $terms = file_exists(resource_path('markdown/terms.md'))
        ? \Illuminate\Support\Str::markdown(file_get_contents(resource_path('markdown/terms.md')))
        : '<h1>Terms of Service</h1><p>Terms of Service content will be displayed here.</p>';

    return view('terms', ['terms' => $terms]);
})->name('terms.show');

Route::get('/policy', function () {
    $policy = file_exists(resource_path('markdown/policy.md'))
        ? \Illuminate\Support\Str::markdown(file_get_contents(resource_path('markdown/policy.md')))
        : '<h1>Privacy Policy</h1><p>Privacy Policy content will be displayed here.</p>';

    return view('policy', ['policy' => $policy]);
})->name('policy.show');

Route::get('/', function () {
    return redirect()->to('/login');
});

// Handle GET requests to /logout (redirect to login instead of showing error)
Route::get('/logout', function () {
    return redirect()->to('/login');
})->name('logout.get');

Route::post('/register', [RegisteredUserController::class, 'store'])->name('custom.register');

// CSRF Token Refresh Route
Route::get('/refresh-csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->middleware('web');

// Basecamp Billing - Allow access without login (payment first, then activation)
// User ID will be passed via session or query parameter
Route::get('/test-stripe-redirect', function () {
    return view('test-stripe-redirect');
})->name('test.stripe.redirect');

Route::get('/basecamp/billing', \App\Livewire\BasecampBilling::class)->name('basecamp.billing');

// Account Suspended Page - Accessible to authenticated users with suspended accounts
// Account Restricted Page - Accessible to authenticated users with suspended/inactive accounts
Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->get('/account/restricted', function () {
    $user = auth()->user();
    $isBasecamp = $user && $user->hasRole('basecamp');

    // Check if account is active - if active, redirect to dashboard
    $isActive = false;
    $isSuspended = false;
    $isInactive = false;

    if ($isBasecamp) {
        $subscription = \App\Models\SubscriptionRecord::where('user_id', $user->id)
            ->where('tier', 'basecamp')
            ->orderBy('id', 'desc')
            ->first();

        if ($subscription) {
            if ($subscription->status === 'active') {
                $isActive = true;
            } elseif ($subscription->status === 'suspended') {
                $isSuspended = true;
            } elseif ($subscription->status === 'inactive') {
                $isInactive = true;
            }
        } else {
            // No subscription found, check user status
            if (in_array($user->status, ['active_verified', 'active_unverified'])) {
                $isActive = true;
            }
        }
    } else {
        $subscriptionService = new \App\Services\SubscriptionService;
        $subscriptionStatus = $subscriptionService->getSubscriptionStatus($user->orgId ?? 0);

        if (isset($subscriptionStatus['status'])) {
            if ($subscriptionStatus['status'] === 'active') {
                $isActive = true;
            } elseif ($subscriptionStatus['status'] === 'suspended') {
                $isSuspended = true;
            } elseif ($subscriptionStatus['status'] === 'inactive') {
                $isInactive = true;
            }
        } else {
            // No subscription status found, check user status
            if (in_array($user->status, ['active_verified', 'active_unverified'])) {
                $isActive = true;
            }
        }
    }

    // If account is active, redirect to dashboard
    if ($isActive && ! $isSuspended && ! $isInactive) {
        return redirect()->route('dashboard');
    }

    // Check if both status is inactive AND payment is unpaid
    $hasUnpaidInvoice = false;
    $invoiceAmount = null;

    if ($isBasecamp) {
        $subscription = \App\Models\SubscriptionRecord::where('user_id', $user->id)
            ->where('tier', 'basecamp')
            ->whereIn('status', ['suspended', 'inactive'])
            ->orderBy('id', 'desc')
            ->first();

        if ($subscription && $subscription->status === 'inactive') {
            // Check for unpaid invoice and get amount
            $unpaidInvoice = \App\Models\Invoice::where('subscription_id', $subscription->id)
                ->orWhere(function ($q) use ($user) {
                    $q->where('user_id', $user->id)->where('tier', 'basecamp');
                })
                ->whereIn('status', ['unpaid', 'pending', 'failed'])
                ->orderBy('created_at', 'desc')
                ->first();

            if ($unpaidInvoice) {
                $hasUnpaidInvoice = true;
                $invoiceAmount = $unpaidInvoice->total_amount;
            }
        }
    } else {
        $subscriptionService = new \App\Services\SubscriptionService;
        $subscriptionStatus = $subscriptionService->getSubscriptionStatus($user->orgId ?? 0);

        if (isset($subscriptionStatus['status']) && $subscriptionStatus['status'] === 'inactive') {
            // Check for unpaid invoice for organisation
            $unpaidInvoice = \App\Models\Invoice::where('organisation_id', $user->orgId)
                ->whereIn('status', ['unpaid', 'pending', 'failed'])
                ->orderBy('created_at', 'desc')
                ->first();

            if ($unpaidInvoice) {
                $hasUnpaidInvoice = true;
                $invoiceAmount = $unpaidInvoice->total_amount;
            }
        }
    }

    // If BOTH inactive AND unpaid, show payment required page directly
    if ($isInactive && $hasUnpaidInvoice) {
        return view('account.payment-required', [
            'amount' => $invoiceAmount ?? 12.00,
            'isBasecamp' => $isBasecamp,
        ]);
    }

    // Otherwise show account suspended page
    return view('account.suspended');
})->name('account.restricted');

// Redirect old URL to new route for backward compatibility
Route::middleware(['auth:sanctum', config('jetstream.auth_session')])->get('/account/suspended', function () {
    return redirect()->route('account.restricted');
});

// Basecamp payment routes with rate limiting (10 requests per minute)
Route::middleware(['throttle:10,1'])->group(function () {
    Route::post('/basecamp/checkout/create', [\App\Http\Controllers\Billing\BasecampStripeCheckoutController::class, 'createCheckoutSession'])
        ->name('basecamp.checkout.create');
    Route::get('/basecamp/checkout/redirect', [\App\Http\Controllers\Billing\BasecampStripeCheckoutController::class, 'redirectToCheckout'])
        ->name('basecamp.checkout.redirect');
    Route::get('/basecamp/billing/payment/success', [\App\Http\Controllers\Billing\BasecampStripeCheckoutController::class, 'handleSuccess'])
        ->name('basecamp.billing.payment.success');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'validate.web.session', // Validate session is from most recent login
    'check.basecamp.payment', // Check basecamp payment before accessing any route
    // Note: check.subscription is already applied globally in bootstrap/app.php
])->group(function () {
    Route::get('/change-password', function () {
        return view('profile.change-password');
    })->name('password.change');
    Route::middleware(['auth'])->get('/user-profile', [HPTMController::class, 'userProfile']);
    Route::middleware(['auth'])->post('/get-timezone-from-location', [HPTMController::class, 'getTimezoneFromLocation']);
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/weekly-summary', WeeklySummary::class)->name('weekly.summary');
    Route::get('/monthly-summary', MonthlySummary::class)->name('monthly.summary');
    // Routes accessible to all authenticated users (organisation_user, organisation_admin, basecamp)
    Route::get('/hptm/{activePrincipleId?}', Userhptm::class)->name('hptm.list');
    Route::get('/user/notifications', Notifications::class)->name('user.notifications');
    Route::get('/reflection-list', ReflectionList::class)->name('admin.reflections.index');
    Route::get('/reflections/create', ReflectionCreate::class)->name('reflection.create');

    // User Offloading/Feedback Submission
    Route::get('/offloading/create', OffloadingCreate::class)->name('offloading.create');
    Route::get('/offloading/list', OffloadingList::class)->name('offloading.list');
    Route::get('/offloading/chat/{feedbackId}', OffloadingChat::class)->name('offloading.chat');

    Route::get('/help-support', HelpSupportChat::class)->name('help.support');

    // Connecting Module - User Routes
    Route::prefix('connecting')->name('connecting.')->group(function () {
        Route::get('/team-role-map', [ConnectingController::class, 'teamRoleMap'])->name('team-role-map');
        Route::post('/team-role-map/submit', [ConnectingController::class, 'submitTeamRoleMap'])->name('team-role-map.submit');
        Route::get('/team-role-map/results', [ConnectingController::class, 'teamRoleMapResults'])->name('team-role-map.results');

        Route::get('/personality-type', [ConnectingController::class, 'personalityType'])->name('personality-type');
        Route::post('/personality-type/submit', [ConnectingController::class, 'submitPersonalityType'])->name('personality-type.submit');
        Route::get('/personality-type/results', [ConnectingController::class, 'personalityTypeResults'])->name('personality-type.results');
    });

    // Supercharging Module - User Routes
    Route::prefix('supercharging')->name('supercharging.')->group(function () {
        Route::get('/culture-structure', [SuperchargingController::class, 'cultureStructure'])->name('culture-structure');
        Route::post('/culture-structure/submit', [SuperchargingController::class, 'submitCultureStructure'])->name('culture-structure.submit');
        Route::get('/culture-structure/results', [SuperchargingController::class, 'cultureStructureResults'])->name('culture-structure.results');

        Route::get('/motivation', [SuperchargingController::class, 'motivation'])->name('motivation');
        Route::post('/motivation/submit', [SuperchargingController::class, 'submitMotivation'])->name('motivation.submit');
        Route::get('/motivation/results', [SuperchargingController::class, 'motivationResults'])->name('motivation.results');
    });

    // Diagnostics Module - User Routes
    Route::prefix('diagnostics')->name('diagnostics.')->group(function () {
        Route::get('/', [DiagnosticsController::class, 'index'])->name('index');
        Route::post('/submit', [DiagnosticsController::class, 'submit'])->name('submit');
        Route::get('/results', [DiagnosticsController::class, 'results'])->name('results');
    });

    // Tribeometer Module - User Routes
    Route::prefix('tribeometer')->name('tribeometer.')->group(function () {
        Route::get('/', [TribeometerController::class, 'index'])->name('index');
        Route::post('/submit', [TribeometerController::class, 'submit'])->name('submit');
        Route::get('/results', [TribeometerController::class, 'results'])->name('results');
    });

    // My Teammates - Only for organisation_user, organisation_admin, basecamp (with orgId)
    // Note: Component-level check ensures user has orgId
    Route::get('/myteam', Myteam::class)->name('myteam.list');

    // Billing (for directors and basecamp users)
    Route::middleware(['role:director|basecamp'])->group(function () {
        Route::get('/billing', Billing::class)->name('billing');

        // Stripe Checkout Routes with rate limiting (10 requests per minute)
        Route::middleware(['throttle:10,1'])->group(function () {
            Route::post('/billing/renewal/checkout', [\App\Http\Controllers\Billing\StripeCheckoutController::class, 'createRenewalCheckout'])
                ->name('billing.renewal.checkout');
            Route::post('/billing/reactivate', [\App\Http\Controllers\Billing\StripeCheckoutController::class, 'reactivateSubscription'])
                ->name('billing.reactivate');
            Route::get('/billing/payment/success', [\App\Http\Controllers\Billing\StripeCheckoutController::class, 'handleSuccess'])
                ->name('billing.payment.success');
            Route::get('/billing/stripe/redirect', function () {
                $checkoutUrl = session()->get('stripe_checkout_url');
                if (! $checkoutUrl) {
                    return redirect()->route('billing')->with('error', 'Payment session expired. Please try again.');
                }
                session()->forget('stripe_checkout_url');

                return view('stripe-redirect', ['url' => $checkoutUrl]);
            })->name('billing.stripe.redirect');
        });

        Route::get('/invoices/{id}/download', [InvoiceController::class, 'download'])->name('invoices.download');
        Route::get('/invoices/{id}/view', [InvoiceController::class, 'view'])->name('invoices.view');

        // Payment Gateway Routes with rate limiting (10 requests per minute)
        Route::middleware(['throttle:10,1'])->group(function () {
            Route::post('/payment/process', [PaymentGatewayController::class, 'processPayment'])->name('payment.process');
        });

        Route::get('/payment/config', [PaymentGatewayController::class, 'getPaymentConfig'])->name('payment.config');
    });

    // Stripe Billing Routes with rate limiting (10 requests per minute)
    Route::prefix('billing/stripe')->name('billing.stripe.')->middleware(['throttle:10,1'])->group(function () {
        Route::post('/subscription/create', [StripeSubscriptionController::class, 'createSubscription'])
            ->name('subscription.create');
        Route::post('/subscription/add-user', [StripeSubscriptionController::class, 'addUser'])
            ->name('subscription.add-user');
        Route::post('/subscription/remove-user', [StripeSubscriptionController::class, 'removeUser'])
            ->name('subscription.remove-user');
        Route::post('/subscription/cancel', [StripeSubscriptionController::class, 'cancelSubscription'])
            ->name('subscription.cancel');

        // Stripe Payment Routes
        Route::post('/payment-intent/create', [\App\Http\Controllers\Billing\StripePaymentController::class, 'createPaymentIntent'])
            ->name('payment-intent.create');
        Route::post('/payment/confirm', [\App\Http\Controllers\Billing\StripePaymentController::class, 'confirmPayment'])
            ->name('payment.confirm');
    });

    // Stripe Webhook (No CSRF protection needed)
    Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handleWebhook'])
        ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);

    // Refund Routes
    Route::prefix('billing/refunds')->name('billing.refunds.')->group(function () {
        Route::post('/process', [RefundController::class, 'processRefund'])
            ->name('process');
        Route::get('/history', [RefundController::class, 'getRefundHistory'])
            ->name('history');
    });

    // Super Admin only routes - protected with super_admin role
    Route::middleware(['role:super_admin'])->group(function () {
        // Organisation Management
        Route::get('/organisations', OrganisationIndex::class)->name('organisations.index');
        Route::get('/organisations/create', OrganisationPage::class)->name('organisations.create');
        Route::get('/organisations/update/{id}', UpdateOrganisation::class)->name('update-organisation');
        Route::get('/organisations/office/{id}', Office::class)->name('office-list');
        Route::get('/office/{officeId}/staff', OfficeStaff::class)->name('office.staff');
        Route::get('/organisations/add-office/{id}', AddOffice::class)->name('office-add');
        Route::get('/organisations/update-office/{id}', UpdateOffice::class)->name('office-update');
        Route::get('/organisations/add-staff/{id}', AddStaff::class)->name('staff-add');
        Route::get('/organisations/update-staff/{id}', UpdateStaff::class)->name('staff-update');
        Route::get('/organisations/staff/{id}', Staff::class)->name('staff-list');
        Route::get('/organisations/view/{id}', ViewOrganisation::class)->name('organisations.view');

        // Basecamp User Management
        Route::get('/basecampuser', BaseCampUser::class)->name('basecampuser');
        Route::get('/basecampuser/view/{id}', \App\Livewire\ViewBasecampUser::class)->name('basecampuser.view');
        Route::get('/basecampuser/edit/{id}', UpdateBasecampUser::class)->name('basecampuser.edit');

        // Admin Notification Features
        Route::get('admin/notifications', Notifications::class)->name('admin.notifications');
        Route::get('admin/send-notification', SendNotifications::class)->name('admin.send-notification');
        Route::get('admin/send-basecamp-notification', \App\Livewire\Admin\SendBasecampNotifications::class)->name('admin.send-basecamp-notification');
        Route::get('admin/send-notification-list', SendNotificationList::class)->name('admin.send-notification-list');
        Route::get('admin/activity-log', ActivityLogComponent::class)->name('admin.activity-log');
        Route::get('admin/login-sessions', LoginSessionsComponent::class)->name('admin.login-sessions');

        // Subscription Management
        Route::get('/admin/subscriptions', ManageSubscriptions::class)->name('admin.subscriptions');

        // Prompt Management
        Route::get('/admin/prompts', ManagePrompts::class)->name('admin.prompts');

        // Assessments - Admin Routes (Super Admin Only)
        Route::prefix('admin/connecting')->group(function () {
            // Team Role Map
            Route::prefix('team-role-map')->name('admin.cot.')->group(function () {
                Route::get('/questions', [AdminCOTquestionController::class, 'index'])->name('questions.index');
                Route::get('/questions/create', [AdminCOTquestionController::class, 'create'])->name('questions.create');
                Route::post('/questions', [AdminCOTquestionController::class, 'store'])->name('questions.store');
                Route::get('/questions/{id}/edit', [AdminCOTquestionController::class, 'edit'])->name('questions.edit');
                Route::put('/questions/{id}', [AdminCOTquestionController::class, 'update'])->name('questions.update');
                Route::delete('/questions/{id}', [AdminCOTquestionController::class, 'destroy'])->name('questions.destroy');

                Route::get('/descriptions', [AdminCOTController::class, 'listTeamRoleMapDescription'])->name('team-role-descriptions.index');
                Route::get('/descriptions/{id}/edit', [AdminCOTController::class, 'editTeamRoleMapDescription'])->name('team-role-descriptions.edit');
                Route::put('/descriptions/{id}', [AdminCOTController::class, 'updateTeamRoleMapDescription'])->name('team-role-descriptions.update');

                Route::get('/results', [AdminCOTController::class, 'teamRoleMapResults'])->name('team-role-results.index');
                Route::get('/results/export', [AdminCOTController::class, 'exportTeamRoleMapResults'])->name('team-role-results.export');
            });

            // Personality Type
            Route::prefix('personality-type')->name('admin.personality-type.')->group(function () {
                Route::get('/questions', [AdminPersonalityTypeController::class, 'questionsIndex'])->name('questions.index');
                Route::get('/questions/create', [AdminPersonalityTypeController::class, 'questionsCreate'])->name('questions.create');
                Route::post('/questions', [AdminPersonalityTypeController::class, 'questionsStore'])->name('questions.store');
                Route::get('/questions/{id}/edit', [AdminPersonalityTypeController::class, 'questionsEdit'])->name('questions.edit');
                Route::put('/questions/{id}', [AdminPersonalityTypeController::class, 'questionsUpdate'])->name('questions.update');
                Route::delete('/questions/{id}', [AdminPersonalityTypeController::class, 'questionsDestroy'])->name('questions.destroy');

                Route::get('/options', [AdminPersonalityTypeController::class, 'optionsIndex'])->name('options.index');

                Route::get('/values', [AdminPersonalityTypeController::class, 'valuesIndex'])->name('values.index');
                Route::get('/values/{id}/edit', [AdminPersonalityTypeController::class, 'valuesEdit'])->name('values.edit');
                Route::put('/values/{id}', [AdminPersonalityTypeController::class, 'valuesUpdate'])->name('values.update');

                Route::get('/results', [AdminPersonalityTypeController::class, 'resultsIndex'])->name('results.index');
                Route::get('/results/export', [AdminPersonalityTypeController::class, 'exportResults'])->name('results.export');
            });
        });

        Route::prefix('admin/supercharging')->group(function () {
            Route::prefix('culture-structure')->name('admin.culture-structure.')->group(function () {
                Route::get('/questions', [AdminCultureStructureController::class, 'questionsIndex'])->name('questions.index');
                Route::get('/questions/create', [AdminCultureStructureController::class, 'questionsCreate'])->name('questions.create');
                Route::post('/questions', [AdminCultureStructureController::class, 'questionsStore'])->name('questions.store');
                Route::get('/questions/{id}/edit', [AdminCultureStructureController::class, 'questionsEdit'])->name('questions.edit');
                Route::put('/questions/{id}', [AdminCultureStructureController::class, 'questionsUpdate'])->name('questions.update');
                Route::delete('/questions/{id}', [AdminCultureStructureController::class, 'questionsDestroy'])->name('questions.destroy');

                Route::get('/types', [AdminCultureStructureController::class, 'typesIndex'])->name('types.index');
                Route::get('/types/{id}/edit', [AdminCultureStructureController::class, 'typesEdit'])->name('types.edit');
                Route::put('/types/{id}', [AdminCultureStructureController::class, 'typesUpdate'])->name('types.update');

                Route::get('/results', [AdminCultureStructureController::class, 'resultsIndex'])->name('results.index');
            });

            Route::prefix('motivation')->name('admin.motivation.')->group(function () {
                Route::get('/questions', [AdminMotivationController::class, 'questionsIndex'])->name('questions.index');
                Route::get('/questions/create', [AdminMotivationController::class, 'questionsCreate'])->name('questions.create');
                Route::post('/questions', [AdminMotivationController::class, 'questionsStore'])->name('questions.store');
                Route::get('/questions/{id}/edit', [AdminMotivationController::class, 'questionsEdit'])->name('questions.edit');
                Route::put('/questions/{id}', [AdminMotivationController::class, 'questionsUpdate'])->name('questions.update');
                Route::delete('/questions/{id}', [AdminMotivationController::class, 'questionsDestroy'])->name('questions.destroy');

                Route::get('/values', [AdminMotivationController::class, 'valuesIndex'])->name('values.index');
                Route::get('/values/{id}/edit', [AdminMotivationController::class, 'valuesEdit'])->name('values.edit');
                Route::put('/values/{id}', [AdminMotivationController::class, 'valuesUpdate'])->name('values.update');

                Route::get('/results', [AdminMotivationController::class, 'resultsIndex'])->name('results.index');
            });
        });

        Route::prefix('admin/diagnostics')->name('admin.diagnostic.')->group(function () {
            Route::get('/', [AdminDiagnosticController::class, 'index'])->name('index');
            Route::post('/update/{id}', [AdminDiagnosticController::class, 'update'])->name('update');
            Route::get('/options', [AdminDiagnosticController::class, 'getDiagnosticOptionList'])->name('options.index');
            Route::post('/options/update', [AdminDiagnosticController::class, 'upadateDiagnosticOption'])->name('options.update');
            Route::post('/options/add', [AdminDiagnosticController::class, 'addDiagnosticOption'])->name('options.add');
            Route::post('/options/delete', [AdminDiagnosticController::class, 'deleteDiagnosticOption'])->name('options.delete');
            Route::get('/categories', [AdminDiagnosticController::class, 'getDiagnosticValuesList'])->name('categories.index');
            Route::post('/categories/update', [AdminDiagnosticController::class, 'updateDiagnosticCategoryValues'])->name('categories.update');
            Route::post('/categories/add', [AdminDiagnosticController::class, 'addDiagnosticCategory'])->name('categories.add');
            Route::post('/categories/delete', [AdminDiagnosticController::class, 'deleteDiagnosticCategory'])->name('categories.delete');
            Route::post('/questions/add', [AdminDiagnosticController::class, 'addDiagnosticQuestion'])->name('questions.add');
            Route::post('/questions/delete', [AdminDiagnosticController::class, 'deleteDiagnosticQuestion'])->name('questions.delete');
            Route::get('/results', [AdminDiagnosticController::class, 'resultsIndex'])->name('results.index');
        });

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('tribeometer', [AdminTribeometerController::class, 'index'])->name('tribeometer.index');
            Route::get('tribeometer/question/edit/{id}', [AdminTribeometerController::class, 'editQuestion'])->name('tribeometer.question.edit');
            Route::post('tribeometer/question/update/{id}', [AdminTribeometerController::class, 'updateQuestion'])->name('tribeometer.question.update');
            Route::get('tribeometer/options', [AdminTribeometerController::class, 'getOptionList'])->name('tribeometer.option.list');
            Route::post('tribeometer/option/update', [AdminTribeometerController::class, 'updateOption'])->name('tribeometer.option.update');
            Route::get('tribeometer/values', [AdminTribeometerController::class, 'getValuesList'])->name('tribeometer.value.list');
            Route::post('tribeometer/value/update', [AdminTribeometerController::class, 'updateValue'])->name('tribeometer.value.update');
            Route::post('tribeometer/option/add', [AdminTribeometerController::class, 'addOption'])->name('tribeometer.option.add');
            Route::post('tribeometer/question/add', [AdminTribeometerController::class, 'addQuestion'])->name('tribeometer.question.add');
            Route::post('tribeometer/value/add', [AdminTribeometerController::class, 'addValue'])->name('tribeometer.value.add');
            Route::post('tribeometer/question/delete', [AdminTribeometerController::class, 'deleteQuestion'])->name('tribeometer.question.delete');
            Route::post('tribeometer/option/delete', [AdminTribeometerController::class, 'deleteOption'])->name('tribeometer.option.delete');
            Route::post('tribeometer/value/delete', [AdminTribeometerController::class, 'deleteValue'])->name('tribeometer.value.delete');
            Route::get('tribeometer/results', [AdminTribeometerController::class, 'resultsIndex'])->name('tribeometer.results.index');
        });

        // Invoice Routes (Admin - super_admin can access all invoices)
        Route::get('/admin/invoices/{id}/download', [InvoiceController::class, 'download'])->name('admin.invoices.download');
        Route::get('/admin/invoices/{id}/view', [InvoiceController::class, 'view'])->name('admin.invoices.view');

        // Universal Settings - Master Data Management (Super Admin only)
        Route::get('/department', Department::class)->name('department');
        Route::get('/department/add', AddDepartment::class)->name('add.department');
        Route::get('/department/edit/{id}', UpdateDepartment::class)->name('update.department');
        Route::get('/directing-value', DirectingValue::class)->name('directing-value.list');
        Route::get('/directing-values/add', DirectingValueAdd::class)->name('add.directing-value');
        Route::get('/directing-values/edit/{id}', DirectingValueEdit::class)->name('update.directing-value');
        Route::get('/principles', Principles::class)->name('principles');
        Route::get('/principle/edit/{id}', EditPrinciple::class)->name('principle.edit');
        Route::get('/principles/add', AddPrinciple::class)->name('principles.add');
        Route::get('/learning-types', LearningTypeList::class)->name('learningtype.list');
        Route::get('/learning-types/edit/{id}', EditLearningType::class)->name('learningtype.edit');
        Route::get('/learning-types/add', AddLearningType::class)->name('learningtype.add');
        Route::get('/learning-checklist', LearningChecklistList::class)->name('learningchecklist.list');
        Route::get('/learning-checklist/add', AddLearningChecklist::class)->name('learningchecklist.add');
        Route::get('/learning-checklist/edit/{id}', EditLearningChecklist::class)->name('learningchecklist.edit');
        Route::get('/team-feedback', TeamFeedbackQuestionsList::class)->name('team-feedback.list');
        Route::get('/team-feedback/add', AddTeamFeedbackQuestion::class)->name('team-feedback.add');
        Route::get('/team-feedback/edit/{id}', EditTeamFeedbackQuestion::class)->name('team-feedback.edit');
        Route::get('/industries', Industry::class)->name('industries.list');
        Route::get('/industries/add', IndustryAdd::class)->name('industries.add');
        Route::get('/industries/edit/{id}', IndustryEdit::class)->name('industries.edit');

        // IOT/Offloading Admin Routes
        Route::post('/admin/improvementChkPass', [AdminLoginController::class, 'improvementChkPass'])->name('admin.improvement.check-pass');
        Route::post('/admin/iot-change-password', [AdminLoginController::class, 'changePassword'])->name('admin.iot.change-password');
        Route::get('/admin/iot-dashboard/{orgId}', [AdminIOTController::class, 'getIotDeshboard'])->name('admin.iot.dashboard');
        Route::get('/admin/feedback-list/{orgId}/{status}/{officeId?}', [AdminIOTController::class, 'feedbackList'])->name('admin.iot.feedback-list');
        Route::get('/admin/iot-chatbox/{feedbackId}', [AdminIOTController::class, 'iotChatbox'])->name('admin.iot.chatbox');
        Route::post('/admin/iot-send-message', [AdminIOTController::class, 'sendChatMessagesFromAdmin'])->name('admin.iot.send-message');
        Route::post('/admin/iot-update-feedback', [AdminIOTController::class, 'updateFeedback'])->name('admin.iot.update-feedback');
        Route::post('/admin/iot-assign-theme', [AdminIOTController::class, 'assignTheme'])->name('admin.iot.assign-theme');
        Route::get('/admin/theme-list/{orgId}', [AdminIOTController::class, 'themeList'])->name('admin.theme-list');
        Route::get('/admin/add-theme/{orgId}', [AdminIOTController::class, 'addTheme'])->name('admin.add-theme');
        Route::post('/admin/store-theme', [AdminIOTController::class, 'storeTheme'])->name('admin.store-theme');
        Route::get('/admin/edit-theme/{themeId}', [AdminIOTController::class, 'editTheme'])->name('admin.edit-theme');
        Route::post('/admin/update-theme/{themeId}', [AdminIOTController::class, 'updateTheme'])->name('admin.update-theme');
    });

    // REMOVED: API routes for summaries moved to routes/api.php
    // These routes should not require web session authentication
    // They are now in routes/api.php without middleware
});
