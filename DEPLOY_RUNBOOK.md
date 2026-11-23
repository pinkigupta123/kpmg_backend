# Production deploy runbook (backend)

Run everything from the `kpmg_backend` folder on the server. Do the steps in order.

---

## 0. Safety first (30 seconds)

```bash
php artisan db:show                      # confirm DRIVER, HOST and DATABASE are the ones you expect
php artisan migrate:status               # which migrations have run / are pending
mysqldump -u USER -p DBNAME > backup_$(date +%F_%H%M).sql   # backup before anything that changes data
```

`migrate:fresh` DELETES every table. Only use it if you really want an empty start.

---

## 1. Pull and install the latest code

```bash
git pull origin main
composer install --no-dev --optimize-autoloader
```

## 2. Clear stale caches (do this BEFORE migrate/seed)

An old cached config can make Laravel read the wrong `.env` or old settings, which is a common reason seeds
"do not take".

```bash
php artisan optimize:clear
```

## 3. Database — choose ONE

**A. Update an existing database (keep data)**
```bash
php artisan migrate --force
```

**B. Completely fresh start (WIPES ALL DATA)**
```bash
php artisan migrate:fresh --force
```

## 4. Create the base data and all users (single command)

```bash
php artisan db:seed --class=ProductionSeeder --force
```

This creates, create-only (safe to re-run, never resets existing passwords or job counters):

- 2 companies x 2 branches
  - KPNG Technologies: BR-001 Karol Bagh, BR-002 Nehru Place
  - HP Showroom Pvt Ltd: BR-003 HP Showroom - Connaught Place, BR-004 HP Showroom - Saket
- Dropdown options (7 groups) and app settings (GST, currency, prefixes)
- Staff users and Lead module users (below)

| Table | Role | Email | Branches |
|---|---|---|---|
| employees | super_admin | admin@kpng.com | BR-001 |
| employees | branch_manager | manager@kpng.com | BR-001 |
| employees | stock_manager | stock@kpng.com | ALL 4 branches |
| employees | employee (technician) | employee@kpng.com | BR-001 + BR-003 (one per company) |
| employees | employee | suresh@kpng.com | BR-002 |
| employees | branch_manager | manager2@kpng.com | BR-003 |
| lead_users | super_admin | leadadmin@kpng.com | - |
| lead_users | sales_executive | sales@kpng.com | - |
| lead_users | super_admin | admin@kpmgleadmanager.com | - |
| lead_users | sales_executive | exec1@kpmgleadmanager.com, exec2@kpmgleadmanager.com | - |

Starting password for all: `ChangeMe@123`. Each user must change it at first login.

> Do NOT run plain `php artisan db:seed` on prod. That also loads demo customers, devices and stock.

## 5. Link storage and rebuild caches

```bash
php artisan storage:link                 # first deploy only (invoice photos are stored here)
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart                # only if a queue worker is running
```

Cron for the scheduler (lead follow-up reminders, daily 09:00 IST):

```
* * * * * cd /path/to/kpmg_backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 6. Verify everything is set (read-only)

```bash
php artisan setup:verify
```

It prints PASS / FAIL for each of these and ends with `All checks passed` or the number of failures:

- the DB it is connected to, plus APP_ENV and debug flag
- required tables exist (including `employee_branch`)
- both companies and all 4 branches exist, 2 branches per company
- the 4 staff roles exist with the right role
- stock manager can access ALL branches
- technician has a branch in each company
- every employee has at least one branch
- lead users exist with the right roles
- all 7 dropdown groups and the key settings exist
- `public/storage` link exists

Manual spot checks if you want to look yourself:

```bash
php artisan db:show --counts                       # every table with row counts
php artisan tinker --execute="dump(App\Models\Branch::pluck('name','code'), App\Models\Employee::pluck('role','email'), App\Models\LeadUser::pluck('role','email'));"
php artisan tinker --execute="dump(App\Models\Employee::where('email','stock@kpng.com')->first()->allBranchIds());"   # expect 4 ids
php artisan migrate:status                         # every line should say Ran
```

Then log in as `stock@kpng.com` and open Inventory > Add Stock: the Branch dropdown should list all 4 branches.

---

## 7. If users were not created correctly (troubleshooting)

| Symptom | Likely cause | Fix |
|---|---|---|
| `setup:verify` says a table is missing, or the seeder errors on `employee_branch` | migrations not run after pulling | `php artisan migrate --force`, then seed again |
| Seeder ran but users or branches look unchanged | wrong database, or cached config pointing to an old DB | `php artisan optimize:clear`, `php artisan db:show`, re-seed |
| Stock manager or technician has only 1 branch | seeded with old code, or branches did not exist yet | `git pull`, re-run step 4 (assignments are added, never removed) |
| Old demo users (HQ - TechFix Central, North Branch) still there | database existed before this setup | wipe with step 3B (after a backup), or edit them in the app |
| `Duplicate entry` on seed | a company, branch or employee with the same code or email already exists with different data | check with `tinker`, then fix or delete the conflicting row and re-seed |
| Login says invalid credentials after the first successful login | the user changed their password (forced at first login) | reset the password in the app, or in `tinker` |

---

## 8. Frontend (`kpmg_techno`) after backend deploy

- Set `VITE_API_URL` to the prod API URL.
- Optional: `VITE_SHOW_DEMO_CREDENTIALS=true` shows the demo-credentials card on the login page. Remove it before real users go live.
- `npm run build`, then deploy the output. Vite reads env vars at build time, so rebuild after any change.
