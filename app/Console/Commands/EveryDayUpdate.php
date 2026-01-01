<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Office;
use App\Models\User;
use App\Http\Controllers\AdminReportController;
use App\Services\OneSignalService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use OpenAI\Laravel\Facades\OpenAI;
use App\Models\HappyIndex;
use App\Models\WeeklySummary as WeeklySummaryModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class EveryDayUpdate extends Command
{
	protected $signature = 'notification:send {--only=} {--date=} {--month=} {--year=}';
    protected $description = 'Send daily reports, push notifications, weekly and monthly email';

    public function handle(OneSignalService $oneSignal)
    {
        $only = $this->option('only');
        $date = $this->option('date') ?: now()->toDateString();
        $now  = now('Asia/Kolkata');

        Log::info("Cron started for --only={$only} at {$now}");

        if ($only === 'notification') {
            $this->sendNotification($oneSignal);
        } elseif ($only === 'report') {
            $this->sendReports($date);
        } elseif ($only === 'email') {
            $this->sendFridayEmail();
        } elseif ($only === 'monthly') {
            $this->sendMonthlyEmail();
        } elseif ($only === 'sentiment') {
            $this->sendSentimentReminderEmail();
        } elseif ($only === 'monthly-summary') {
            // Allow manual month/year override for generating past months
            $month = $this->option('month');
            $year = $this->option('year');
            $this->generateMonthlySummary($month, $year);
        } elseif ($only === 'weeklySummary') {
            $this->generateWeeklySummary();
        } else {
            // fallback combined logic
            $this->sendNotification($oneSignal);
            $this->sendReports($date);

            if ($now->format('H:i') === '12:36') {
                $this->sendSentimentReminderEmail();
            }

            if ($now->isFriday() && $now->format('H:i') === '16:30') {
                $this->sendFridayEmail();
            }

            if ($now->isLastOfMonth() && $now->format('H:i') === '23:59') {
                $this->sendMonthlyEmail();
            }

            if ($now->isLastOfMonth() && $now->format('H:i') === '22:00') {
                $this->generateMonthlySummary();
            }

            if ($now->isSunday() && $now->format('H:i') === '23:00') {
                $this->generateWeeklySummary();
            }
        }
    }

	/**
     * Helper method to store notification in database
     * 
     * @param int $userId
     * @param string $type Notification type (sentiment, weekly-report, monthly-report, sentiment-reminder, weekly-summary, monthly-summary)
     * @param string $title
     * @param string $description
     * @param string|null $link
     * @param Carbon|null $date Check for existing notification on this date
     * @return void
     */
    protected function storeNotification($userId, $type, $title, $description, $link = null, $date = null)
    {
        try {
            $now = $date ?: now('Asia/Kolkata');
            
            // Check if notification already exists for this type, title, and date range
            $query = \App\Models\IotNotification::where('to_bubble_user_id', $userId)
                ->where('notificationType', $type)
                ->where('title', $title);
            
            // For weekly/monthly notifications, check within the week/month
            if ($type === 'weekly-report' || $type === 'weekly-summary') {
                $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY);
                $endOfWeek = $now->copy()->endOfWeek(Carbon::SUNDAY);
                $query->whereBetween('created_at', [
                    $startOfWeek->startOfDay()->toDateTimeString(),
                    $endOfWeek->endOfDay()->toDateTimeString()
                ]);
            } elseif ($type === 'monthly-report' || $type === 'monthly-summary') {
                $startOfMonth = $now->copy()->startOfMonth();
                $endOfMonth = $now->copy()->endOfMonth();
                $query->whereBetween('created_at', [
                    $startOfMonth->startOfDay()->toDateTimeString(),
                    $endOfMonth->endOfDay()->toDateTimeString()
                ]);
            } elseif ($date) {
                $query->whereDate('created_at', $now->toDateString());
            }
            
            $alreadyExists = $query->exists();
            
            if (!$alreadyExists) {
                \App\Models\IotNotification::create([
                    'to_bubble_user_id' => $userId,
                    'from_bubble_user_id' => null,
                    'notificationType' => $type,
                    'title' => $title,
                    'description' => $description,
                    'notificationLinks' => $link,
                    'sendNotificationId' => null,
                    'status' => 'Active',
                    'archive' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                
                Log::channel('daily')->info("Notification stored in DB", [
                    'user_id' => $userId,
                    'type' => $type,
                    'title' => $title
                ]);
                
                return true; // Notification was stored successfully
            } else {
                Log::channel('daily')->info("Notification already exists, skipping", [
                    'user_id' => $userId,
                    'type' => $type,
                    'title' => $title
                ]);
                
                return false; // Notification already exists
            }
        } catch (\Throwable $e) {
            Log::channel('daily')->error("Failed to store notification", [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
            
            return false; // Failed to store
        }
    }

	protected function sendNotificationOLD(OneSignalService $oneSignal)
    {
      $playerIds = User::whereNotNull('fcmToken')
        ->where('fcmToken', '!=', '')
        ->pluck('fcmToken')
        ->filter(fn($token) => preg_match('/^[a-z0-9-]{8,}$/i', $token))
        ->unique()
        ->values()
        ->toArray();
      if (!empty($playerIds)) {
        $response = $oneSignal->sendNotification(
          'Feedback',
          "How's things at work today?",
          $playerIds
        );
        Log::channel('daily')->info('Notification sent to users', ['response' => $response]);
        $this->info('Notification sent successfully.');
      } else {
        Log::channel('daily')->warning('No valid OneSignal player IDs found.');
        $this->warn('No valid player IDs to send notification.');
      }
    }


  protected function sendNotification(OneSignalService $oneSignal)
  {
      $this->info("Fetching users...");

      // Get all users with a valid FCM token and load organisation
      $users = User::whereNotNull('fcmToken')
          ->where('fcmToken', '!=', '')
          ->with('organisation')
          ->get();

      if ($users->isEmpty()) {
          $this->warn('No users found with valid FCM tokens.');
          Log::channel('daily')->info('No users found with valid FCM tokens.');
          return;
      }

      // Filter users based on their timezone, target time (16:00), organisation and working days
      $usersToNotify = $users->filter(function ($user) {
          // Get user's timezone or default to Asia/Kolkata
          $userTimezone = $user->timezone ?: 'Asia/Kolkata';
          
          // Validate timezone to prevent errors
          if (!in_array($userTimezone, timezone_identifiers_list())) {
              Log::channel('daily')->warning("Invalid timezone for user {$user->id}: {$userTimezone}, using Asia/Kolkata");
              $userTimezone = 'Asia/Kolkata';
          }
          
          // Get current time in user's timezone
          $userNow = now($userTimezone);
          
          // Check if it's 16:00 in user's timezone
          $targetTime = $userNow->format('H:i');
          if ($targetTime !== '16:00') {
              return false;
          }

          $today = $userNow->format('D'); // e.g., Mon, Tue, Wed

          // Organisation users: notify only on working days
          if ($user->organisation) {
              // Decode working_days JSON or fallback to Mon–Fri
              $workingDays = $user->organisation->working_days;
              if (is_string($workingDays)) {
                  $workingDays = json_decode($workingDays, true) ?? ["Mon", "Tue", "Wed", "Thu", "Fri"];
              }

              return in_array($today, $workingDays);
          }

          // Basecamp users (no organisation): notify every day
          return true;
      });

      if ($usersToNotify->isEmpty()) {
          $this->warn('No users to notify at this time.');
          Log::channel('daily')->info('No users to notify at this time.');
          return;
      }

      // ✅ Filter out users who already received notification today to prevent duplicates
      $usersToNotify = $usersToNotify->filter(function ($user) {
          $userTimezone = $user->timezone ?: 'Asia/Kolkata';
          if (!in_array($userTimezone, timezone_identifiers_list())) {
              $userTimezone = 'Asia/Kolkata';
          }
          $userNow = now($userTimezone);
          
          // Check if notification already sent today
          $notificationAlreadySent = \App\Models\IotNotification::where('to_bubble_user_id', $user->id)
              ->where('notificationType', 'sentiment')
              ->whereDate('created_at', $userNow->toDateString())
              ->exists();

          if ($notificationAlreadySent) {
              Log::channel('daily')->info("Skipping user - notification already sent today: {$user->id}");
              return false;
          }
          
          return true;
      });

      if ($usersToNotify->isEmpty()) {
          $this->warn('No users to notify (all already received notification today).');
          Log::channel('daily')->info('No users to notify (all already received notification today).');
          return;
      }

      $playerIds = $usersToNotify->pluck('fcmToken')
          ->filter(fn($token) => preg_match('/^[a-z0-9-]{8,}$/i', $token))
          ->unique()
          ->values()
          ->toArray();

      $this->info("Sending notification to " . count($playerIds) . " users.");
      Log::channel('daily')->info("Sending notification", ['count' => count($playerIds)]);

      $response = $oneSignal->sendNotification(
          'Feedback',
          "How's things at work today?",
          $playerIds
      );

      foreach ($usersToNotify as $user) {
          $userTimezone = $user->timezone ?: 'Asia/Kolkata';
          
          // Validate timezone (already validated in filter, but double-check for safety)
          if (!in_array($userTimezone, timezone_identifiers_list())) {
              $userTimezone = 'Asia/Kolkata';
          }
          
          $userNow = now($userTimezone);
          
          $this->storeNotification(
              $user->id,
              'sentiment',
              'Feedback',
              "How's things at work today?",
              null,
              $userNow
          );
      }

      $this->info('Notification response: ' . json_encode($response));
      Log::channel('daily')->info('Notification sent', ['response' => $response]);

      // Remove invalid tokens if any
      if (!empty($response['errors']['invalid_player_ids'])) {
          $invalidIds = $response['errors']['invalid_player_ids'];
          User::whereIn('fcmToken', $invalidIds)->update(['fcmToken' => null]);
          $this->warn(count($invalidIds) . " invalid tokens removed.");
          Log::channel('daily')->warning('Removed invalid tokens', ['invalid_ids' => $invalidIds]);
      }
  }





    protected function sendReports(string $date)
    {
        // Get all offices with their users
        $offices = Office::with('users')->get();
        
        $officesToReport = [];
        
        foreach ($offices as $office) {
            // Get users for this office
            $users = $office->users;
            
            if ($users->isEmpty()) {
                continue;
            }
            
            // Check if it's 23:59 in any user's timezone for this office
            $shouldSendReport = false;
            $reportDate = $date;
            
            foreach ($users as $user) {
                $userTimezone = $user->timezone ?: 'Asia/Kolkata';
                
                // Validate timezone to prevent errors
                if (!in_array($userTimezone, timezone_identifiers_list())) {
                    Log::channel('daily')->warning("Invalid timezone for user {$user->id}: {$userTimezone}, using Asia/Kolkata");
                    $userTimezone = 'Asia/Kolkata';
                }
                
                $userNow = now($userTimezone);
                
                // Check if it's 23:59 in user's timezone
                if ($userNow->format('H:i') === '23:59') {
                    $shouldSendReport = true;
                    // Use the date in user's timezone
                    $reportDate = $userNow->toDateString();
                    break;
                }
            }
            
            if ($shouldSendReport) {
                $officesToReport[] = [
                    'office' => $office,
                    'date' => $reportDate
                ];
            }
        }
        
        if (empty($officesToReport)) {
            $this->info('No offices need reports at this time (23:59 in their users\' timezones).');
            Log::channel('daily')->info('No offices need reports at this time.');
            return;
        }
        
        foreach ($officesToReport as $item) {
            $office = $item['office'];
            $reportDate = $item['date'];
            
            $payload = [
                'officeId' => $office->id,
                'date'     => $reportDate,
            ];
            Log::channel('daily')->info('Sending report for office: '.$office->id, $payload);

            (new AdminReportController())->getHappyIndexReport($payload);
        }

        $this->info('Reports sent for ' . count($officesToReport) . ' office(s) at 23:59 in their users\' timezones.');
    }
protected function sendFridayEmail()
{
    try {
    
        $users = User::with('organisation')
            ->with(['happyindexes' => function ($q) {
                $q->whereDate('created_at', '>=', now()->subDays(6));
            }])
            ->get();

        if ($users->isEmpty()) {
            $this->error('No users found.');
            return;
        }

        // Get current week range for duplicate check (week starts on Monday)
        $now = now('Asia/Kolkata');
        $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $now->copy()->endOfWeek(Carbon::SUNDAY);
    
        foreach ($users as $user) {
            // ✅ Check if weekly email already sent this week to prevent duplicates
            // Check more broadly to catch any duplicate in the week
            $emailAlreadySent = \App\Models\IotNotification::where('to_bubble_user_id', $user->id)
                ->where('notificationType', 'weekly-report')
                ->where('title', 'Your Weekly Happy Index Report')
                ->whereBetween('created_at', [
                    $startOfWeek->startOfDay()->toDateTimeString(),
                    $endOfWeek->endOfDay()->toDateTimeString()
                ])
                ->exists();

            if ($emailAlreadySent) {
                Log::channel('daily')->info("Skipping user - weekly email already sent this week: {$user->email}");
                continue;
            }
            
            // ✅ Additional safety check: also check if email was sent in the last 7 days
            $recentEmailSent = \App\Models\IotNotification::where('to_bubble_user_id', $user->id)
                ->where('notificationType', 'weekly-report')
                ->where('title', 'Your Weekly Happy Index Report')
                ->where('created_at', '>=', now()->subDays(7)->startOfDay())
                ->exists();
                
            if ($recentEmailSent) {
                Log::channel('daily')->info("Skipping user - weekly email sent in last 7 days: {$user->email}");
                continue;
            }
      
            $days = collect();
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i)->startOfDay();
                $days->push($date);
            }

        
            $happyData = $user->happyindexes->groupBy(function ($item) {
                return $item->created_at->toDateString();
            });

            $labels = [];
            $values = [];

            foreach ($days as $day) {
                $labels[] = $day->format('l'); 
                if ($happyData->has($day->toDateString())) {
                    $score = $happyData->get($day->toDateString())->first()->mood_value;

                    if ($score == 3) {
                        $values[] = 100;
                    } elseif ($score != null) {
                        $values[] = 50;
                    } else {
                        $values[] = 0;
                    }
                } else {
                    $values[] = 0;
                }
            }

            // Chart config
            $chartConfig = [
                'type' => 'line',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [[
                        'label' => 'Happy Index',
                        'borderColor' => 'rgb(237, 48, 55)',
                        'backgroundColor' => 'rgb(237, 48, 55)',
                        'fill' => false,
                        'tension' => 0.4,
                        'pointRadius' => 5,
                        'pointBackgroundColor' => 'rgb(237, 48, 55)',
                        'data' => $values,
                    ]]
                ],
                'options' => [
                    'plugins' => [
                        'title' => [
                            'display' => true,
                            'text' => 'Your Weekly Happy Index'
                        ],
                        'legend' => [
                            'display' => false
                        ]
                    ],
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true,
                            'max' => 100,
                            'ticks' => [
                                'stepSize' => 20
                            ]
                        ],
                        'x' => [
                            'grid' => [
                                'display' => false
                            ]
                        ]
                    ]
                ]
            ];

            $chartUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode($chartConfig));

            // Store notification in database FIRST to prevent race conditions
            $weekLabel = now()->subDays(6)->format('M d') . ' - ' . now()->format('M d');
            $notificationStored = $this->storeNotification(
                $user->id,
                'weekly-report',
                'Your Weekly Happy Index Report',
                "Your weekly happy index report for {$weekLabel} is available.",
                null,
                now('Asia/Kolkata')
            );
            
            // Only send email if notification was successfully stored (not duplicate)
            if ($notificationStored) {
                // Send email to this user
                \Mail::send('emails.weekly-report', [
                    'user' => $user,
                    'organisation' => $user->organisation,
                    'chartUrl' => $chartUrl,
                ], function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Your Weekly Happy Index Report');
                });
                
                Log::channel('daily')->info("Weekly report email sent to: {$user->email}");
            } else {
                Log::channel('daily')->info("Skipping email - notification already exists for: {$user->email}");
            }
        }

        Log::channel('daily')->info('Friday HappyIndex emails sent and notifications stored for all users.');
        $this->info('Friday HappyIndex emails sent and notifications stored for all users.');

    } catch (\Exception $e) {
        Log::channel('daily')->error('Error sending Friday HappyIndex emails and storing notifications', [
            'error' => $e->getMessage(),
        ]);
        $this->error('Error sending Friday HappyIndex emails and storing notifications: ' . $e->getMessage());
    }
}


