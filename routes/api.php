<?php

use App\Http\Controllers\Api\ApiHelpSupportController;
use App\Http\Controllers\Api\BasecampBillingController;
use App\Http\Controllers\Api\MonthlySummaryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReflectionApiController;
use App\Http\Controllers\Api\ApiIOTController;
use App\Http\Controllers\Api\ApiCOTController;
use App\Http\Controllers\Api\ApiPersonalitytypeController;
use App\Http\Controllers\Api\ApiCultureStructureController;
use App\Http\Controllers\Api\ApiMotivationController;
use App\Http\Controllers\Api\ApiDiagnosticController;
use App\Http\Controllers\Api\ApiTribeometerController;
use App\Http\Controllers\Api\WeeklySummaryController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotController;
use App\Http\Controllers\HappyIndexController;
use App\Http\Controllers\HPTMController;
use App\Http\Controllers\SummaryController;
use App\Http\Controllers\UserLeaveController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/user-set-password', [AuthController::class, 'setPassword']);
Route::post('/login-admin', [AuthController::class, 'adminLogin']);
Route::post('/logout', [AuthController::class, 'logout']);

// Summary APIs - no middleware (handle authentication in controller)
// This allows expired tokens to work (auto logout disabled)
// Add session middleware for web requests (web uses session auth, not Bearer token)
// Using 'web' middleware to enable session support for these API endpoints
Route::middleware(['web'])->group(function () {
    Route::get('/weekly-summaries', [WeeklySummaryController::class, 'index']);
    Route::get('/monthly-summary', [MonthlySummaryController::class, 'index']);
    Route::post('/monthly-summary/generate', [MonthlySummaryController::class, 'generate']);
    Route::get('/summary/{filterType}', [SummaryController::class, 'getSummary']);
});

