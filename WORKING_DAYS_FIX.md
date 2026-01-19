# Working Days & has_working_today Fix - RESOLVED

## 🎯 Issue
`has_working_today` tag was showing `true` on OneSignal even though Monday was OFF for Organisation ID 87.

## 🔍 Root Cause
The organisation's `working_days` field in the database still contained "Mon" even though it should have been removed.

**Before Fix:**
```json
{
  "working_days": ["Mon", "Tue", "Wed", "Thu", "Fri"]
}
```

**After Fix:**
```json
{
  "working_days": ["Tue", "Wed", "Thu", "Fri"]
}
```

## ✅ Solution Applied

### 1. Database Update
```sql
-- Organisation ID: 87 (Chetaru)
-- Removed "Mon" from working_days array
UPDATE organisations 
SET working_days = '["Tue","Wed","Thu","Fri"]' 
WHERE id = 87;
```

### 2. Verification
```bash
php artisan tinker
$user = User::find(328);
$oneSignal = app(App\Services\OneSignalService::class);
$isWorking = $oneSignal->isWorkingDayToday($user);
# Result: FALSE ✓ (correct!)
```

### 3. OneSignal Sync
```bash
# Manual sync to immediately update OneSignal
php artisan onesignal:sync-all-tags

# Or wait for cron (runs every 5 minutes)
```

## 📊 Test Results

### Before Fix:
```
Working Days: ["Mon", "Tue", "Wed", "Thu", "Fri"]
Today: Monday (Mon)
has_working_today: TRUE ❌ (incorrect)
```

### After Fix:
```
Working Days: ["Tue", "Wed", "Thu", "Fri"]
Today: Monday (Mon)
has_working_today: FALSE ✓ (correct!)
Sync to OneSignal: SUCCESS ✓
```

## 🎯 How to Update Working Days via UI

If you need to change working days through the admin interface:

1. **Login to Admin Panel**
   - URL: `community.tribe365.co/organisations/update/87`

2. **Update Working Days**
   - Click on the days you want to ENABLE (they turn red)
   - Unselected days (white) are OFF days
   - Example: Mon=white, Tue-Fri=red, Sat-Sun=white

3. **IMPORTANT: Click "Update" Button**
   - Changes only save when you click "Update"
   - Without clicking Update, database won't be updated

4. **Verify in Database**
   ```bash
   php artisan tinker
   $org = Organisation::find(87);
   echo json_encode($org->working_days);
   ```

5. **Wait for Sync**
   - Cron runs every 5 minutes
   - Or manually run: `php artisan onesignal:sync-all-tags`

6. **Check OneSignal Dashboard**
   - Go to: Audience > Users > user_328
   - Verify `has_working_today` matches expected value

## 🔧 Technical Details

### Code Location: `app/Services/OneSignalService.php`
```php
public function isWorkingDayToday($user): bool
{
    // For org users, check organisation's working_days
    if (!$user->organisation->working_days) {
        return true; // default
    }

    $workingDays = is_array($user->organisation->working_days) 
        ? $user->organisation->working_days 
        : json_decode($user->organisation->working_days, true);

    // Get today's day name in user's timezone
    $todayName = TimezoneHelper::carbon(null, $user->timezone)->format('D');
    
    // Check if today is in working days array
    return in_array($todayName, $workingDays);
}
```

### Logic:
1. Check if user has an organisation (org users vs basecamp users)
2. Get organisation's `working_days` array
3. Get today's day name in user's timezone (Mon, Tue, Wed, etc.)
4. Return `true` if today is IN the array, `false` otherwise

### Example:
```php
// Organisation working_days: ["Tue", "Wed", "Thu", "Fri"]
// Today: Monday (Mon)
// Result: in_array("Mon", ["Tue", "Wed", "Thu", "Fri"]) = FALSE ✓
```

## 📋 Troubleshooting

### If has_working_today is still wrong:

1. **Check Database**
   ```bash
   php artisan tinker
   $org = Organisation::find(87);
   var_dump($org->working_days);
   ```

2. **Check User's Organisation**
   ```bash
   $user = User::find(328);
   echo $user->orgId; // Should be 87
   ```

3. **Test Function Manually**
   ```bash
   $user = User::with('organisation')->find(328);
   $oneSignal = app(App\Services\OneSignalService::class);
   var_dump($oneSignal->isWorkingDayToday($user));
   ```

4. **Force Sync to OneSignal**
   ```bash
   php artisan onesignal:sync-all-tags
   ```

5. **Check Logs**
   ```bash
   tail -f storage/logs/onesignal-sync.log
   tail -f storage/logs/laravel.log | grep OneSignal
   ```

## 🎯 Success Criteria

- ✅ Database `working_days` matches intended schedule
- ✅ `isWorkingDayToday()` returns correct boolean
- ✅ OneSignal sync completes successfully
- ✅ OneSignal dashboard shows correct `has_working_today` value
- ✅ Cron runs every 5 minutes without errors

## 📝 Related Files

1. `app/Models/Organisation.php` - Model with `working_days` cast
2. `app/Services/OneSignalService.php` - `isWorkingDayToday()` logic
3. `app/Livewire/UpdateOrganisation.php` - UI for updating working days
4. `app/Console/Commands/SyncAllUserTagsToOneSignal.php` - Sync command
5. `routes/console.php` - Cron schedule (every 5 minutes)

## ✅ Status: RESOLVED

Organisation ID 87's working_days has been corrected. Monday is now properly marked as OFF, and `has_working_today` will correctly return `false` on Mondays.

---

**Fixed:** 2026-01-19  
**Organisation:** Chetaru (ID: 87)  
**Test User:** user_328 (Prahalad Singh)