protected function sendMonthlyEmail()
{
    try {
     
        $users = User::with('organisation')
            ->with(['happyindexes' => function ($q) {
                $q->whereMonth('created_at', now()->month);
            }])
            ->get();

        if ($users->isEmpty()) {
            $this->error('No users found.');
            return;
        }

        // Get current month range for duplicate check
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        foreach ($users as $user) {
            // ✅ Check if monthly email already sent this month to prevent duplicates
            $emailAlreadySent = \App\Models\IotNotification::where('to_bubble_user_id', $user->id)
                ->where('notificationType', 'monthly-report')
                ->whereBetween('created_at', [$startOfMonth . ' 00:00:00', $endOfMonth . ' 23:59:59'])
                ->exists();

            if ($emailAlreadySent) {
                Log::channel('daily')->info("Skipping user - monthly email already sent this month: {$user->email}");
                continue;
            }
            // Prepare days in current month
            $daysInMonth = now()->daysInMonth;
            $days = collect();
            for ($i = 1; $i <= $daysInMonth; $i++) {
                $days->push(now()->startOfMonth()->addDays($i - 1));
            }


            $happyData = $user->happyindexes->groupBy(function ($item) {
                return $item->created_at->toDateString();
            });

            $labels = [];
            $values = [];

            foreach ($days as $day) {
                $labels[] = $day->format('d M'); 

                if ($happyData->has($day->toDateString())) {
                    $score = $happyData->get($day->toDateString())->first()->mood_value;

                    if ($score == 3) {
                        $values[] = 100;
                    } elseif ($score != null) {
                        $values[] = 50;
                    } else {
                        $values[] = 0;
                    }
                } else {
                    $values[] = 0;
                }
            }

            // Chart config
            $chartConfig = [
                'type' => 'line',
                'data' => [
                    'labels' => $labels,
                    'datasets' => [[
                        'label' => 'Happy Index',
                        'borderColor' => 'rgb(237, 48, 55)',
                        'backgroundColor' => 'rgb(237, 48, 55)',
                        'fill' => false,
                        'tension' => 0.4,
                        'pointRadius' => 5,
                        'pointBackgroundColor' => 'rgb(237, 48, 55)',
                        'data' => $values,
                    ]]
                ],
                'options' => [
                    'plugins' => [
                        'title' => [
                            'display' => true,
                            'text' => 'Your Monthly Happy Index'
                        ],
                        'legend' => [
                            'display' => false
                        ]
                    ],
                    'scales' => [
                        'y' => [
                            'beginAtZero' => true,
                            'max' => 100,
                            'ticks' => [
                                'stepSize' => 20
                            ]
                        ],
                        'x' => [
                            'grid' => [
                                'display' => false
                            ]
                        ]
                    ]
                ]
            ];

            $chartUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode($chartConfig));

            // Send email to this user
            \Mail::send('emails.monthly-report', [
                'user' => $user,
                'organisation' => $user->organisation,
                'chartUrl' => $chartUrl,
            ], function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Your Monthly Happy Index Report');
            });

            // Store notification in database
            $monthName = now()->format('F Y');
            $this->storeNotification(
                $user->id,
                'monthly-report',
                'Your Monthly Happy Index Report',
                "Your monthly happy index report for {$monthName} is available.",
                null,
                now('Asia/Kolkata')
            );
        }

        Log::channel('daily')->info('Monthly HappyIndex emails sent and notifications stored for all users.');
        $this->info('Monthly HappyIndex emails sent and notifications stored for all users.');

    } catch (\Exception $e) {
        Log::channel('daily')->error('Error sending Monthly HappyIndex emails and storing notifications', [
            'error' => $e->getMessage(),
        ]);
        $this->error('Error sending Monthly HappyIndex emails and storing notifications: ' . $e->getMessage());
    }
}
  
	protected function sendSentimentReminderEmail()
    {
        try {

            $now = now();
            $todayDate = $now->toDateString();
            $todayShort = $now->format('D'); 

            Log::channel('daily')->info("Checking sentiment reminders for: $todayDate");
            $this->info("Checking sentiment reminders for: $todayDate");

            $users = User::whereDoesntHave('happyindexes', function ($q) use ($todayDate) {
                $q->whereDate('created_at', $todayDate);
            })->get();

            if ($users->isEmpty()) {
                Log::channel('daily')->info("No users missing sentiment today.");
                return;
            }

            Log::channel('daily')->info("Users missing sentiment: {$users->count()}");


            $usersToNotify = $users->filter(function ($user) use ($todayShort) {

                // If user belongs to an organisation:
                if ($user->organisation) {

                    $workingDays = $user->organisation->working_days;

                    if (is_string($workingDays)) {
                        $workingDays = json_decode($workingDays, true) ?: ["Mon", "Tue", "Wed", "Thu", "Fri"];
                    }

                    return in_array($todayShort, $workingDays);
                }

                return true;
            });

            if ($usersToNotify->isEmpty()) {
                Log::channel('daily')->info("No users to notify today (non-working day).");
                return;
            }

            Log::channel('daily')->info("Filtered users (working days): " . $usersToNotify->count());

            // STEP 3 → Send OneSignal Email
            $oneSignal = app(\App\Services\OneSignalService::class);

            foreach ($usersToNotify as $user) {

                // ✅ NEW CONDITION → Send only if user.status = 1
                if ($user->status != 1) {
                    Log::channel('daily')->info("Skipping user (status != 1): {$user->email}");
                    continue;
                }

                // ✅ Check if email already sent today to prevent duplicates
                $emailAlreadySent = \App\Models\IotNotification::where('to_bubble_user_id', $user->id)
                    ->where('notificationType', 'sentiment-reminder')
                    ->whereDate('created_at', $todayDate)
                    ->exists();

                if ($emailAlreadySent) {
                    Log::channel('daily')->info("Skipping user - email already sent today: {$user->email}");
                    continue;
                }

                Log::channel('daily')->info("Sending sentiment reminder to: {$user->email}");

                $emailBody = view('emails.sentiment-reminder', [
                    'user' => $user
                ])->render();

                $oneSignal->sendEmailMessage(
                    $user->email,
                    'Reminder: Please Update Your Sentiment Index',
                    $emailBody
                );

                // Store notification in database
                $this->storeNotification(
                    $user->id,
                    'sentiment-reminder',
                    'Reminder: Please Update Your Sentiment Index',
                    "Please update your sentiment index for today. Your feedback helps us understand how things are going at work.",
                    null,
                    $now
                );
            }

            Log::channel('daily')->info("✔ Sentiment reminder emails sent and notifications stored successfully.", [
                'total_sent' => $usersToNotify->count()
            ]);

            $this->info("Sentiment reminder emails sent and notifications stored successfully.");

        } catch (\Throwable $e) {

            Log::channel('daily')->error("❌ Error sending sentiment reminder emails and storing notifications", [
                'error' => $e->getMessage()
            ]);

            $this->error("Error sending sentiment reminder emails and storing notifications: " . $e->getMessage());
        }
    }



  protected function sendAIMonthlyEmail()
    {
        try {
            $users = User::with('organisation')
                ->with(['happyindexes' => function ($q) {
                    $q->whereMonth('created_at', now()->month);
                }])
                ->get();

            if ($users->isEmpty()) {
                $this->error('No users found.');
                return;
            }

            foreach ($users as $user) {
                // Prepare days in current month
                $daysInMonth = now()->daysInMonth;
                $days = collect();
                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $days->push(now()->startOfMonth()->addDays($i - 1));
                }

                $happyData = $user->happyindexes->groupBy(function ($item) {
                    return $item->created_at->toDateString();
                });

                $labels = [];
                $values = [];
                $aiLines = [];

                foreach ($days as $day) {
                    $labels[] = $day->format('d M');

                    if ($happyData->has($day->toDateString())) {
                        $score = $happyData->get($day->toDateString())->first()->mood_value;
                        $desc = $happyData->get($day->toDateString())->first()->description ?? '';

                        if ($score == 3) {
                            $values[] = 100;
                        } elseif ($score != null) {
                            $values[] = 50;
                        } else {
                            $values[] = 0;
                        }

                        $aiLines[] = "- {$day->format('d M')}: Mood {$score}, Note: {$desc}";

                    } else {
                        $values[] = 0;
                        $aiLines[] = "- {$day->format('d M')}: No sentiment submitted";
                    }
                }

                // Chart config
                $chartConfig = [
                    'type' => 'line',
                    'data' => [
                        'labels' => $labels,
                        'datasets' => [[
                            'label' => 'Happy Index',
                            'borderColor' => 'rgb(237, 48, 55)',
                            'backgroundColor' => 'rgb(237, 48, 55)',
                            'fill' => false,
                            'tension' => 0.4,
                            'pointRadius' => 5,
                            'pointBackgroundColor' => 'rgb(237, 48, 55)',
                            'data' => $values,
                        ]]
                    ],
                    'options' => [
                        'plugins' => [
                            'title' => [
                                'display' => true,
                                'text' => 'Your Monthly Happy Index'
                            ],
                            'legend' => [
                                'display' => false
                            ]
                        ],
                        'scales' => [
                            'y' => [
                                'beginAtZero' => true,
                                'max' => 100,
                                'ticks' => [
                                    'stepSize' => 20
                                ]
                            ],
                            'x' => [
                                'grid' => [
                                    'display' => false
                                ]
                            ]
                        ]
                    ]
                ];

                $chartUrl = "https://quickchart.io/chart?c=" . urlencode(json_encode($chartConfig));

                // Generate AI summary for the month (batched)
                $prompt = "Generate a concise, one-line per day summary (10 words max) for this month:\n";
                $prompt .= implode("\n", $aiLines);

                $response = OpenAI::responses()->create([
                    'model' => 'gpt-4.1-mini',
                    'input' => $prompt,
                ]);

                $aiSummary = $response->output[0]->content[0]->text ?? '';

                // Send mail to this user
                Mail::send('emails.monthly-report', [
                    'user' => $user,
                    'organisation' => $user->organisation,
                    'chartUrl' => $chartUrl,
                    'aiSummary' => $aiSummary,
                ], function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Your Monthly Happy Index Report');
                });
            }

            Log::channel('daily')->info('Monthly HappyIndex emails sent to all users.');
            $this->info('Monthly HappyIndex emails sent to all users.');

        } catch (\Exception $e) {
            Log::channel('daily')->error('Error sending Monthly HappyIndex emails', [
                'error' => $e->getMessage(),
            ]);
            $this->error('Error sending Monthly HappyIndex emails: ' . $e->getMessage());
        }
    }

    protected function sendAiSentimentReminderEmail()
    {
        try {
            $today = now()->toDateString();
            $this->info("Checking sentiment reminders for date: $today");
            Log::channel('daily')->info("Checking sentiment reminders for date: $today");

            // Users who did not submit sentiment today
            $users = User::whereDoesntHave('happyindexes', function ($q) use ($today) {
                $q->whereDate('created_at', $today);
            })->get();

            $this->info("Users missing sentiment count: " . $users->count());
            Log::channel('daily')->info("Users missing sentiment count", ['count' => $users->count()]);

            if ($users->isEmpty()) {
                $this->warn('No users missing sentiment today.');
                Log::channel('daily')->info('No users missing sentiment today.');
                return;
            }

            foreach ($users as $user) {
                // Generate short AI reminder (max 20 words)
                $prompt = "Generate a short friendly reminder (max 20 words) for user {$user->name} to submit today's sentiment.";
                $response = OpenAI::responses()->create([
                    'model' => 'gpt-4.1-mini',
                    'input' => $prompt,
                ]);

                $aiTip = $response->output[0]->content[0]->text ?? 'Please update your sentiment index today.';

                Mail::send('emails.sentiment-reminder', [
                    'user' => $user,
                    'aiTip' => $aiTip
                ], function ($message) use ($user) {
                    $message->to($user->email)
                        ->subject('Reminder: Please Update Your Sentiment Index');
                });

                $this->line("Sent reminder to: {$user->email}");
                Log::channel('daily')->info("Sent sentiment reminder", ['email' => $user->email]);
            }

            Log::channel('daily')->info('Sentiment reminder emails sent', ['count' => $users->count()]);
            $this->info('Sentiment reminder emails sent successfully.');

        } catch (\Exception $e) {
            Log::channel('daily')->error('Error sending sentiment reminders', ['error' => $e->getMessage()]);
            $this->error('Error sending sentiment reminders: ' . $e->getMessage());
        }
    
}

