# Happy Index — SOP implementation (step by step)

This document tracks the Happy Index / Sentiment module improvements from the internal SOP, in deployment order. Update the checkboxes as you merge.

## Status legend

- [x] Done in repo
- [ ] Not done / partial

---

## Phase 1 — Security & data integrity (do first)

| Step | Item | Notes |
|------|------|--------|
| 1.1 | [x] Authenticate `POST /api/add-happy-index` | Route under `auth:api` + `validate.jwt`. Controller uses `auth('api')->id()`; body `userId` ignored. |
| 1.2 | [x] OpenAPI `storage/api-docs/api-docs.json` | `userId` not required; `bearerAuth` + `401`. Regenerate with `php artisan l5-swagger:generate` if you use that flow. |
| 1.3 | [x] DB unique: one row per user per calendar day (UTC `created_at` date) | Migration `2026_04_15_120000_add_unique_daily_submission_to_happy_indexes_table.php`: MySQL virtual `submission_date` + unique; SQLite expression index. **Note:** App still enforces timezone-aware “one local day”; DB constraint blocks duplicate rows same UTC date. |

**Verify:** `POST /api/add-happy-index` without `Authorization` → 401. With token, mood saved for token user only.

---

## Phase 2 — Bugs & fragile UI

| Step | Item | Notes |
|------|------|--------|
| 2.1 | [x] `MonthlySummary::generateMonthlySummary` user lookup | Uses `Auth::user()`; if `userId` argument ≠ logged-in user, method returns early (no `Auth::user()->where` bug). |
| 2.2 | [x] Out-of-office disable modal | Alpine + `wire:click`; removed legacy DOM script. |
| 2.3 | [x] Sentiment reminder email subject | `EveryDayUpdate.php`: OneSignal + `storeNotification` + AI mail path use `Reminder (d M): Please Update Your Sentiment Index` via `now()->format('d M')`. |

---

## Phase 3 — Dashboard UX (`DashboardSummary` + `dashboard-summary.blade.php`)

| Step | Item | Notes |
|------|------|--------|
| 3.1 | [x] Submission window message | `nextWindowTime` + copy when window closed / before 16:00 on eligible days. |
| 3.2 | [x] Past day, no submission | Em dash instead of sad icon. |
| 3.3 | [x] Day detail modal | Mood label (no raw score). |
| 3.4 | [x] Mood note validation + counter | Happy optional; 1/2 required min 5; counter in Blade. |
| 3.5 | [x] Department filter | `wire:model.live`. |
| 3.6 | [x] Streak banner | `currentStreak` when > 0. |

---

## Phase 4 — Summaries & reporting

| Step | Item | Notes |
|------|------|--------|
| 4.1 | [x] Weekly summary manual generate | `WeeklySummary::generateWeeklySummary()` + buttons + session flashes. |
| 4.2 | [x] `without_weekend` in `AdminReportController` | `calculateHappyIndexGraph(..., $excludeWeekend)` filters weekday `created_at` (MySQL `WEEKDAY`, SQLite `strftime`, PostgreSQL `EXTRACT(DOW)`). `upsertHappyIndex` persists `with_weekend` + `without_weekend`. |
| 4.3 | [x] `EngagementReportService::individualUserEngageHappyIndexReport` | Completed / hardened: `officeId` / `departmentId` / HI flags from `$perArray` with defaults; early return if no user row for month; default `$getWeekendDates`; `happy_indexes.status` accepts `active` + `Active`; removed stray `;` lines; fixed `|| $firstDate = $currentDate` assignment bug to `==`; explicit `(string)` return. |

---

## Commands

```bash
php artisan migrate
php artisan notification:send --only=sentiment   # if you test reminders
```

---

## Related

- Original SOP: Happy Index Module — Improvement SOP (April 2026)
- Code: `routes/api.php`, `app/Http/Controllers/HappyIndexController.php`, `app/Livewire/DashboardSummary.php`, `resources/views/livewire/dashboard-summary.blade.php`, `app/Livewire/MonthlySummary.php`, `app/Livewire/WeeklySummary.php`, `resources/views/livewire/weekly-summary.blade.php`, `app/Console/Commands/EveryDayUpdate.php`, `app/Http/Controllers/AdminReportController.php`, `app/Services/EngagementReportService.php`
