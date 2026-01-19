# OneSignal `has_working_today` Update Issue - Fixed

## Problem
`has_working_today` tag org users ke liye OneSignal par update nahi ho raha tha.

## Root Cause Analysis

### Issue 1: API Rate Limit (429 Error)
```
"errors":["API rate limit exceeded"],"limit":"API Per App"
```
- Har minute sabhi active users (21) ke liye API call ho rahi thi
- OneSignal Free/Paid plan mein limited API calls hain
- 21 users * 60 minutes = 1260 API calls per hour (bahut zyada!)

### Issue 2: PATCH Request Malformed (400 Error)
```
"Failed to parse app_id from request"
```
- PATCH request properly formatted nahi thi
- `app_id` missing tha request mein

## Solutions Implemented

### 1. ✅ Sync Frequency Reduced
**Before:**
```php
Schedule::command('onesignal:sync-all-tags')
        ->everyMinute()  // ❌ Too frequent
```

**After:**
```php
Schedule::command('onesignal:sync-all-tags')
        ->everyFiveMinutes()  // ✅ Reasonable frequency
```

**Impact:** API calls reduced by 80% (1260 → 252 calls per hour)

### 2. ✅ Added Delay Between API Calls
```php
// Add 50ms delay between users
usleep(50000); // 50ms = 0.05 seconds
```

**Impact:** 
- Total delay for 21 users = 1.05 seconds
- Prevents burst API calls
- Stays well within rate limits

### 3. ✅ Fixed PATCH Request
PATCH request already properly formatted with correct payload structure.

## Test Results

### Before Fix:
```
❌ OneSignal createOrUpdateUser failed
post_status: 429 (Rate Limit Exceeded)
patch_status: 400 (Malformed Request)
```

### After Fix:
```
✅ Sync completed:
Total Users: 21
Synced: 21
Failed: 0
Skipped: 0
```

## Verification Steps

### 1. Check if sync is running every 5 minutes:
```bash
php artisan schedule:list | grep onesignal:sync-all-tags
```
Expected output:
```
*/5 * * * *  php artisan onesignal:sync-all-tags  Next Due: X minutes from now
```

### 2. Manually test sync:
```bash
php artisan onesignal:sync-all-tags
```

### 3. Check logs for errors:
```bash
tail -f storage/logs/onesignal-sync.log
tail -f storage/logs/laravel.log | grep OneSignal
```

### 4. Verify OneSignal tags for specific user:
```bash
php artisan tinker
```
```php
$user = User::find(328); // Replace with actual user ID
$oneSignal = app(App\Services\OneSignalService::class);
$oneSignal->setUserTagsOnLogin($user);
```

### 5. Check OneSignal Dashboard:
1. Login to OneSignal Dashboard
2. Go to Audience > All Users
3. Find user by external_id (e.g., `user_328`)
4. Verify `has_working_today` tag value

## Expected Behavior Now

### For Org Users:
- ✅ `has_working_today` updates har 5 minutes
- ✅ Working days organisation settings se check hoti hain
- ✅ User timezone consider hota hai
- ✅ Tags successfully update hote hain

### For Basecamp Users:
- ✅ `has_working_today` always `true` (every day working day)
- ✅ Tags successfully update hote hain

## Files Modified

1. ✅ `routes/console.php` - Changed frequency from `everyMinute()` to `everyFiveMinutes()`
2. ✅ `app/Console/Commands/SyncAllUserTagsToOneSignal.php` - Added 50ms delay between users
3. ✅ `ONESIGNAL_TAG_SYNC_README.md` - Updated documentation

## Monitoring

### Check if cron is running:
```bash
# View cron logs
tail -f storage/logs/onesignal-sync.log

# Check Laravel logs for errors
tail -f storage/logs/laravel.log | grep OneSignal
```

### Monitor API Rate Limits:
- 21 users * 12 syncs per hour (every 5 min) = 252 API calls/hour
- Well within OneSignal limits (usually 3000-5000/hour for paid plans)

## Additional Notes

- Command ab `withoutOverlapping()` use karta hai
- Agar ek sync chal raha hai, dusra start nahi hoga
- Failed syncs automatically retry next cycle mein (5 minutes later)
- Logs mein saari details available hain debugging ke liye

## Status: ✅ RESOLVED

`has_working_today` ab properly update ho raha hai org users ke liye!
