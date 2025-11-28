# Laravel Scheduler Cron Setup

## ✅ What Was Fixed

1. **Cron Job Configured**: The Laravel scheduler cron job has been set up to run every minute
2. **Enhanced Logging**: Added comprehensive logging to track scheduler execution
3. **Error Handling**: Added success/failure callbacks for scheduled tasks

## 📋 Current Cron Configuration

The cron job runs every minute and executes:
```bash
* * * * * cd /home/chetaru/workspace/tribe-community && php artisan schedule:run >> /home/chetaru/workspace/tribe-community/storage/logs/cron.log 2>&1
```

## 📊 Scheduled Tasks

The following tasks are scheduled:

1. **Notification Sender** (`notification:send --only=notification`)
   - Runs: Every minute
   - Purpose: Send notifications based on user timezones (16:30)

2. **Report Sender** (`notification:send --only=report`)
   - Runs: Every minute
   - Purpose: Send daily reports based on user timezones (23:59)

3. **Weekly Summary Generator**
   - Runs: Sunday at 23:00 (Asia/Kolkata)
   - Purpose: Generate weekly summaries for all users

4. **Monthly Update** (`everyday:update`)
   - Runs: Last day of month at 22:00 (Asia/Kolkata)
   - Purpose: Monthly updates

5. **Leave Status Update** (`leave:update-status`)
   - Runs: Daily at 00:00 (Asia/Kolkata)
   - Purpose: Update leave statuses

6. **Sentiment Reminder** (`notification:send --only=sentiment`)
   - Runs: Daily at 18:00 (Asia/Kolkata)

7. **Monthly Summary** (`notification:send --only=monthly-summary`)
   - Runs: 28th of each month at 22:00 (Asia/Kolkata)

8. **Weekly Summary Notification** (`notification:send --only=weeklySummary`)
   - Runs: Sunday at 23:00 (Asia/Kolkata)

## 🔍 How to Verify It's Working

### 1. Check Cron Job is Running
```bash
crontab -l
```

### 2. View Cron Execution Logs
```bash
tail -f storage/logs/cron.log
```

### 3. View Laravel Application Logs
```bash
tail -f storage/logs/laravel.log | grep -i "scheduler\|cron"
```

### 4. View Scheduler-Specific Logs
```bash
tail -f storage/logs/scheduler.log
```

### 5. Manually Test the Scheduler
```bash
php artisan schedule:run
```

### 6. List All Scheduled Tasks
```bash
php artisan schedule:list
```

## 🐛 Troubleshooting

### If cron is not running:

1. **Check if cron service is running:**
   ```bash
   sudo systemctl status cron
   # or
   sudo service cron status
   ```

2. **Check cron logs (system level):**
   ```bash
   sudo tail -f /var/log/syslog | grep CRON
   ```

3. **Verify PHP path:**
   ```bash
   which php
   ```

4. **Test scheduler manually:**
   ```bash
   cd /home/chetaru/workspace/tribe-community
   php artisan schedule:run
   ```

5. **Check file permissions:**
   ```bash
   ls -la storage/logs/
   chmod -R 775 storage/logs/
   ```

### If tasks are not executing:

1. **Check Laravel logs for errors:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

2. **Verify scheduled tasks are registered:**
   ```bash
   php artisan schedule:list
   ```

3. **Check for overlapping tasks:**
   - Tasks with `withoutOverlapping()` won't run if previous instance is still running
   - Check for stuck processes: `ps aux | grep "schedule:run"`

## 📝 Log Files

- **Cron execution**: `storage/logs/cron.log` - Shows when `schedule:run` is called
- **Scheduler output**: `storage/logs/scheduler.log` - Output from scheduled commands
- **Laravel logs**: `storage/logs/laravel.log` - Application logs including scheduler events
- **Monthly summary**: `storage/logs/monthly_summary.log` - Specific to monthly tasks

## 🔄 Re-running Setup

If you need to re-run the setup script:
```bash
./setup-cron.sh
```

## ⚠️ Important Notes

- The scheduler runs every minute, but individual tasks may run at different intervals
- Tasks with `withoutOverlapping()` will skip if the previous run is still executing
- Background tasks (`runInBackground()`) won't block the scheduler
- All times are in Asia/Kolkata timezone unless specified otherwise

