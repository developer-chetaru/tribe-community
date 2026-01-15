<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\View as ViewFacade;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;

class GenerateEmailTemplatesPdf extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:generate-pdfs {--output=storage/app/email-templates-pdf} {--combined : Generate all templates in a single PDF file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate PDF files for all email templates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $outputDir = $this->option('output');
        $combined = $this->option('combined');
        
        // Register mail component namespace for Laravel mail components
        $mailViewsPath = resource_path('views/vendor/mail');
        if (File::exists($mailViewsPath)) {
            // Add namespace for mail components
            $finder = ViewFacade::getFinder();
            $finder->addNamespace('mail', $mailViewsPath);
            
            // Also register html subdirectory
            $htmlPath = $mailViewsPath . '/html';
            if (File::exists($htmlPath)) {
                $finder->addLocation($htmlPath);
            }
        }
        
        // Create output directory if it doesn't exist
        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
            $this->info("Created directory: {$outputDir}");
        }

        if ($combined) {
            return $this->generateCombinedPdf($outputDir);
        }

        $this->info("Generating PDFs for all email templates...");
        $this->newLine();

        $templates = $this->getEmailTemplates();
        $generated = 0;
        $failed = 0;

        foreach ($templates as $templateName => $templateData) {
            try {
                $this->info("Processing: {$templateName}...");
                
                // Render the view
                $html = View::make($templateData['view'], $templateData['data'])->render();
                
                // Generate PDF
                $pdf = Pdf::loadHTML($html);
                $pdf->setPaper('a4', 'portrait');
                
                // Save PDF
                $filename = str_replace('.blade.php', '', $templateName) . '.pdf';
                $filepath = $outputDir . '/' . $filename;
                $pdf->save($filepath);
                
                $this->info("  ✓ Generated: {$filename}");
                $generated++;
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$templateName} - {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Generated: {$generated}");
        $this->info("  Failed: {$failed}");
        $this->info("  Output directory: {$outputDir}");
        
        return Command::SUCCESS;
    }

    /**
     * Generate a single combined PDF with all email templates
     */
    private function generateCombinedPdf($outputDir)
    {
        $this->info("Generating combined PDF with all email templates...");
        $this->newLine();

        $templates = $this->getEmailTemplates();
        $combinedHtml = '';
        $processed = 0;
        $failed = 0;

        // Add CSS for page breaks and styling
        $combinedHtml .= '<style>
            @page {
                margin: 20mm;
            }
            .email-template {
                page-break-after: always;
                margin-bottom: 50px;
                padding: 20px;
                border-bottom: 3px solid #EB1C24;
            }
            .email-template:last-child {
                page-break-after: auto;
            }
            .template-title {
                background: #EB1C24;
                color: white;
                padding: 15px;
                margin: -20px -20px 20px -20px;
                font-size: 18px;
                font-weight: bold;
                text-transform: uppercase;
            }
            body {
                font-family: Arial, sans-serif;
            }
        </style>';

        foreach ($templates as $templateName => $templateData) {
            try {
                $this->info("Processing: {$templateName}...");
                
                // Render the view
                $html = View::make($templateData['view'], $templateData['data'])->render();
                
                // Add template wrapper with title
                $templateTitle = str_replace(['-', '_'], ' ', $templateName);
                $templateTitle = ucwords($templateTitle);
                
                $combinedHtml .= '<div class="email-template">';
                $combinedHtml .= '<div class="template-title">' . htmlspecialchars($templateTitle) . '</div>';
                $combinedHtml .= $html;
                $combinedHtml .= '</div>';
                
                $processed++;
            } catch (\Exception $e) {
                $this->error("  ✗ Failed: {$templateName} - {$e->getMessage()}");
                $failed++;
            }
        }

        if ($processed > 0) {
            try {
                // Generate combined PDF
                $pdf = Pdf::loadHTML($combinedHtml);
                $pdf->setPaper('a4', 'portrait');
                $pdf->setOption('enable-local-file-access', true);
                
                // Save combined PDF
                $filepath = $outputDir . '/all-email-templates-combined.pdf';
                $pdf->save($filepath);
                
                $this->newLine();
                $this->info("✓ Combined PDF generated successfully!");
                $this->info("  File: {$filepath}");
                $this->info("  Templates included: {$processed}");
                if ($failed > 0) {
                    $this->warn("  Failed templates: {$failed}");
                }
            } catch (\Exception $e) {
                $this->error("Failed to generate combined PDF: {$e->getMessage()}");
                return Command::FAILURE;
            }
        } else {
            $this->error("No templates were processed successfully.");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Get all email templates with their dummy data
     */
    private function getEmailTemplates(): array
    {
        $now = Carbon::now();
        
        // Create dummy user
        $dummyUser = (object)[
            'id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'name' => 'John Doe',
            'status' => 'active_verified',
            'email_verified_at' => $now,
        ];

        // Create dummy organisation
        $dummyOrg = (object)[
            'id' => 1,
            'name' => 'Example Organisation',
            'admin_email' => 'admin@example.com',
        ];

        // Create dummy invoice
        $dummyInvoice = (object)[
            'id' => 1,
            'invoice_number' => 'INV-2024-001',
            'invoice_date' => $now,
            'due_date' => $now->copy()->addDays(7),
            'paid_date' => $now, // Add paid_date for payment-confirmation-mail
            'total_amount' => 120.00,
            'currency' => 'gbp',
            'status' => 'unpaid',
            'subscription_id' => 1,
            'user_id' => 1,
            'organisation_id' => 1,
        ];

        // Create dummy subscription
        $dummySubscription = (object)[
            'id' => 1,
            'tier' => 'standard',
            'user_count' => 10,
            'status' => 'active',
            'current_period_start' => $now->copy()->startOfMonth(),
            'current_period_end' => $now->copy()->endOfMonth(),
            'next_billing_date' => $now->copy()->addMonth()->startOfMonth(),
            'suspended_at' => null,
            'user_id' => 1,
            'organisation_id' => 1,
        ];

        // Create dummy payment record
        $dummyPayment = (object)[
            'id' => 1,
            'amount' => 120.00,
            'status' => 'succeeded',
            'created_at' => $now,
        ];

        // Create dummy reflection with user relationship
        $dummyReflection = (object)[
            'id' => 1,
            'topic' => 'Team Collaboration',
            'message' => 'This is a sample reflection message about team collaboration.',
            'status' => 'resolved',
            'userId' => 1,
            'orgId' => 1,
            'user' => $dummyUser, // Add user relationship for reflection-resolved template
        ];

        // Create dummy admin user
        $dummyAdmin = (object)[
            'id' => 2,
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@tribe365.co',
            'name' => 'Admin User',
        ];

        return [
            // Mail Classes
            'verify-user' => [
                'view' => 'emails.verify-user',
                'data' => [
                    'user' => $dummyUser,
                    'verificationUrl' => url('/verify/1?signature=example'),
                ],
            ],
            'activation-message' => [
                'view' => 'emails.activation-message',
                'data' => [
                    'user' => $dummyUser,
                ],
            ],
            'forgot-password-otp' => [
                'view' => 'emails.forgot-password-otp',
                'data' => [
                    'otp' => '123456',
                    'first_name' => 'John',
                ],
            ],
            'payment-reminder-mail' => [
                'view' => 'emails.payment-reminder-mail',
                'data' => [
                    'invoice' => $dummyInvoice,
                    'subscription' => $dummySubscription,
                    'user' => $dummyUser,
                    'amount' => '£120.00',
                    'daysRemaining' => 3,
                    'paymentUrl' => url('/billing'),
                ],
            ],
            'payment-failed-mail' => [
                'view' => 'emails.payment-failed-mail',
                'data' => [
                    'invoice' => $dummyInvoice,
                    'subscription' => $dummySubscription,
                    'user' => $dummyUser,
                    'amount' => '£120.00',
                    'failureReason' => 'Insufficient funds',
                    'gracePeriodDays' => 5,
                    'dayNumber' => 1,
                    'updatePaymentUrl' => url('/billing'),
                ],
            ],
            'payment-confirmation-mail' => [
                'view' => 'emails.payment-confirmation-mail',
                'data' => [
                    'invoice' => $dummyInvoice,
                    'payment' => (object)array_merge((array)$dummyPayment, ['transaction_id' => 'txn_1234567890']), // Add transaction_id
                    'user' => $dummyUser,
                    'amount' => '£120.00',
                    'isBasecamp' => false,
                    'billingPeriod' => $now->format('M d, Y') . ' - ' . $now->copy()->addMonth()->format('M d, Y'),
                ],
            ],
            'final-warning-mail' => [
                'view' => 'emails.final-warning-mail',
                'data' => [
                    'invoice' => $dummyInvoice,
                    'subscription' => $dummySubscription,
                    'user' => $dummyUser,
                    'amount' => '£120.00',
                    'daysRemaining' => 2,
                    'suspensionDate' => $now->copy()->addDays(2)->format('M d, Y'),
                    'paymentUrl' => url('/billing'),
                ],
            ],
            'account-suspended-mail' => [
                'view' => 'emails.account-suspended-mail',
                'data' => [
                    'subscription' => $dummySubscription,
                    'user' => $dummyUser,
                    'suspensionDate' => $now->format('M d, Y'),
                    'outstandingAmount' => '£120.00',
                    'dataRetentionDays' => 30,
                    'deletionDate' => $now->copy()->addDays(30)->format('M d, Y'),
                    'reactivationUrl' => url('/billing/reactivate'),
                ],
            ],
            'account-reactivated-mail' => [
                'view' => 'emails.account-reactivated-mail',
                'data' => [
                    'subscription' => $dummySubscription,
                    'user' => $dummyUser,
                    'nextBillingDate' => $now->copy()->addMonth()->format('M d, Y'),
                    'dashboardUrl' => url('/dashboard'),
                ],
            ],
            'reflection-resolved' => [
                'view' => 'emails.reflection-resolved',
                'data' => [
                    'reflection' => $dummyReflection,
                    'admin' => $dummyAdmin,
                ],
            ],
            'reflection-admin-message' => [
                'view' => 'emails.reflection-admin-message',
                'data' => [
                    'reflection' => $dummyReflection,
                    'admin' => $dummyAdmin,
                ],
            ],
            'email-verification-mail' => [
                'view' => 'emails.email-verification-mail',
                'data' => [
                    'user' => $dummyUser,
                    'verificationUrl' => url('/verify/1?signature=example'),
                ],
            ],
            'test-summary' => [
                'view' => 'emails.test-summary',
                'data' => [
                    'user' => $dummyUser,
                    'weekLabel' => 'Week of ' . $now->format('M d, Y'),
                    'summaryText' => 'This is a sample weekly summary text.',
                    'logoCid' => 'cid:logo-tribe',
                ],
            ],
            
            // Direct Mail::send templates
            'weekly-report' => [
                'view' => 'emails.weekly-report',
                'data' => [
                    'user' => $dummyUser,
                    'organisation' => $dummyOrg,
                    'chartUrl' => 'https://quickchart.io/chart?c=' . urlencode(json_encode([
                        'type' => 'line',
                        'data' => [
                            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
                            'datasets' => [[
                                'label' => 'Happy Index',
                                'data' => [7, 8, 6, 9, 8],
                            ]],
                        ],
                    ])),
                ],
            ],
            'monthly-report' => [
                'view' => 'emails.monthly-report',
                'data' => [
                    'user' => $dummyUser,
                    'organisation' => $dummyOrg,
                    'chartUrl' => 'https://quickchart.io/chart?c=' . urlencode(json_encode([
                        'type' => 'bar',
                        'data' => [
                            'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                            'datasets' => [[
                                'label' => 'Happy Index',
                                'data' => [7.5, 8.0, 7.8, 8.2],
                            ]],
                        ],
                    ])),
                ],
            ],
            // 'sentiment-reminder' - Skipped due to syntax error in template (uses invalid Blade syntax: name | default: "there")
            // Note: These templates use Laravel's @component('mail::message') which requires
            // the Mail facade to render properly. They cannot be rendered directly via View::make.
            // To generate PDFs for these, you would need to create Mailable classes and use Mail::to()->send()
            // 'custom-reset' - Uses @component('mail::message') - requires Mail facade
            // 'team-invitation' - Uses @component('mail::message') - requires Mail facade
            
            // Notification templates
            'custom-reset-password' => [
                'view' => 'emails.custom-reset-password',
                'data' => [
                    'user' => $dummyUser,
                    'orgName' => 'Example Organisation',
                    'resetUrl' => url('/password/reset?token=example'),
                    'userFullName' => 'John Doe',
                    'inviterName' => null,
                ],
            ],
            'custom-reset-password-invitation' => [
                'view' => 'emails.custom-reset-password',
                'data' => [
                    'user' => $dummyUser,
                    'orgName' => 'Example Organisation',
                    'resetUrl' => url('/password/reset?token=example'),
                    'userFullName' => 'John Doe',
                    'inviterName' => 'Jane Smith',
                ],
            ],
            
            // Additional templates
            'welcome-user' => [
                'view' => 'emails.welcome-user',
                'data' => [
                    'user' => $dummyUser,
                ],
            ],
            'basecamp-reset-password' => [
                'view' => 'emails.basecamp-reset-password',
                'data' => [
                    'user' => $dummyUser,
                    'userFullName' => 'John Doe',
                    'inviterName' => 'Jane Smith',
                    'resetUrl' => url('/password/reset?token=example'),
                ],
            ],
            'weekly-summary' => [
                'view' => 'emails.weekly-summary',
                'data' => [
                    'user' => $dummyUser,
                    'weekLabel' => 'Week of ' . $now->format('M d, Y'),
                    'engagementText' => 'Your engagement this week has been excellent. You participated in team activities and contributed positively to discussions.',
                    'summaryText' => 'This is a sample weekly emotional summary text. Your overall sentiment has been positive this week.',
                    'organisationSummary' => 'The organisation as a whole has shown great progress this week. Team collaboration and communication have improved significantly.',
                ],
            ],
            'reset-password' => [
                'view' => 'emails.reset-password',
                'data' => [
                    'user' => $dummyUser,
                    'organisation' => 'Example Organisation',
                    'url' => url('/password/reset?token=example'),
                ],
            ],
            'staff_reset_password' => [
                'view' => 'emails.staff_reset_password',
                'data' => [
                    'user' => $dummyUser,
                    'organisation' => 'Example Organisation',
                    'url' => url('/password/reset?token=example'),
                ],
            ],
            'verify-user-inline' => [
                'view' => 'emails.verify-user-inline',
                'data' => [
                    'user' => $dummyUser,
                    'verificationUrl' => url('/verify/1?signature=example'),
                ],
            ],
            'payment-confirmation' => [
                'view' => 'emails.payment-confirmation',
                'data' => [
                    'user' => $dummyUser,
                    'amount' => '£120.00',
                    'date' => $now->format('M d, Y'),
                ],
            ],
        ];
    }
}
