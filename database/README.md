# Database

SQL for the Barangay Profiling and Document Request System.

## Files

- `schema.sql` — the complete database structure (single source of truth). All
  historical migrations are folded in, so there are no incremental migration
  files to track. Everyone on the team runs this one file and gets an identical
  structure.
- `seed.sql` — reference data (the 31 barangays) plus a bootstrap admin account.

## Setup (fresh database)

```bash
mysql -u root -e "CREATE DATABASE IF NOT EXISTS brgy_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root brgy_system < schema.sql
mysql -u root brgy_system < seed.sql
```

On Windows (XAMPP) use the full path to mysql, e.g.
`C:\xampp\mysql\bin\mysql.exe`.

## Bootstrap login

`seed.sql` creates one administrator:

| Username | Password | Role |
|----------|----------|------|
| `admin`  | `admin123` | admin |

**Change this password immediately after the first login.** The hash in
`seed.sql` is a bcrypt hash of `admin123` and is intended only to get you in the
first time.

## Re-running

- `schema.sql` **drops and recreates every table** (`DROP TABLE IF EXISTS` +
  `CREATE TABLE`). Running it wipes all data — only use on a fresh/rebuilt DB.
- `seed.sql` is **safe to re-run**: it uses `ON DUPLICATE KEY` for barangays and
  `INSERT IGNORE` for the admin, so it will not create duplicates or overwrite a
  changed admin password.

## Tables

| Table | Purpose |
|-------|---------|
| `barangays` | Reference list of barangays |
| `users` | Accounts (admin / secretary / resident); `deleted_at` = soft delete |
| `residents` | Resident profile records; `deleted_at` = soft delete |
| `households` | Households (one row each) with the head of the family; `residents.household_id` binds every resident to their household |
| `document_requests` | Certificate / clearance requests + workflow |
| `document_status_history` | Audit trail of document status changes |
| `blotter_records` | Blotter / incident reports |
| `access_log` | RBAC / security audit log |
| `login_attempts` | Brute-force throttling (per-identifier and per-IP) |
| `password_reset_tokens` | Single-use, hashed, time-limited reset tokens |
| `barangay_yearly_stats` | Frozen per-year, per-barangay snapshots (barangay_id 0 = all) |

## Notes

- `schema.sql` was regenerated from the live, migration-applied database and then
  tidied: the legacy unused `users.brgy_name` column and its constraint were
  dropped, `residents.barangay_id` width corrected to `int(11)`, and the
  `is_pwd` / `is_student` defaults set to `'No'`. The app sets those fields
  explicitly, so behavior is unchanged.
- The older full dump `_archive/brgy_system-1.sql` is kept for reference only.
