<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use App\Models\User;
use App\Models\Reflection;
use App\Models\Invoice;
use App\Models\SubscriptionRecord;
use App\Services\OneSignalService;
use Carbon\Carbon;

class SendAllTestEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:send-all-emails {email=mousam@chetaru.com} {--save : Save emails as HTML files instead of sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send all email templates to a test email address for testing purposes. Use --save to save as HTML files instead.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $testEmail = $this->argument('email');
        $saveMode = $this->option('save');
        
        if ($saveMode) {
            $saveDir = storage_path('app/test-emails');
            if (!File::exists($saveDir)) {
                File::makeDirectory($saveDir, 0755, true);
            }
            $this->info("Saving all test emails as HTML files to: {$saveDir}");
        } else {
            $this->info("Sending all test emails to: {$testEmail}");
        }

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
        $oneSignal = new OneSignalService();

        // Helper function to send or save email via OneSignal
        $sendOrSaveEmail = function($emailHtml, $subject, $filename) use ($testEmail, $saveMode, $saveDir, $oneSignal, &$sentCount, &$errors) {
            try {
                if ($saveMode) {
                    File::put($saveDir . '/' . $filename . '.html', $emailHtml);
                    $this->info("Saved: {$filename}.html");
                    $sentCount++;
                } else {
                    $result = $oneSignal->sendEmailMessage($testEmail, $subject, $emailHtml);
                    if ($result !== false) {
                        $this->info("Sent: {$subject}");
                        $sentCount++;
                    } else {
                        $errors[] = "{$subject}: OneSignal send failed";
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "{$subject}: " . $e->getMessage();
            }
        };

        try {
            // 1. Forgot Password OTP
            $this->info("Processing: Forgot Password OTP");
            $emailHtml = view('emails.forgot-password-otp', [
                'otp' => '123456',
                'first_name' => 'Test',
                'user' => $dummyUser,
            ])->render();
            $sendOrSaveEmail($emailHtml, 'Basecamp Forgot Password OTP', '01-forgot-password-otp');
        } catch (\Exception $e) {
            $errors[] = "Forgot Password OTP: " . $e->getMessage();
        }

        try {
            // 2. Payment Reminder
            $this->info("Processing: Payment Reminder");
            $emailHtml = view('emails.payment-reminder-mail', ['user' => $dummyUser])->render();
            $sendOrSaveEmail($emailHtml, 'Payment Reminder - 3 Days Remaining - Tribe365', '02-payment-reminder');
        } catch (\Exception $e) {
            $errors[] = "Payment Reminder: " . $e->getMessage();
        }

        try {
            // 3. Payment Failed
            $this->info("Processing: Payment Failed");
            $emailHtml = view('emails.payment-failed-mail', ['user' => $dummyUser])->render();
            $sendOrSaveEmail($emailHtml, 'Payment Failed - Action Required - Tribe365', '03-payment-failed');
        } catch (\Exception $e) {
            $errors[] = "Payment Failed: " . $e->getMessage();
        }

        try {
            // 4. Payment Confirmation
            $this->info("Processing: Payment Confirmation");
            $emailHtml = view('emails.payment-confirmation-mail', [
                'user' => $dummyUser,
                'invoice' => $dummyInvoice,
                'payment' => null,
                'isBasecamp' => true,
            ])->render();
            $sendOrSaveEmail($emailHtml, 'Payment Confirmation - Tribe365', '04-payment-confirmation');
        } catch (\Exception $e) {
            $errors[] = "Payment Confirmation: " . $e->getMessage();
        }

        try {
            // 5. Final Warning
            $this->info("Processing: Final Warning");
            $suspensionDate = Carbon::now()->addDays(2)->format('M d, Y');
            $emailHtml = view('emails.final-warning-mail', [
                'user' => $dummyUser,
                'suspensionDate' => $suspensionDate,
            ])->render();
            $sendOrSaveEmail($emailHtml, 'Final Warning - Account Suspension Imminent - Tribe365', '05-final-warning');
        } catch (\Exception $e) {
            $errors[] = "Final Warning: " . $e->getMessage();
        }

        try {
            // 6. Account Suspended
            $this->info("Processing: Account Suspended");
            $suspensionDate = Carbon::now()->format('M d, Y');
            $deletionDate = Carbon::now()->addDays(30)->format('M d, Y');
            $emailHtml = view('emails.account-suspended-mail', [
                'user' => $dummyUser,
                'suspensionDate' => $suspensionDate,
                'deletionDate' => $deletionDate,
            ])->render();
            $sendOrSaveEmail($emailHtml, 'Account Suspended - Action Required - Tribe365', '06-account-suspended');
        } catch (\Exception $e) {
            $errors[] = "Account Suspended: " . $e->getMessage();
        }

        try {
            // 7. Account Reactivated
            $this->info("Processing: Account Reactivated");
            $emailHtml = view('emails.account-reactivated-mail', ['user' => $dummyUser])->render();
            $sendOrSaveEmail($emailHtml, 'Welcome Back! Your Account Has Been Reactivated - Tribe365', '07-account-reactivated');
        } catch (\Exception $e) {
            $errors[] = "Account Reactivated: " . $e->getMessage();
        }

        try {
            // 8. Email Verification
            $this->info("Processing: Email Verification");
            $verificationUrl = url('/verify-user/123?signature=test');
            $emailHtml = view('emails.email-verification-mail', [
                'user' => $dummyUser,
                'verificationUrl' => $verificationUrl,
            ])->render();
            $sendOrSaveEmail($emailHtml, 'Verify Your Email Address - Tribe365', '08-email-verification');
        } catch (\Exception $e) {
            $errors[] = "Email Verification: " . $e->getMessage();
        }

        try {
            // 9. Activation Success
            $this->info("Processing: Activation Success");
            $emailHtml = view('emails.activation-message', ['user' => $dummyUser])->render();
            $sendOrSaveEmail($emailHtml, 'Your Tribe Account is Now Active!', '09-activation-success');
        } catch (\Exception $e) {
            $errors[] = "Activation Success: " . $e->getMessage();
        }

        // Emails using Mail::send() directly
        try {
            // 10. Verify User Inline
            $this->info("Processing: Verify User Inline");
            $verificationUrl = url('/verify-user/123?signature=test');
            $viewData = ['user' => $dummyUser, 'verificationUrl' => $verificationUrl];
            $html = view('emails.verify-user-inline', $viewData)->render();
            if ($saveMode) {
                File::put($saveDir . '/10-verify-user-inline.html', $html);
                $this->info("Saved: 10-verify-user-inline.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Welcome to Tribe365® Basecamp - Verify Your Account', $html);
                if ($result !== false) {
                    $this->info("Sent: Verify User Inline");
                    $sentCount++;
                } else {
                    $errors[] = "Verify User Inline: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Verify User Inline: " . $e->getMessage();
        }

        try {
            // 11. Reset Password
            $this->info("Processing: Reset Password");
            $resetUrl = url('/reset-password?token=test123&email=' . $testEmail);
            $viewData = ['user' => $dummyUser, 'url' => $resetUrl];
            $html = view('emails.reset-password', $viewData)->render();
            if ($saveMode) {
                File::put($saveDir . '/11-reset-password.html', $html);
                $this->info("Saved: 11-reset-password.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Tribe365® Password Reset', $html);
                if ($result !== false) {
                    $this->info("Sent: Reset Password");
                    $sentCount++;
                } else {
                    $errors[] = "Reset Password: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Reset Password: " . $e->getMessage();
        }

        try {
            // 12. Basecamp Reset Password
            $this->info("Processing: Basecamp Reset Password");
            $resetUrl = url('/reset-password?token=test123&email=' . $testEmail);
            $viewData = ['user' => $dummyUser, 'url' => $resetUrl];
            $html = view('emails.basecamp-reset-password', $viewData)->render();
            if ($saveMode) {
                File::put($saveDir . '/12-basecamp-reset-password.html', $html);
                $this->info("Saved: 12-basecamp-reset-password.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Tribe365® Basecamp Password Reset Request', $html);
                if ($result !== false) {
                    $this->info("Sent: Basecamp Reset Password");
                    $sentCount++;
                } else {
                    $errors[] = "Basecamp Reset Password: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Basecamp Reset Password: " . $e->getMessage();
        }

        try {
            // 13. Staff Reset Password
            $this->info("Processing: Staff Reset Password");
            $resetUrl = url('/reset-password?token=test123&email=' . $testEmail);
            $viewData = ['user' => $dummyUser, 'url' => $resetUrl, 'organisation' => 'Test Organisation'];
            $html = view('emails.staff_reset_password', $viewData)->render();
            if ($saveMode) {
                File::put($saveDir . '/13-staff-reset-password.html', $html);
                $this->info("Saved: 13-staff-reset-password.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Tribe365® Password Reset', $html);
                if ($result !== false) {
                    $this->info("Sent: Staff Reset Password");
                    $sentCount++;
                } else {
                    $errors[] = "Staff Reset Password: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Staff Reset Password: " . $e->getMessage();
        }

        try {
            // 14. Welcome User
            $this->info("Processing: Welcome User");
            $viewData = ['user' => $dummyUser];
            $html = view('emails.welcome-user', $viewData)->render();
            if ($saveMode) {
                File::put($saveDir . '/14-welcome-user.html', $html);
                $this->info("Saved: 14-welcome-user.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Welcome to Tribe365® Basecamp', $html);
                if ($result !== false) {
                    $this->info("Sent: Welcome User");
                    $sentCount++;
                } else {
                    $errors[] = "Welcome User: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Welcome User: " . $e->getMessage();
        }

        try {
            // 15. Custom Reset Password
            $this->info("Processing: Custom Reset Password");
            $resetUrl = url('/reset-password?token=test123&email=' . $testEmail);
            $viewData = [
                'userFullName' => 'Test User',
                'inviterName' => 'Admin User',
                'orgName' => 'Test Organisation',
                'resetUrl' => $resetUrl,
            ];
            $html = view('emails.custom-reset-password', $viewData)->render();
            if ($saveMode) {
                File::put($saveDir . '/15-custom-reset-password.html', $html);
                $this->info("Saved: 15-custom-reset-password.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Tribe365® App Password Setup Request', $html);
                if ($result !== false) {
                    $this->info("Sent: Custom Reset Password");
                    $sentCount++;
                } else {
                    $errors[] = "Custom Reset Password: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Custom Reset Password: " . $e->getMessage();
        }

        try {
            // 16. Custom Reset (Welcome)
            $this->info("Processing: Custom Reset Welcome");
            $resetUrl = url('/reset-password?token=test123&email=' . $testEmail);
            $viewData = ['user' => $dummyUser, 'inviterName' => 'Admin User', 'url' => $resetUrl];
            $html = view('emails.custom-reset', $viewData)->render();
            if ($saveMode) {
                File::put($saveDir . '/16-custom-reset-welcome.html', $html);
                $this->info("Saved: 16-custom-reset-welcome.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Welcome to Tribe365®', $html);
                if ($result !== false) {
                    $this->info("Sent: Custom Reset Welcome");
                    $sentCount++;
                } else {
                    $errors[] = "Custom Reset Welcome: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Custom Reset Welcome: " . $e->getMessage();
        }

        try {
            // 17. Weekly Summary
            $this->info("Processing: Weekly Summary");
            $viewData = [
                'user' => $dummyUser,
                'weekLabel' => 'Week 48 (Nov 20 - Nov 26)',
                'engagementText' => '• You were positive 4/7 days.\n• Your highest mood score was 8/10.',
                'summaryText' => 'Keep up the great work!',
            ];
            $html = view('emails.weekly-summary', $viewData)->render();
            if ($saveMode) {
                File::put($saveDir . '/17-weekly-summary.html', $html);
                $this->info("Saved: 17-weekly-summary.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Tribe365® Weekly Summary', $html);
                if ($result !== false) {
                    $this->info("Sent: Weekly Summary");
                    $sentCount++;
                } else {
                    $errors[] = "Weekly Summary: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Weekly Summary: " . $e->getMessage();
        }

        try {
            // 18. Weekly Report
            $this->info("Processing: Weekly Report");
            $viewData = ['user' => $dummyUser, 'chartUrl' => null];
            $html = view('emails.weekly-report', $viewData)->render();
            if ($saveMode) {
                File::put($saveDir . '/18-weekly-report.html', $html);
                $this->info("Saved: 18-weekly-report.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Team Sentiment Index', $html);
                if ($result !== false) {
                    $this->info("Sent: Weekly Report");
                    $sentCount++;
                } else {
                    $errors[] = "Weekly Report: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Weekly Report: " . $e->getMessage();
        }

        try {
            // 19. Monthly Report
            $this->info("Processing: Monthly Report");
            $viewData = ['user' => $dummyUser, 'month' => 'November 2024'];
            $html = view('emails.monthly-report', $viewData)->render();
            if ($saveMode) {
                File::put($saveDir . '/19-monthly-report.html', $html);
                $this->info("Saved: 19-monthly-report.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Tribe365® Basecamp Monthly Report', $html);
                if ($result !== false) {
                    $this->info("Sent: Monthly Report");
                    $sentCount++;
                } else {
                    $errors[] = "Monthly Report: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Monthly Report: " . $e->getMessage();
        }

        try {
            // 20. Sentiment Reminder
            $this->info("Processing: Sentiment Reminder");
            $viewData = ['user' => $dummyUser, 'week' => 'Week 48'];
            $html = view('emails.sentiment-reminder', $viewData)->render();
            if ($saveMode) {
                File::put($saveDir . '/20-sentiment-reminder.html', $html);
                $this->info("Saved: 20-sentiment-reminder.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Weekly Tribe365® Basecamp Sentiment Summary', $html);
                if ($result !== false) {
                    $this->info("Sent: Sentiment Reminder");
                    $sentCount++;
                } else {
                    $errors[] = "Sentiment Reminder: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Sentiment Reminder: " . $e->getMessage();
        }

        // Reflection emails need a reflection object
        try {
            // 21. Reflection Resolved
            $this->info("Processing: Reflection Resolved");
            $dummyReflection = new Reflection();
            $dummyReflection->setRawAttributes([
                'id' => 1,
                'userId' => 1,
                'topic' => 'Test Reflection Topic',
                'status' => 'Resolved',
            ]);
            $dummyReflection->setRelation('user', $dummyUser);
            $html = view('emails.reflection-resolved', [
                'reflection' => $dummyReflection,
                'status' => 'Resolved',
            ])->render();
            if ($saveMode) {
                File::put($saveDir . '/21-reflection-resolved.html', $html);
                $this->info("Saved: 21-reflection-resolved.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Your Reflection has been Resolved', $html);
                if ($result !== false) {
                    $this->info("Sent: Reflection Resolved");
                    $sentCount++;
                } else {
                    $errors[] = "Reflection Resolved: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Reflection Resolved: " . $e->getMessage();
        }

        try {
            // 22. Reflection Admin Message
            $this->info("Processing: Reflection Admin Message");
            $dummyReflection = new Reflection();
            $dummyReflection->setRawAttributes([
                'id' => 1,
                'userId' => 1,
                'topic' => 'Test Reflection Topic',
            ]);
            $dummyReflection->setRelation('user', $dummyUser);
            $dummyAdmin = new \stdClass();
            $dummyAdmin->name = 'Admin User';
            $html = view('emails.reflection-admin-message', [
                'reflection' => $dummyReflection,
                'admin' => $dummyAdmin,
            ])->render();
            if ($saveMode) {
                File::put($saveDir . '/22-reflection-admin-message.html', $html);
                $this->info("Saved: 22-reflection-admin-message.html");
                $sentCount++;
            } else {
                $result = $oneSignal->sendEmailMessage($testEmail, 'Admin Started Conversation About Your Reflection', $html);
                if ($result !== false) {
                    $this->info("Sent: Reflection Admin Message");
                    $sentCount++;
                } else {
                    $errors[] = "Reflection Admin Message: OneSignal send failed";
                }
            }
        } catch (\Exception $e) {
            $errors[] = "Reflection Admin Message: " . $e->getMessage();
        }

        $this->info("\n==========================================");
        if ($saveMode) {
            $this->info("✅ Successfully saved {$sentCount} email templates to: {$saveDir}");
        } else {
            $this->info("✅ Successfully sent {$sentCount} emails to {$testEmail}");
        }
        
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
