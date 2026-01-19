# OneSignal Tag Sync Documentation

## Overview
Yeh system automatically sabhi active users ke tags ko OneSignal par sync karta hai har minute.

## Command
```bash
php artisan onesignal:sync-all-tags
```

## Cron Schedule
**Frequency:** Har 5 minute (Every 5 minutes)
```php
Schedule::command('onesignal:sync-all-tags')
        ->everyFiveMinutes()
        ->withoutOverlapping()
        ->appendOutputTo(storage_path('logs/onesignal-sync.log'));
```

**Note:** Pehle ye har minute run hota tha, but OneSignal API rate limit ki wajah se ab har 5 minute run hota hai.

## Tags Jo Sync Hote Hain

### 1. `user_type`
- **Values:** `org` ya `basecamp`
- **Logic:** Agar user ka `orgId` hai to `org`, otherwise `basecamp`

### 2. `has_working_today`
- **Values:** `true` ya `false`
- **Logic:** Check karta hai ki aaj user ke organisation ke liye working day hai ya nahi
- **Depends on:** Organisation ke `working_days` setting

### 3. `timezone`
- **Value:** User ka timezone (e.g., `Asia/Kolkata`)
- **Default:** `Asia/Kolkata` if not set

### 4. `has_submitted_today`
- **Values:** `true` ya `false`
- **Logic:** Check karta hai ki user ne aaj sentiment/happy index submit kiya hai ya nahi
- **Table:** `happy_indexes`

### 5. `email_subscribed`
- **Values:** `true` ya `false`
- **Default:** `true`

### 6. `status`
- **Values:** User status
  - `active_verified`
  - `active_unverified`
  - `pending_payment`
  - `suspended`
  - `cancelled`
  - `inactive`

### 7. `name`
- **Value:** Full name (first_name + last_name)
- **Default:** `Unknown` if both names are empty

### 8. `first_name`
- **Value:** User's first name

### 9. `last_name`
- **Value:** User's last name

## Active Users Definition
Command sirf un users ko sync karta hai jo:
- `status` in `['active_verified', 'active_unverified', 'pending_payment']`

## Logs
- **Location:** `storage/logs/onesignal-sync.log`
- **Format:** Har run ka detail log hota hai

## Manual Run
Agar manually run karna ho:
```bash
# Normal sync
php artisan onesignal:sync-all-tags

# Force sync (all users)
php artisan onesignal:sync-all-tags --force
```

## Test Run Results
```
✅ Sync completed:
+-------------+-------+
| Metric      | Count |
+-------------+-------+
| Total Users | 21    |
| Synced      | 21    |
| Failed      | 0     |
| Skipped     | 0     |
+-------------+-------+
```

## Benefits
1. **Regular Updates:** Tags har 5 minute update hote hain
2. **Accurate Targeting:** Push notifications accurate tags ke basis par bhej sakte hain
3. **Status Tracking:** User status changes quickly reflect hote hain
4. **Sentiment Tracking:** Daily sentiment submission status regularly track hoti hai
5. **Rate Limit Safe:** 50ms delay between users aur 5-minute frequency se API rate limits avoid hote hain

## Related Commands

### Update Working Day Status (Timezone-based)
```bash
# Midnight update
php artisan onesignal:update-working-day-status --time=00:00

# 11:10 AM update
php artisan onesignal:update-working-day-status --time=11:10
```

### Reset Sentiment Tag
```bash
php artisan onesignal:reset-sentiment-tag
```

## Troubleshooting

### Check Logs
```bash
tail -f storage/logs/onesignal-sync.log
```

### Check if Cron is Running
```bash
php artisan schedule:list
```

### Manually Verify OneSignal Tags
1. Login to OneSignal Dashboard
2. Go to Audience > All Users
3. Click on any user
4. Check "Tags" section

## Configuration
OneSignal credentials:
- **File:** `.env`
- **Keys:**
  - `ONESIGNAL_APP_ID`
  - `ONESIGNAL_REST_API_KEY`

## Notes
- Command `withoutOverlapping()` use karta hai, matlab ek time par sirf ek instance run hoga
- Failed updates log mein record hote hain
- User ko OneSignal mein create karta hai agar exist nahi karta
- Har user ke beech 50ms delay hai to avoid API rate limiting
- Command har 5 minutes run hota hai to avoid OneSignal API rate limits (429 errors)
