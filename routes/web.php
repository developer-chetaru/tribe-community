<?php

use Illuminate\Http\Request;
use App\Livewire\LoginForm;
use App\Livewire\Department;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Livewire\Organisations\OrganisationIndex;
use App\Livewire\Organisations\OrganisationCreate;
use App\Livewire\Organisations\OrganisationPage;	
use App\Livewire\BaseCampUser;
use App\Livewire\UpdateBasecampUser;
use App\Livewire\AddDepartment;
use App\Livewire\UpdateDepartment;
use App\Livewire\Principles;
use App\Livewire\EditPrinciple;
use App\Livewire\AddPrinciple;
use App\Livewire\LearningTypeList;
use App\Livewire\EditLearningType;
use App\Livewire\LearningChecklistList;
use App\Livewire\AddLearningChecklist;
use App\Livewire\EditLearningChecklist;
use App\Livewire\AddLearningType;

use App\Livewire\WeeklySummary;
use App\Livewire\MonthlySummary;
use App\Livewire\TeamFeedbackQuestionsList;
use App\Livewire\AddTeamFeedbackQuestion;
use App\Livewire\EditTeamFeedbackQuestion;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\NotificationController;
use App\Livewire\ViewOrganisation;
use App\Livewire\UpdateOrganisation;
use App\Livewire\Office;
use App\Livewire\OfficeStaff;
use App\Livewire\Staff;
use App\Livewire\AddOffice;
use App\Livewire\AddStaff;
use App\Livewire\DirectingValue;
use App\Livewire\DirectingValueAdd;
use App\Livewire\DirectingValueEdit;
use App\Livewire\UpdateOffice;
use App\Livewire\UpdateStaff;
use App\Livewire\Userhptm;
use App\Livewire\Industry;
use App\Livewire\IndustryAdd;
use App\Livewire\IndustryEdit;
use App\Livewire\Myteam;
use App\Livewire\ReflectionList;
use App\Livewire\ReflectionCreate;
use App\Livewire\Admin\SendNotifications;
use App\Livewire\Admin\SendNotificationList;
use App\Livewire\Admin\ManageSubscriptions;
use App\Livewire\Admin\ManagePrompts;
use App\Livewire\Subscription\Billing;
use App\Http\Controllers\PaymentGatewayController;
use App\Livewire\User\Notifications;
use App\Http\Controllers\Auth\ForgotResetPasswordController;
use App\Http\Controllers\HPTMController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\Billing\StripeSubscriptionController;
use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\Billing\RefundController;


use App\Http\Controllers\ForgotController;

use App\Http\Controllers\TestEmailController;
use App\Http\Controllers\AppRedirectController;

Route::get('/test-email', [TestEmailController::class, 'sendTestEmail']);

// App redirect route for mobile deep linking
Route::get('/app-redirect', [AppRedirectController::class, 'redirect']);
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

Route::get('/', function () {
    return redirect()->to('/login');
});

Route::post('/register', [RegisteredUserController::class, 'store'])->name('custom.register');

// CSRF Token Refresh Route
Route::get('/refresh-csrf-token', function () {
    return response()->json(['token' => csrf_token()]);
})->middleware('web');

// Basecamp Billing - Allow access without login (payment first, then activation)
// User ID will be passed via session or query parameter
Route::get('/test-stripe-redirect', function() {
    return view('test-stripe-redirect');
})->name('test.stripe.redirect');

Route::get('/basecamp/billing', \App\Livewire\BasecampBilling::class)->name('basecamp.billing');

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
    Route::get('/hptm', Userhptm::class)->name('hptm.list');
    Route::get('/user/notifications', Notifications::class)->name('user.notifications');
    Route::get('/reflection-list', ReflectionList::class)->name('admin.reflections.index');
    Route::get('/reflections/create', ReflectionCreate::class)->name('reflection.create');

    // My Teammates - Only for organisation_user, organisation_admin, basecamp (with orgId)
    // Note: Component-level check ensures user has orgId
    Route::get('/myteam', Myteam::class)->name('myteam.list');
    
    // Billing (for directors and basecamp users)
    Route::middleware(['role:director|basecamp'])->group(function () {
        Route::get('/billing', Billing::class)->name('billing');
        
        // Stripe Checkout Routes with rate limiting (10 requests per minute)
        Route::middleware(['throttle:10,1'])->group(function () {
            Route::get('/billing/payment/success', [\App\Http\Controllers\Billing\StripeCheckoutController::class, 'handleSuccess'])
                ->name('billing.payment.success');
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
        Route::get('/basecampuser/edit/{id}', UpdateBasecampUser::class)->name('basecampuser.edit');
        
        // Admin Notification Features
        Route::get('admin/send-notification', SendNotifications::class)->name('admin.send-notification');
        Route::get('admin/send-notification-list', SendNotificationList::class)->name('admin.send-notification-list');
        
        // Subscription Management
        Route::get('/admin/subscriptions', ManageSubscriptions::class)->name('admin.subscriptions');
        
        // Prompt Management
        Route::get('/admin/prompts', ManagePrompts::class)->name('admin.prompts');
        
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
    });
});