/**
 * Generate weekly summaries for users.
 *
 * - When run by scheduler (no $force) it requires Sunday 23:00 and uses the current week.
 * - When run with $force = true (manual), it uses LAST week (useful for local testing).
 *
 * @param bool $force
 * @return void
 */
public function generateWeeklySummary()
{
    $today = now('Asia/Kolkata');
    Log::info("WeeklySummary generation started at {$today}");
    
    // Remove strict time check - let cron handle scheduling
    // Only check if it's Sunday (cron runs on Sunday)
    if (!$today->isSunday()) {
        Log::info("WeeklySummary: Not Sunday, skipping. Current day: {$today->format('l')}");
        return;
    }

    // Generate summary for LAST week (the week that just ended)
    // On Sunday, we generate for the week that ended on Saturday (previous week)
    $startOfWeekIST = $today->copy()->subWeek()->startOfWeek();
    $endOfWeekIST = $today->copy()->subWeek()->endOfWeek();
    $startOfWeekUTC = $startOfWeekIST->clone()->setTimezone('UTC');
    $endOfWeekUTC = $endOfWeekIST->clone()->setTimezone('UTC');

    $userIds = HappyIndex::whereBetween('created_at', [$startOfWeekUTC, $endOfWeekUTC])
        ->distinct()
        ->pluck('user_id');

    if ($userIds->isEmpty()) {
        Log::info("WeeklySummary: No users found with mood data for this week.");
        return;
    }

    $users = User::whereIn('id', $userIds)->get();
    $oneSignal = new OneSignalService();

    foreach ($users as $user) {

        $allData = HappyIndex::where('user_id', $user->id)
            ->whereBetween('created_at', [$startOfWeekUTC, $endOfWeekUTC])
            ->orderBy('created_at')
            ->get(['mood_value', 'description', 'created_at']);

        if ($allData->isEmpty()) {
            continue;
        }

        $entries = $allData->map(function ($item) {
            $date = $item->created_at->setTimezone('Asia/Kolkata')->format('M d, D');
            $mood = match ($item->mood_value) {
                3 => 'Good 😊',
                2 => 'Okay 😐',
                1 => 'Bad 🙁',
                default => 'Unknown',
            };
            $desc = $item->description ? " - {$item->description}" : '';
            return "{$date}: {$mood}{$desc}";
        })->implode("\n");

        $weekLabel = $startOfWeekIST->format('M d') . ' - ' . $endOfWeekIST->format('M d');
        $year = $startOfWeekIST->year;
        $month = $startOfWeekIST->month;
        $weekNumber = $startOfWeekIST->weekOfMonth;

        // Get prompt from database or use default
        $promptTemplate = \App\Models\AppSetting::getValue('weekly_summary_prompt', 'Generate a professional weekly emotional summary for the user based strictly on the following daily sentiment data from {weekLabel}:

{entries}

Important writing requirements:
- Do NOT start with greetings.
- Do NOT address the user directly.
- Write a polished, insightful summary of emotional trends.
- Provide 3–5 sentences analyzing patterns across the week.
- Tone should be professional, warm, supportive, and not casual.
- Focus only on the user\'s emotional journey.
- Do NOT include organisational-level references.');

        $prompt = str_replace(['{weekLabel}', '{entries}'], [$weekLabel, $entries], $promptTemplate);

        // FIRST ATTEMPT
        $summaryText = $this->generateAIText($prompt, $user->id);

        // RETRY LOGIC (unchanged)
        if ($summaryText === 'QUOTA_EXCEEDED') {
            $summaryText = 'Summary unavailable due to AI quota limits.';
        } else {
            if ($summaryText === 'AI_SERVICE_UNAVAILABLE' ||
                empty(trim($summaryText)) ||
                $summaryText === 'Summary could not be generated due to an error.') {

                Log::warning("WeeklySummary: AI retry attempt for user {$user->id}");
                $summaryText = $this->generateAIText($prompt, $user->id);
            }

            if ($summaryText === 'QUOTA_EXCEEDED') {
                $summaryText = 'Summary unavailable due to AI quota limits.';
            } elseif ($summaryText === 'AI_SERVICE_UNAVAILABLE' ||
                empty(trim($summaryText)) ||
                $summaryText === 'Summary could not be generated due to an error.') {
                $summaryText = 'No summary generated.';
            }
        }

        // SAVE SUMMARY (unchanged)
        WeeklySummaryModel::updateOrCreate(
            [
                'user_id' => $user->id,
                'year' => $year,
                'month' => $month,
                'week_number' => $weekNumber,
            ],
            [
                'week_label' => $weekLabel,
                'summary' => $summaryText,
            ]
        );

        /**
         * IMPORTANT: SEND EMAIL AND STORE NOTIFICATION ONLY IF SUMMARY IS VALID
         */
        if ($this->isValidSummary($summaryText)) {
            // ✅ Check if weekly summary email already sent for this week to prevent duplicates
            // Use proper datetime range for better matching
            $emailAlreadySent = \App\Models\IotNotification::where('to_bubble_user_id', $user->id)
                ->where('notificationType', 'weekly-summary')
                ->where('title', 'LIKE', "%Weekly Summary ({$weekLabel})%")
                ->whereBetween('created_at', [
                    $startOfWeekIST->startOfDay()->toDateTimeString(),
                    $endOfWeekIST->endOfDay()->toDateTimeString()
                ])
                ->exists();

            if ($emailAlreadySent) {
                Log::warning("⛔ Email NOT sent — weekly summary already sent this week for user {$user->id}");
                continue;
            }
            
            // ✅ Additional safety check: also check if email was sent in the last 7 days with same week label
            $recentEmailSent = \App\Models\IotNotification::where('to_bubble_user_id', $user->id)
                ->where('notificationType', 'weekly-summary')
                ->where('title', 'LIKE', "%Weekly Summary ({$weekLabel})%")
                ->where('created_at', '>=', now()->subDays(7)->startOfDay())
                ->exists();
                
            if ($recentEmailSent) {
                Log::warning("⛔ Email NOT sent — weekly summary with same week label sent in last 7 days for user {$user->id}");
                continue;
            }

            // Store notification in database FIRST to prevent race conditions
            $notificationStored = $this->storeNotification(
                $user->id,
                'weekly-summary',
                "Tribe365 Weekly Summary ({$weekLabel})",
                "Your weekly emotional summary for {$weekLabel} has been generated.",
                null,
                now('Asia/Kolkata')
            );
            
            // Only send email if notification was successfully stored (not duplicate)
            if ($notificationStored) {
                try {
                    $engagementText = $this->buildEngagementSummary($user, $startOfWeekUTC, $endOfWeekUTC);
                    $organisationSummary = $this->generateAIOrgSummary($startOfWeekUTC, $endOfWeekUTC);

                    $emailBody = view('emails.weekly-summary', [
                        'user'                => $user,
                        'summaryText'         => $summaryText,
                        'weekLabel'           => $weekLabel,
                        'engagementText'      => $engagementText,
                        'organisationSummary' => $organisationSummary,
                    ])->render();

                    $oneSignal->registerEmailUserFallback($user->email, $user->id, [
                        'subject' => "Tribe365 Weekly Summary ({$weekLabel})",
                        'body'    => $emailBody,
                    ]);

                    Log::info("✅ OneSignal weekly email sent and notification stored for user {$user->id}");
                } catch (\Throwable $e) {
                    Log::error("❌ OneSignal email failed for user {$user->id}: {$e->getMessage()}");
                }
            } else {
                Log::warning("⛔ Email NOT sent — notification already exists for user {$user->id}");
            }
        } else {
            Log::warning("⛔ Email NOT sent — summary invalid for user {$user->id}");
        }

        Log::info("WeeklySummary generated for user {$user->id} ({$weekLabel})");
    }
}

private function generateAIOrgSummary($startOfWeekUTC, $endOfWeekUTC)
{
    // Collect all mood entries (but NO user names)
    $allEntries = HappyIndex::whereBetween('created_at', [$startOfWeekUTC, $endOfWeekUTC])
        ->orderBy('created_at')
        ->get(['mood_value', 'created_at']);

    if ($allEntries->isEmpty()) {
        return "No organisation-wide sentiment data available for this week.";
    }

    // Build dataset for AI
    $dataset = $allEntries->map(function ($item) {
        $date = $item->created_at->setTimezone('Asia/Kolkata')->format('M d, D');
        $mood = match ($item->mood_value) {
            3 => 'Good 😊',
            2 => 'Okay 😐',
            1 => 'Bad 🙁',
            default => 'Unknown',
        };
        return "{$date}: {$mood}";
    })->implode("\n");

    $prompt = <<<PROMPT
Create a professional organisation-level weekly sentiment summary based on the following mood entries:

{$dataset}

Instructions:
- Do NOT include any personal names or identifying details.
- Summarise overall team sentiment patterns.
- Highlight positive trends and areas needing attention.
- Write 3-4 sentences maximum.
- Tone should be corporate, neutral, and supportive.
PROMPT;

    try {
        $response = \OpenAI\Laravel\Facades\OpenAI::responses()->create([
            'model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4.1-mini'),
            'input' => $prompt,
        ]);

        $orgSummary = '';
        if (!empty($response->output)) {
            foreach ($response->output as $item) {
                foreach ($item->content ?? [] as $c) {
                    $orgSummary .= $c->text ?? '';
                }
            }
        }

        return trim($orgSummary) ?: "No organisational summary available.";
    } catch (\Throwable $e) {
        Log::error("Organisation Summary Error: " . $e->getMessage());
        return "Organisation summary could not be generated this week.";
    }
}


