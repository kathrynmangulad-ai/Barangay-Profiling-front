# Barangay Profiling and Document Request System

A PHP + MySQL web application for managing barangay resident profiling, blotter
records, incident reports, and document release workflows.

Built to run on a local XAMPP stack. Plain PHP — no Composer or npm.

---

## Requirements

- XAMPP (Apache + MySQL + PHP 7.4+)
- A MySQL database named `brgy_system`

## Installation

1. Place the project in your XAMPP `htdocs` directory (so it runs at
   `http://localhost/barangay_system/`).

2. Create your local environment file (not committed to the repo):

   ```bash
   copy config\.env.example config\.env
   ```

   Then edit `config/.env` and set your database and mail credentials. The
   defaults (`root` / empty password / `brgy_system`) match a stock XAMPP
   install. If `config/.env` is missing, the app falls back to those defaults.

3. Create the database and import the schema, then every migration in order
   (see `database/README.md` for details):

   ```bash
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS brgy_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root brgy_system < database/schema.sql
   mysql -u root brgy_system < database/migrations/001_auth.sql
   mysql -u root brgy_system < database/migrations/002_rbac.sql
   mysql -u root brgy_system < database/migrations/003_email.sql
   mysql -u root brgy_system < database/migrations/004_soft_delete.sql
   mysql -u root brgy_system < database/migrations/005_yearly_stats.sql
   ```

   On Windows use the full path, e.g. `C:\xampp\mysql\bin\mysql.exe`.

4. Create the first admin account by running the bootstrap script from the
   command line (it is blocked from the browser):

   ```bash
   php tools/create_admin.php
   ```

   Delete or keep `tools/` blocked afterwards — both creation scripts contain
   default passwords and are protected by `tools/.htaccess`.

5. Visit `http://localhost/barangay_system/` (or `.../auth/login.php`).

## Project structure

```
barangay_system/
  index.php              Front door -> login or the correct dashboard
  config/                config.php (BASE_PATH, BASE_URL, url()), .env, .env.example   [web-denied]
  includes/              env.php, auth.php, mailer.php (shared logic)                   [web-denied]
  partials/              header, footer, auth_top, auth_bottom
  auth/                  login, logout, register, forgot_password, reset_password
  admin/                 users, user_edit, barangays, yearly_capture
  secretary/             resident_accounts
  resident/              resident_dashboard, resident_profile, document_request, blotter_request
  pages/                 dashboard, residents, resident_add/edit, documents,
                         document_print, blotters, blotter_add, incident_report
  actions/               POST/GET handlers: *_delete, *_restore, process/release_document, save_incident
  database/              schema.sql, migrations/00X_*.sql, README.md                    [web-denied]
  tools/                 create_admin, create_secretary, assign_roles, test_rbac, ...   [web-denied]
  assets/                css/, js/, img/
  uploads/               resident photos (script execution blocked via .htaccess)
  _archive/              superseded / dead files kept for reference (see below)
  *.php (root)           thin backward-compat redirect stubs for old URLs
```

### Paths and URLs

- `config/config.php` defines two anchors used everywhere:
  - `BASE_PATH` — filesystem root, for `require`/`include` and file operations.
  - `BASE_URL` — web root path (from `APP_BASE_URL`), for links and redirects.
  - Helpers: `url('admin/users.php')` and `asset('css/style.css')`.
- Pages were reorganized into folders. The old flat URLs still work: each old
  root file (e.g. `/users.php`) is a 301 redirect stub to its new home
  (e.g. `/admin/users.php`), preserving the query string. New code should link
  with `url()` and the foldered path.

## Roles and access control

Three roles — `admin`, `secretary`, `resident`. Every page calls a guard
(`require_login()`, `require_admin()`, `require_staff()`, or
`require_role([...])`) that was preserved during the reorganization. Barangay
scoping restricts secretaries/residents to their own barangay's records.

## Security notes

- Secrets live in `config/.env` (git-ignored). Commit `config/.env.example`
  (placeholders only). The old flat `config.php` is also git-ignored.
- `config/`, `includes/`, `database/`, and `tools/` each ship a `.htaccess`
  that denies all direct web access.
- `uploads/.htaccess` disables script execution so a disguised upload cannot run.
- Passwords are hashed with `password_hash()` (`PASSWORD_DEFAULT`).
- All mutating forms carry a CSRF token validated by `require_csrf()`;
  state-changing GET actions validate a token via `csrf_verify_get()`.
- Session cookies are `HttpOnly` + `SameSite=Lax` with strict mode. Set
  `'secure' => true` in the session cookie params when serving over HTTPS.
- Login attempts are throttled (rolling window, per-username and per-IP).
- `tools/` scripts are dev/setup/test only and are blocked from the web;
  `create_admin`/`create_secretary` additionally require an admin session.

## _archive/

Not deleted — kept for your review:

| File | Why archived |
|------|--------------|
| `brgy_system-1.sql` | Older full dump; `database/schema.sql` is authoritative |
| `approved_tab.html` | Orphan HTML fragment, unreferenced |
| `sync.php` | Dead dev script that copied a file from another machine |
| `probe_test.ps1`, `probe_users.ps1` | Local PowerShell probes, not web files |
