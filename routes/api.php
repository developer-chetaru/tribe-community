<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HPTMController;
use App\Http\Controllers\UserLeaveController;
use App\Http\Controllers\HappyIndexController;
use App\Http\Controllers\SummaryController;

use App\Http\Controllers\Api\WeeklySummaryController;
use App\Http\Controllers\Api\MonthlySummaryController;
use App\Http\Controllers\Api\ReflectionApiController;

use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\BasecampBillingController;

use App\Http\Controllers\ForgotController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/user-set-password', [AuthController::class, 'setPassword']);
Route::post('/login-admin', [AuthController::class, 'adminLogin']);
Route::post('/logout', [AuthController::class, 'logout']);


Route::middleware(['auth:api', 'validate.jwt'])->group(function () {
    // flutter api
    Route::get('/user-profile', [HPTMController::class, 'userProfile']);
  	Route::Post('/update-user-profile', [HPTMController::class, 'updateUserProfile']);
    Route::delete('/delete-profile-photo', [HPTMController::class, 'deleteProfilePhoto']);
    Route::get('/timezone-list', [HPTMController::class, 'getTimezoneList']);
    Route::post('/get-timezone-from-location', [HPTMController::class, 'getTimezoneFromLocation']);
    Route::get('/get-department-list', [HPTMController::class, 'getDepartmentList']);
    Route::post('/all-offices-and-departments', [HPTMController::class, 'getAllOfficenDepartments']);
    Route::post('/get-free-version-home-details', [HPTMController::class, 'getFreeVersionHomeDetails']);
    Route::post('/get-principles-list', [HPTMController::class, 'getPrinciplesList']);
    Route::post('/get-learning-checklist', [HPTMController::class, 'getLearningCheckList']);
    Route::post('/change-read-status-of-user-checklist', [HPTMController::class, 'changeReadStatusOfUserChecklist']);
	Route::get('/summary/{filterType}', [SummaryController::class, 'getSummary']);

    // Payment APIs
    Route::post('/submit-payment', [PaymentController::class, 'submitPayment']);
    Route::get('/payments', [PaymentController::class, 'getPayments']);

    // Organisation Subscription APIs (for directors/organisation users)
    Route::post('/billing/subscription/cancel', [\App\Http\Controllers\Billing\StripeSubscriptionController::class, 'cancelSubscription']);
    Route::post('/billing/subscription/renew', [\App\Http\Controllers\Billing\StripeCheckoutController::class, 'createRenewalCheckout']);
    Route::post('/billing/subscription/reactivate', [\App\Http\Controllers\Billing\StripeCheckoutController::class, 'reactivateSubscription']);

    // Basecamp Billing APIs
    Route::get('/basecamp/invoices', [BasecampBillingController::class, 'getInvoices']);
    Route::get('/basecamp/subscription', [BasecampBillingController::class, 'getSubscription']);
    Route::post('/basecamp/payment-intent', [BasecampBillingController::class, 'createPaymentIntent']);
    Route::post('/basecamp/confirm-payment', [BasecampBillingController::class, 'confirmPayment']);
    Route::post('/basecamp/cancel-subscription', [BasecampBillingController::class, 'cancelSubscription']);
    Route::post('/basecamp/subscription/renew', [\App\Http\Controllers\Billing\StripeCheckoutController::class, 'createRenewalCheckout']);
    Route::post('/basecamp/subscription/reactivate', [\App\Http\Controllers\Billing\StripeCheckoutController::class, 'reactivateSubscription']);
    Route::get('/basecamp/invoice/{id}/view', [BasecampBillingController::class, 'viewInvoice']);
    Route::get('/basecamp/invoices/{id}/view', [BasecampBillingController::class, 'viewInvoice']); // Alias for mobile app compatibility
    Route::get('/basecamp/invoice/{id}/download', [BasecampBillingController::class, 'downloadInvoice']);
    Route::get('/basecamp/invoices/{id}/download', [BasecampBillingController::class, 'downloadInvoice']); // Alias for mobile app compatibility

});

// Public endpoint - no authentication required
Route::get('/basecamp/stripe-config', [BasecampBillingController::class, 'getStripeConfig']);

Route::post('/change-password', [ForgotController::class, 'changePassword']);
Route::post('/forgot-password', [ForgotController::class, 'sendResetLink']);
Route::post('/reset-password', [ForgotController::class, 'reset']);
Route::post('/add-happy-index', [HappyIndexController::class, 'addHappyIndex']);
Route::post('/user/apply-leave', [UserLeaveController::class, 'userApplyLeave']);
Route::post('/user-change-leave-status', [UserLeaveController::class, 'userChangeLeaveStatus']);

Route::get('/weekly-summaries', [WeeklySummaryController::class, 'index']);
Route::get('/monthly-summary', [MonthlySummaryController::class, 'index']);
Route::post('/monthly-summary/generate', [MonthlySummaryController::class, 'generate']);

Route::get('/reflections', [ReflectionApiController::class, 'list']);
Route::post('/reflections', [ReflectionApiController::class, 'create']);
Route::get('/reflections/{reflection}/chats', [ReflectionApiController::class, 'chats']);
Route::post('/reflections/{reflection}/chats', [ReflectionApiController::class, 'sendChat']);

Route::get('/view-notification-list', [NotificationController::class, 'viewNotificationList']);

// API Documentation Route (Swagger UI)
// Note: This requires l5-swagger package to be installed
// If package is not installed, you can access documentation via:
// php artisan l5-swagger:generate (to generate docs)
// Then access at: /api/documentation
Route::post('/notification-archive', [NotificationController::class, 'notificationArchive']);
Route::post('/notification/archive-single', [NotificationController::class, 'archiveSingleNotification']);
Route::get('/view-archive-list', [NotificationController::class, 'getArchivedNotifications']);
Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
Route::post('/reflections/update-status', [ReflectionApiController::class, 'statusReflection']);