private function buildEngagementSummary(User $user, $startOfWeekUTC, $endOfWeekUTC)
{
    // Determine if user is Basecamp or Organisation user
    $isBasecampUser = $user->orgId ? false : true;

    // Working days for organisation users (Mon–Fri)
    $workingDays = [
        'Mon', 'Tue', 'Wed', 'Thu', 'Fri'
    ];

    // All week days for basecamp users (Mon–Sun)
    $allDays = [
        'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'
    ];

    // User-specific allowed days
    $allowedDays = $isBasecampUser ? $allDays : $workingDays;

    // Get submitted days from HappyIndex
    $submittedDays = HappyIndex::where('user_id', $user->id)
        ->whereBetween('created_at', [$startOfWeekUTC, $endOfWeekUTC])
        ->get()
        ->map(function ($item) {
            return $item->created_at
                ->setTimezone('Asia/Kolkata')
                ->format('D');
        })
        ->unique()
        ->values()
        ->toArray();

    // Calculate missed days
    $missedDays = array_values(array_diff($allowedDays, $submittedDays));

    // Counts
    $submittedCount = count($submittedDays);
    $totalExpected  = count($allowedDays);

    // Build engagement message
    if ($submittedCount === $totalExpected) {

        return "Kudos! Your engagement with Tribe365 was 100% this week. 
You submitted your sentiment on all " . $totalExpected . " days.";
    }

    // Build readable lists
    $submittedList = $submittedCount > 0 
        ? implode(', ', $submittedDays) 
        : 'None';

    $missedList = !empty($missedDays) 
        ? implode(', ', $missedDays) 
        : 'None';

    // Final formatted text
    return "You shared your sentiment on: {$submittedList}.
You missed: {$missedList}.
Recommendation: Try to engage with Tribe365 regularly to maintain consistent emotional awareness.";
}


