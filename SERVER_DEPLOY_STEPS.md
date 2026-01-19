# Server Deployment Steps - OneSignal Fix

## 🚀 Quick Deploy Commands

SSH into server aur ye commands run karo:

### 1. Pull Latest Code
```bash
cd /home/tribe365-community/community.tribe365.co
git pull origin main
```

### 2. Clear Cache
```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
```

### 3. Verify Scheduler
```bash
php artisan schedule:list | grep onesignal
```
**Expected Output:**
```
*/5 * * * *  php artisan onesignal:sync-all-tags  Next Due: X minutes from now
```

### 4. Test Sync Manually
```bash
php artisan onesignal:sync-all-tags
```
**Expected Output:**
```
✅ Sync completed:
Total Users: XX
Synced: XX
Failed: 0
```

### 5. Check Cron Logs
```bash
# Check sync logs
tail -f storage/logs/onesignal-sync.log

# Check Laravel logs for errors
tail -f storage/logs/laravel.log | grep -i onesignal
```

### 6. Verify Cron is Running
```bash
# Check if cron job exists in CloudPanel
# Login to CloudPanel: 69.62.122.238:8443
# Go to Sites → community.tribe365.co → Cron Jobs
# Verify this cron exists:
* * * * * cd /home/tribe365-community/community.tribe365.co && /usr/bin/php artisan schedule:run >> /home/tribe365-community/community.tribe365.co/storage/logs/cron.log 2>&1
```

### 7. Wait and Verify
```bash
# Wait 5 minutes, then check logs
tail -20 storage/logs/onesignal-sync.log

# Should see:
# ✅ Sync completed: Total Users: XX, Synced: XX, Failed: 0
```

## 🔍 Troubleshooting

### If cron not running:
1. Check CloudPanel cron job is added (see CLOUDPANEL_CRON_SETUP.md)
2. Check file permissions:
   ```bash
   chmod -R 775 storage/logs/
   ```
3. Manually test scheduler:
   ```bash
   php artisan schedule:run
   ```

### If sync failing:
1. Check OneSignal API credentials:
   ```bash
   php artisan tinker
   config('services.onesignal.app_id')
   config('services.onesignal.rest_api_key')
   ```
2. Check Laravel logs:
   ```bash
   tail -50 storage/logs/laravel.log | grep -i onesignal
   ```

### If has_working_today not updating:
1. Check user's organisation working_days:
   ```bash
   php artisan tinker
   $user = User::with('organisation')->find(328);
   echo $user->organisation->working_days;
   ```
2. Test manually:
   ```bash
   php artisan tinker
   $user = User::find(328);
   $oneSignal = app(App\Services\OneSignalService::class);
   $oneSignal->setUserTagsOnLogin($user);
   ```

## ✅ Verification Checklist

- [ ] Code pulled from git
- [ ] Cache cleared
- [ ] Scheduler shows every 5 minutes
- [ ] Manual test successful
- [ ] Logs show successful syncs
- [ ] No 429 (rate limit) errors
- [ ] OneSignal dashboard shows updated tags
- [ ] `has_working_today` matches working days

## 📝 What Changed

### Files Modified:
1. ✅ `routes/console.php` - Changed from `everyMinute()` to `everyFiveMinutes()`
2. ✅ `app/Console/Commands/SyncAllUserTagsToOneSignal.php` - Added 50ms delay between users
3. ✅ `ONESIGNAL_TAG_SYNC_README.md` - Updated documentation

### Impact:
- API calls reduced from 1260/hour to 252/hour (80% reduction)
- No more 429 (rate limit) errors
- Tags properly update every 5 minutes
- `has_working_today` correctly reflects organisation working days

## 🎯 Success Criteria

After deployment, within 5 minutes you should see:
1. ✅ No 429 errors in logs
2. ✅ Sync completes successfully
3. ✅ OneSignal tags updated for org users
4. ✅ `has_working_today` matches working days configuration

---

**Last Updated:** 2026-01-19
**Deployed By:** [Your Name]
**Status:** Ready to Deploy
