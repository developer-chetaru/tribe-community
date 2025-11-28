# CloudPanel Cron Job Setup Guide

## 🎯 Problem
The Laravel scheduler is not running because no cron job is configured in CloudPanel. The cron job needs to be added through the CloudPanel web interface.

## 📋 Steps to Add Cron Job in CloudPanel

### Step 1: Access CloudPanel
1. Log into CloudPanel at: `69.62.122.238:8443`
2. Navigate to **Sites** → **community.tribe365.co**
3. Click on the **"Cron Jobs"** tab

### Step 2: Add New Cron Job
1. Click the blue **"Add Cron Job"** button
2. Fill in the following details:

#### Cron Job Configuration:

**Name:**
```
Laravel Scheduler
```

**Schedule:**
```
* * * * *
```
(This means: every minute)

**Command:**
```
cd /home/tribe365-community/community.tribe365.co && /usr/bin/php artisan schedule:run >> /home/tribe365-community/community.tribe365.co/storage/logs/cron.log 2>&1
```

**OR** (if the path is different, use this format):
```
cd /path/to/your/laravel/project && /usr/bin/php artisan schedule:run >> /path/to/your/laravel/project/storage/logs/cron.log 2>&1
```

### Step 3: Find the Correct Path

To find the exact path on your server, you can:

**Option A: Check via SSH**
```bash
# SSH into the server as the site user
sudo su - tribe365-community

# Find the Laravel project path
pwd
# or
ls -la ~/
```

**Option B: Check via CloudPanel File Manager**
1. Go to **File Manager** in CloudPanel
2. Navigate to where your Laravel project is located
3. Note the full path shown in the address bar

**Option C: Common CloudPanel Paths**
- `/home/tribe365-community/community.tribe365.co/`
- `/home/tribe365-community/public_html/`
- `/home/tribe365-community/htdocs/`

### Step 4: Verify PHP Path

The PHP path should be: `/usr/bin/php`

To verify:
```bash
which php
```

### Step 5: Test the Cron Job

After adding the cron job:

1. **Wait 1-2 minutes** for the cron to run
2. **Check the cron log:**
   ```bash
   tail -f /home/tribe365-community/community.tribe365.co/storage/logs/cron.log
   ```
3. **Check Laravel logs:**
   ```bash
   tail -f /home/tribe365-community/community.tribe365.co/storage/logs/laravel.log
   ```

## 🔍 Alternative: Manual Test

Before setting up the cron, you can test manually via SSH:

```bash
# SSH into server
sudo su - tribe365-community

# Navigate to project
cd /path/to/your/laravel/project

# Run scheduler manually
/usr/bin/php artisan schedule:run

# Check if it logs
tail -f storage/logs/laravel.log | grep -i scheduler
```

## 📝 Complete Cron Command Template

Replace `/path/to/your/laravel/project` with your actual path:

```bash
cd /path/to/your/laravel/project && /usr/bin/php artisan schedule:run >> /path/to/your/laravel/project/storage/logs/cron.log 2>&1
```

## ⚠️ Important Notes

1. **Path must be absolute** - CloudPanel cron jobs need full paths
2. **PHP path** - Use `/usr/bin/php` or find with `which php`
3. **Permissions** - Ensure the site user has write access to `storage/logs/`
4. **Log rotation** - Consider setting up log rotation for `cron.log`

## 🐛 Troubleshooting

### If cron still doesn't run:

1. **Check CloudPanel cron logs:**
   - Look for errors in CloudPanel's cron execution logs

2. **Verify file permissions:**
   ```bash
   chmod -R 775 storage/logs/
   chown -R tribe365-community:tribe365-community storage/logs/
   ```

3. **Test command manually:**
   ```bash
   cd /path/to/project
   /usr/bin/php artisan schedule:run
   ```

4. **Check if scheduler is working:**
   ```bash
   /usr/bin/php artisan schedule:list
   ```

5. **Verify Laravel can write logs:**
   ```bash
   /usr/bin/php artisan tinker
   # Then run:
   \Illuminate\Support\Facades\Log::info('Test log entry');
   # Check storage/logs/laravel.log
   ```

## 📊 Expected Log Output

After setup, you should see in `storage/logs/cron.log`:
```
Running scheduled command: notification:send --only=notification
Running scheduled command: notification:send --only=report
```

And in `storage/logs/laravel.log`:
```
[2025-XX-XX XX:XX:XX] local.INFO: Laravel Scheduler: schedule() method called at ...
[2025-XX-XX XX:XX:XX] local.INFO: Cron started for --only=notification at ...
```

