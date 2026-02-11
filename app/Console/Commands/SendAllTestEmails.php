<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
use App\Models\Reflection;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;
use App\Mail\ForgotPasswordOtpMail;
use Illuminate\Support\Facades\DB;
use App\Mail\PaymentReminderMail;
use App\Mail\PaymentFailedMail;
use App\Mail\PaymentConfirmationMail;
use App\Mail\FinalWarningMail;
use App\Mail\AccountSuspendedMail;
use App\Mail\AccountReactivatedMail;
use App\Mail\ReflectionResolvedMail;
use App\Mail\ReflectionAdminMessageMail;
use App\Mail\EmailVerificationMail;
use App\Mail\ActivationSuccessMail;
use Carbon\Carbon;

class SendAllTestEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:send-all-emails {email=mousam@chetaru.com}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all email templates to a test email address for testing purposes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $testEmail = $this->argument('email');
        $this->info("Sending all test emails to: {$testEmail}");

        // Create a dummy user object for testing
        $dummyUser = new User();
        $dummyUser->setRawAttributes([
            'id' => 1,
            'first_name' => 'Test',
            'name' => 'Test User',
            'email' => $testEmail,
        ]);

        // Create dummy Invoice
        $dummyInvoice = new Invoice();
        $dummyInvoice->setRawAttributes([
            'id' => 1,
            'user_id' => 1,
            'subscription_id' => 1,
            'invoice_number' => 'INV-TEST-001',
            'total_amount' => 29.99,
            'status' => 'unpaid',
        ]);
        // Add currency as dynamic property since Mail classes access it
        $dummyInvoice->currency = 'gbp';

        // Create dummy SubscriptionRecord
        $dummySubscription = new SubscriptionRecord();
        $dummySubscription->setRawAttributes([
            'id' => 1,
            'user_id' => 1,
            'status' => 'active',
            'current_period_start' => Carbon::now(),
            'current_period_end' => Carbon::now()->addMonth(),
            'next_billing_date' => Carbon::now()->addMonth(),
        ]);
        // Set relationship for invoice
        $dummyInvoice->setRelation('subscription', $dummySubscription);

        $sentCount = 0;
        $errors = [];

        try {
            // 1. Forgot Password OTP
            $this->info("Sending: Forgot Password OTP");
            Mail::to($testEmail)->send(new ForgotPasswordOtpMail('123456', 'Test'));
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Forgot Password OTP: " . $e->getMessage();
        }

        try {
            // 2. Payment Reminder
            $this->info("Sending: Payment Reminder");
            Mail::to($testEmail)->send(new PaymentReminderMail($dummyInvoice, $dummySubscription, $dummyUser, 3));
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Payment Reminder: " . $e->getMessage();
        }

        try {
            // 3. Payment Failed
            $this->info("Sending: Payment Failed");
            Mail::to($testEmail)->send(new PaymentFailedMail($dummyInvoice, $dummySubscription, $dummyUser, 'Insufficient funds', 1));
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Payment Failed: " . $e->getMessage();
        }

        try {
            // 4. Payment Confirmation
            $this->info("Sending: Payment Confirmation");
            // PaymentRecord might not exist, use null
            Mail::to($testEmail)->send(new PaymentConfirmationMail($dummyInvoice, null, $dummyUser, true));
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Payment Confirmation: " . $e->getMessage();
        }

        try {
            // 5. Final Warning
            $this->info("Sending: Final Warning");
            Mail::to($testEmail)->send(new FinalWarningMail($dummyInvoice, $dummySubscription, $dummyUser, 2));
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Final Warning: " . $e->getMessage();
        }

        try {
            // 6. Account Suspended
            $this->info("Sending: Account Suspended");
            $dummySubscription->setRawAttributes(array_merge($dummySubscription->getAttributes(), [
                'suspended_at' => Carbon::now(),
            ]));
            Mail::to($testEmail)->send(new AccountSuspendedMail($dummySubscription, $dummyUser, 29.99));
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Account Suspended: " . $e->getMessage();
        }

        try {
            // 7. Account Reactivated
            $this->info("Sending: Account Reactivated");
            Mail::to($testEmail)->send(new AccountReactivatedMail($dummySubscription, $dummyUser));
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Account Reactivated: " . $e->getMessage();
        }

        try {
            // 8. Email Verification
            $this->info("Sending: Email Verification");
            $verificationUrl = url('/verify-user/123?signature=test');
            Mail::to($testEmail)->send(new EmailVerificationMail($dummyUser, $verificationUrl));
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Email Verification: " . $e->getMessage();
        }

        try {
            // 9. Activation Success
            $this->info("Sending: Activation Success");
            Mail::to($testEmail)->send(new ActivationSuccessMail($dummyUser));
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Activation Success: " . $e->getMessage();
        }

        // Emails using Mail::send() directly
        try {
            // 10. Verify User Inline
            $this->info("Sending: Verify User Inline");
            $verificationUrl = url('/verify-user/123?signature=test');
            Mail::send('emails.verify-user-inline', [
                'user' => $dummyUser,
                'verificationUrl' => $verificationUrl,
            ], function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Welcome to Tribe365® Basecamp - Verify Your Account');
            });
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Verify User Inline: " . $e->getMessage();
        }

        try {
            // 11. Reset Password
            $this->info("Sending: Reset Password");
            $resetUrl = url('/reset-password?token=test123&email=' . $testEmail);
            Mail::send('emails.reset-password', [
                'user' => $dummyUser,
                'url' => $resetUrl,
            ], function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Tribe365® Password Reset');
            });
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Reset Password: " . $e->getMessage();
        }

        try {
            // 12. Basecamp Reset Password
            $this->info("Sending: Basecamp Reset Password");
            $resetUrl = url('/reset-password?token=test123&email=' . $testEmail);
            Mail::send('emails.basecamp-reset-password', [
                'user' => $dummyUser,
                'url' => $resetUrl,
            ], function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Tribe365® Basecamp Password Reset Request');
            });
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Basecamp Reset Password: " . $e->getMessage();
        }

        try {
            // 13. Staff Reset Password
            $this->info("Sending: Staff Reset Password");
            $resetUrl = url('/reset-password?token=test123&email=' . $testEmail);
            Mail::send('emails.staff_reset_password', [
                'user' => $dummyUser,
                'url' => $resetUrl,
                'organisation' => 'Test Organisation',
            ], function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Tribe365® Password Reset');
            });
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Staff Reset Password: " . $e->getMessage();
        }

        try {
            // 14. Welcome User
            $this->info("Sending: Welcome User");
            Mail::send('emails.welcome-user', [
                'user' => $dummyUser,
            ], function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Welcome to Tribe365® Basecamp');
            });
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Welcome User: " . $e->getMessage();
        }

        try {
            // 15. Custom Reset Password
            $this->info("Sending: Custom Reset Password");
            $resetUrl = url('/reset-password?token=test123&email=' . $testEmail);
            Mail::send('emails.custom-reset-password', [
                'userFullName' => 'Test User',
                'inviterName' => 'Admin User',
                'orgName' => 'Test Organisation',
                'resetUrl' => $resetUrl,
            ], function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Tribe365® App Password Setup Request');
            });
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Custom Reset Password: " . $e->getMessage();
        }

        try {
            // 16. Custom Reset (Welcome)
            $this->info("Sending: Custom Reset Welcome");
            $resetUrl = url('/reset-password?token=test123&email=' . $testEmail);
            Mail::send('emails.custom-reset', [
                'user' => $dummyUser,
                'inviterName' => 'Admin User',
                'url' => $resetUrl,
            ], function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Welcome to Tribe365®');
            });
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Custom Reset Welcome: " . $e->getMessage();
        }

        try {
            // 17. Weekly Summary
            $this->info("Sending: Weekly Summary");
            Mail::send('emails.weekly-summary', [
                'user' => $dummyUser,
                'weekLabel' => 'Week 48 (Nov 20 - Nov 26)',
                'engagementText' => '• You were positive 4/7 days.\n• Your highest mood score was 8/10.',
                'summaryText' => 'Keep up the great work!',
            ], function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Tribe365® Weekly Summary');
            });
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Weekly Summary: " . $e->getMessage();
        }

        try {
            // 18. Weekly Report
            $this->info("Sending: Weekly Report");
            Mail::send('emails.weekly-report', [
                'user' => $dummyUser,
                'chartUrl' => null,
            ], function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Team Sentiment Index');
            });
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Weekly Report: " . $e->getMessage();
        }

        try {
            // 19. Monthly Report
            $this->info("Sending: Monthly Report");
            Mail::send('emails.monthly-report', [
                'user' => $dummyUser,
                'month' => 'November 2024',
            ], function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Tribe365® Basecamp Monthly Report');
            });
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Monthly Report: " . $e->getMessage();
        }

        try {
            // 20. Sentiment Reminder
            $this->info("Sending: Sentiment Reminder");
            Mail::send('emails.sentiment-reminder', [
                'user' => $dummyUser,
                'week' => 'Week 48',
            ], function ($message) use ($testEmail) {
                $message->to($testEmail)
                    ->subject('Weekly Tribe365® Basecamp Sentiment Summary');
            });
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Sentiment Reminder: " . $e->getMessage();
        }

        // Reflection emails need a reflection object
        try {
            // 21. Reflection Resolved
            $this->info("Sending: Reflection Resolved");
            $dummyReflection = new Reflection();
            $dummyReflection->setRawAttributes([
                'id' => 1,
                'userId' => 1,
                'topic' => 'Test Reflection Topic',
                'status' => 'Resolved',
            ]);
            $dummyReflection->setRelation('user', $dummyUser);
            Mail::to($testEmail)->send(new ReflectionResolvedMail($dummyReflection, 'Resolved'));
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Reflection Resolved: " . $e->getMessage();
        }

        try {
            // 22. Reflection Admin Message
            $this->info("Sending: Reflection Admin Message");
            $dummyReflection = new Reflection();
            $dummyReflection->setRawAttributes([
                'id' => 1,
                'userId' => 1,
                'topic' => 'Test Reflection Topic',
            ]);
            $dummyReflection->setRelation('user', $dummyUser);
            $dummyAdmin = new \stdClass();
            $dummyAdmin->name = 'Admin User';
            Mail::to($testEmail)->send(new ReflectionAdminMessageMail($dummyReflection, $dummyAdmin));
            $sentCount++;
        } catch (\Exception $e) {
            $errors[] = "Reflection Admin Message: " . $e->getMessage();
        }

        $this->info("\n==========================================");
        $this->info("✅ Successfully sent {$sentCount} emails to {$testEmail}");
        
        if (!empty($errors)) {
            $this->error("\n❌ Errors occurred:");
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
        }
        
        $this->info("==========================================\n");
        
        return 0;
    }
}