/**
 * NEW: Helper that checks if summary is valid
 */
private function isValidSummary(string $text): bool
{
    $bad = [
        '',
        'Summary could not be generated due to an error.',
        'AI_SERVICE_UNAVAILABLE',
        'No summary generated.',
        'Summary unavailable due to AI quota limits.',
        'QUOTA_EXCEEDED',
    ];

    return !in_array(trim($text), $bad, true);
}

	/**
 * Generate text using OpenAI Chat Completions via HTTP with retries/backoff.
 *
 * Returns:
 *  - string summary on success
 *  - 'QUOTA_EXCEEDED' if API returns 429
 *  - 'AI_SERVICE_UNAVAILABLE' for other permanent failures
 *
 * @param string $prompt
 * @param int|null $userId
 * @return string
 */
private function generateAIText(string $prompt, $userId = null): string
{
    $apiKey = env('OPENAI_API_KEY');
    if (empty($apiKey)) {
        Log::error("OpenAI API key missing (OPENAI_API_KEY).");
        return 'AI_SERVICE_UNAVAILABLE';
    }

    // Model configuration — change via .env if you want different model
    $chatModel = env('OPENAI_CHAT_MODEL', 'gpt-3.5-turbo');
    $maxTokens = intval(env('OPENAI_MAX_TOKENS', 500));
    $temperature = floatval(env('OPENAI_TEMPERATURE', 0.7));

    $attempts = 0;
    $maxAttempts = 3;
    $backoffSeconds = 1;

    while ($attempts < $maxAttempts) {
        $attempts++;

        try {
            $res = Http::withToken($apiKey)
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $chatModel,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature,
                ]);

            if ($res->ok()) {
                $json = $res->json();
                $text = $json['choices'][0]['message']['content'] ?? null;
                if (!empty($text)) {
                    return trim((string)$text);
                }
                // empty response — warn and try again
                Log::warning("AI chat returned empty for user {$userId}, attempt {$attempts}");
            } else {
                $status = $res->status();
                $body = $res->body();

                // Quota exceeded — return sentinel immediately
                if ($status === 429 || str_contains($body, 'insufficient_quota')) {
                    Log::warning("AI chat HTTP failed for user {$userId}: status {$status} body: {$body}");
                    return 'QUOTA_EXCEEDED';
                }

                // Model-not-found or 404 model error — treat as permanent service unavailability
                if ($status === 404 && str_contains($body, 'model')) {
                    Log::warning("AI chat HTTP model error for user {$userId}: status {$status} body: {$body}");
                    return 'AI_SERVICE_UNAVAILABLE';
                }

                // For other non-ok status codes, log and retry
                Log::warning("AI chat HTTP failed for user {$userId}: status {$status} body: {$body}");
            }
        } catch (\Throwable $e) {
            Log::warning("AI chat HTTP exception for user {$userId}: " . $e->getMessage());
        }

        // Exponential backoff before retry
        sleep($backoffSeconds);
        $backoffSeconds *= 2;
    }

    Log::error("AI Generation final failure for user {$userId}: no usable response after {$maxAttempts} attempts.");
    return 'AI_SERVICE_UNAVAILABLE';
}



    public function generateMonthlySummary($month = null, $year = null)
    {
        $today = now('Asia/Kolkata');
        
        // If month/year provided, use that; otherwise use current month
        if ($month && $year) {
            $targetDate = Carbon::create($year, $month, 1, 0, 0, 0, 'Asia/Kolkata');
            $startOfMonthIST = $targetDate->copy()->startOfMonth();
            $endOfMonthIST   = $targetDate->copy()->endOfMonth();
            Log::info("MonthlySummary generation started for {$targetDate->format('F Y')} (manual override)");
        } else {
            // Check if it's last day of month (cron runs on last day at 22:00)
            // Also allow day 28 for manual testing/backup
            if (!$today->isLastOfMonth() && $today->day !== 28) {
                Log::info("MonthlySummary: Not last day of month or 28th, skipping. Current date: {$today->format('Y-m-d')}");
                return;
            }
            $startOfMonthIST = $today->copy()->startOfMonth();
            $endOfMonthIST   = $today->copy()->endOfMonth();
            Log::info("MonthlySummary generation started at {$today}");
        }
        $startOfMonthUTC = $startOfMonthIST->clone()->setTimezone('UTC');
        $endOfMonthUTC   = $endOfMonthIST->clone()->setTimezone('UTC');

        $userIds = \App\Models\HappyIndex::whereBetween('created_at', [$startOfMonthUTC, $endOfMonthUTC])
            ->distinct()
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            \Log::info("MonthlySummary: No users found with mood data for {$startOfMonthIST->format('F Y')}.");
            return;
        }

        $users = \App\Models\User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $allData = \App\Models\HappyIndex::where('user_id', $user->id)
                ->whereBetween('created_at', [$startOfMonthUTC, $endOfMonthUTC])
                ->orderBy('created_at')
                ->get(['mood_value', 'description', 'created_at']);

            if ($allData->isEmpty()) {
                continue;
            }

            // ✅ Build mood data summary for prompt
            $entries = $allData->map(function ($h) {
                $date = $h->created_at->setTimezone('Asia/Kolkata')->format('d M (D)');
                $mood = match ($h->mood_value) {
                    3 => 'Good 😊',
                    2 => 'Okay 😐',
                    1 => 'Bad 😔',
                    default => 'Unknown',
                };
                $desc = $h->description ?: 'No note provided.';
                return "- {$date}: {$mood}. Note: {$desc}";
            })->implode("\n");

            $monthName = $startOfMonthIST->format('F Y');

            // Get prompt from database or use default
            $promptTemplate = \App\Models\AppSetting::getValue('monthly_summary_prompt', 'Create a polished and professional monthly emotional summary for the user based on their daily mood entries.

Month: {monthName}

Daily Entries:
{entries}

Writing Guidelines:
- Do NOT start with any greeting (no "Hi", "Hello", "Hey", etc.).
- Do NOT speak directly to the user.
- Start immediately with a clear insight about the month.
- Use a neutral, warm, and professional tone.
- Summarize the overall emotional trend for the month.
- Highlight periods of consistency, improvements, or challenges.
- Provide gentle encouragement without sounding overly casual.
- Avoid repeating words or notes exactly from the user\'s entries.
- Keep the summary concise: 4–6 sentences maximum.
- End with an uplifting, forward-looking statement.');

            $prompt = str_replace(['{monthName}', '{entries}'], [$monthName, $entries], $promptTemplate);

            try {
                $response = \OpenAI\Laravel\Facades\OpenAI::responses()->create([
                    'model' => env('OPENAI_DEFAULT_MODEL', 'gpt-4.1-mini'),
                    'input' => $prompt,
                ]);

                $summaryText = '';
                if (!empty($response->output)) {
                    foreach ($response->output as $item) {
                        foreach ($item->content ?? [] as $c) {
                            $summaryText .= $c->text ?? '';
                        }
                    }
                }

                $summaryText = trim($summaryText) ?: 'No summary generated.';
            } catch (\Throwable $e) {
                \Log::error("MonthlySummary: Failed for user {$user->id}. Error: " . $e->getMessage());
                $summaryText = 'Error generating summary.';
            }

            // ✅ Save monthly summary
            \App\Models\MonthlySummary::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'year'    => $startOfMonthIST->year,
                    'month'   => $startOfMonthIST->month,
                ],
                [
                    'month_label' => $monthName,
                    'summary'     => $summaryText,
                ]
            );

			// ✅ Check if monthly summary email already sent for this month to prevent duplicates
            $monthStartDate = $startOfMonthIST->toDateString();
            $monthEndDate = $endOfMonthIST->toDateString();
            $emailAlreadySent = \App\Models\IotNotification::where('to_bubble_user_id', $user->id)
                ->where('notificationType', 'monthly-summary')
                ->whereBetween('created_at', [$monthStartDate . ' 00:00:00', $monthEndDate . ' 23:59:59'])
                ->exists();

            if ($emailAlreadySent) {
                Log::warning("⛔ Email NOT sent — monthly summary already sent this month for user {$user->id}");
                continue;
            }

            try {
                $oneSignal = new OneSignalService();

                $emailBody = view('emails.monthly-summary', [
                    'user'       => $user,
                    'summaryText'=> $summaryText,
                    'monthName'  => $monthName,
                ])->render();

                $oneSignal->registerEmailUserFallback($user->email, $user->id, [
                    'subject' => "Tribe365 Monthly Summary ({$monthName})",
                    'body'    => $emailBody,
                ]);

                // Store notification in database
                $this->storeNotification(
                    $user->id,
                    'monthly-summary',
                    "Tribe365 Monthly Summary ({$monthName})",
                    "Your monthly emotional summary for {$monthName} has been generated.",
                    null,
                    $today
                );

                Log::info("✅ OneSignal monthly email sent and notification stored for user {$user->id}");
            } catch (\Throwable $e) {
                Log::error("❌ OneSignal monthly email failed for user {$user->id}: {$e->getMessage()}");
            }

            \Log::info("MonthlySummary: Generated successfully for user {$user->id} ({$monthName}).");
        }

        \Log::channel('daily')->info("✅ Monthly summaries generated successfully for " . count($users) . " users.");
        $this->info("✅ Monthly summaries generated successfully for " . count($users) . " users.");
    }
}