Route::middleware(['auth:api', 'validate.jwt'])->group(function () {
    // flutter api
    Route::get('/user-profile', [HPTMController::class, 'userProfile']);
    Route::Post('/update-user-profile', [HPTMController::class, 'updateUserProfile']);
    Route::delete('/delete-profile-photo', [HPTMController::class, 'deleteProfilePhoto']);
    Route::delete('/delete-account', [HPTMController::class, 'deleteAccount']);
    Route::get('/timezone-list', [HPTMController::class, 'getTimezoneList']);
    Route::post('/get-timezone-from-location', [HPTMController::class, 'getTimezoneFromLocation']);
    Route::get('/get-department-list', [HPTMController::class, 'getDepartmentList']);
    Route::post('/all-offices-and-departments', [HPTMController::class, 'getAllOfficenDepartments']);
    Route::post('/get-free-version-home-details', [HPTMController::class, 'getFreeVersionHomeDetails']);
    Route::post('/get-principles-list', [HPTMController::class, 'getPrinciplesList']);
    Route::post('/get-learning-checklist', [HPTMController::class, 'getLearningCheckList']);
    Route::post('/change-read-status-of-user-checklist', [HPTMController::class, 'changeReadStatusOfUserChecklist']);

    // Payment APIs
    Route::post('/submit-payment', [PaymentController::class, 'submitPayment']);
    Route::get('/payments', [PaymentController::class, 'getPayments']);

    // IOT/Offloading API routes
    Route::post('/iot-post-feedback', [ApiIOTController::class, 'postFeedback']);
    Route::post('/iot-get-feedback-detail', [ApiIOTController::class, 'getFeedbackDetail']);
    Route::post('/iot-send-msg', [ApiIOTController::class, 'iotSendMsg']);
    Route::post('/iot-inbox-list', [ApiIOTController::class, 'getInboxChatList']);
    Route::post('/iot-get-msg', [ApiIOTController::class, 'getChatMessages']);
    Route::post('/iot-get-theme-list', [ApiIOTController::class, 'getThemeList']);

    // Assessments API routes (Connecting / Supercharging / Diagnostics / Tribeometer)
    Route::get('/cot/questions', [ApiCOTController::class, 'getQuestions']);
    Route::post('/cot/submit-answers', [ApiCOTController::class, 'submitAnswers']);
    Route::get('/cot/results', [ApiCOTController::class, 'getResults']);
    Route::get('/cot/role-descriptions', [ApiCOTController::class, 'getRoleDescriptions']);
    Route::get('/cot/user-answers', [ApiCOTController::class, 'getUserAnswers']);

    Route::get('/personality-type/questions', [ApiPersonalitytypeController::class, 'getQuestions']);
    Route::post('/personality-type/submit-answers', [ApiPersonalitytypeController::class, 'submitAnswers']);
    Route::get('/personality-type/results', [ApiPersonalitytypeController::class, 'getResults']);
    Route::get('/personality-type/values', [ApiPersonalitytypeController::class, 'getValues']);
    Route::get('/personality-type/user-answers', [ApiPersonalitytypeController::class, 'getUserAnswers']);

    Route::get('/culture-structure/questions', [ApiCultureStructureController::class, 'getQuestions']);
    Route::post('/culture-structure/submit-answers', [ApiCultureStructureController::class, 'submitAnswers']);
    Route::put('/culture-structure/update-answers', [ApiCultureStructureController::class, 'updateAnswers']);
    Route::get('/culture-structure/user-answers', [ApiCultureStructureController::class, 'getUserAnswers']);
    Route::get('/culture-structure/results/{userId}', [ApiCultureStructureController::class, 'getResults']);
    Route::get('/culture-structure/types', [ApiCultureStructureController::class, 'getTypes']);

    Route::get('/motivation/questions', [ApiMotivationController::class, 'getQuestions']);
    Route::post('/motivation/submit-answers', [ApiMotivationController::class, 'submitAnswers']);
    Route::put('/motivation/update-answers', [ApiMotivationController::class, 'updateAnswers']);
    Route::get('/motivation/user-answers', [ApiMotivationController::class, 'getUserAnswers']);
    Route::get('/motivation/results/{userId}', [ApiMotivationController::class, 'getResults']);
    Route::get('/motivation/values', [ApiMotivationController::class, 'getValues']);

    Route::get('/getDiagnosticQuestionList', [ApiDiagnosticController::class, 'getDiagnosticQuestionList']);
    Route::post('/addDiagnosticAnswers', [ApiDiagnosticController::class, 'addDiagnosticAnswers']);
    Route::get('/getDiagnosticCompletedAnswers', [ApiDiagnosticController::class, 'getDiagnosticCompletedAnswers']);
    Route::post('/updateDiagnosticAnswers', [ApiDiagnosticController::class, 'updateDiagnosticAnswers']);
    Route::get('/isDiagnosticTribeometerAnswerDone', [ApiDiagnosticController::class, 'isDiagnosticTribeometerAnswerDone']);
    Route::post('/getDiagnosticReport', [ApiDiagnosticController::class, 'getDiagnosticReport']);

    Route::get('/tribeometer/questions', [ApiTribeometerController::class, 'getQuestions']);
    Route::post('/tribeometer/submit-answers', [ApiTribeometerController::class, 'submitAnswers']);
    Route::get('/tribeometer/results', [ApiTribeometerController::class, 'getResults']);
    Route::get('/tribeometer/values', [ApiTribeometerController::class, 'getValues']);
    Route::get('/tribeometer/user-answers', [ApiTribeometerController::class, 'getUserAnswers']);
    Route::get('/tribeometer/check-completion', [ApiTribeometerController::class, 'checkCompletion']);

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

    Route::post('/add-happy-index', [HappyIndexController::class, 'addHappyIndex']);

    // Help & Support Chat API
    Route::prefix('help-support')->group(function () {
        Route::get('/history', [ApiHelpSupportController::class, 'history']);
        Route::post('/send', [ApiHelpSupportController::class, 'send']);
        Route::get('/quick-actions', [ApiHelpSupportController::class, 'quickActions']);
        Route::post('/run-action', [ApiHelpSupportController::class, 'runAction']);
        Route::delete('/clear', [ApiHelpSupportController::class, 'clear']);
    });

});

// Public endpoints for Stripe Checkout callbacks (no auth required - handled inside)
Route::get('/basecamp/payment/success', [BasecampBillingController::class, 'handlePaymentSuccess']);
Route::get('/basecamp/payment/cancel', [BasecampBillingController::class, 'handlePaymentCancel']);

// Public endpoint - no authentication required
Route::get('/basecamp/stripe-config', [BasecampBillingController::class, 'getStripeConfig']);

Route::post('/change-password', [ForgotController::class, 'changePassword']);
Route::post('/forgot-password', [ForgotController::class, 'sendResetLink']);
Route::post('/reset-password', [ForgotController::class, 'reset']);
Route::post('/user/apply-leave', [UserLeaveController::class, 'userApplyLeave']);
Route::post('/user-change-leave-status', [UserLeaveController::class, 'userChangeLeaveStatus']);

Route::get('/reflections', [ReflectionApiController::class, 'list']);
Route::post('/reflections', [ReflectionApiController::class, 'create']);
Route::get('/reflections/{reflection}/chats', [ReflectionApiController::class, 'chats']);
Route::post('/reflections/{reflection}/chats', [ReflectionApiController::class, 'sendChat']);
Route::post('/reflections/{reflection}/mark-read', [ReflectionApiController::class, 'markAsRead']);

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
