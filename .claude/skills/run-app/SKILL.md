---
name: run-app
description: Launch the Barangay system locally (run, start, open or screenshot the app). One script starts the database and web server and opens the browser.
---

# Run the app

This Mac has no XAMPP or MySQL. The app runs on PHP's built-in server with
MariaDB in a Docker container. One script does everything and skips steps that
are already done:

```bash
.claude/skills/run-app/run.sh            # start + open browser
.claude/skills/run-app/run.sh --no-open  # start only
.claude/skills/run-app/run.sh status
.claude/skills/run-app/run.sh stop       # data is kept
```

- URL: http://127.0.0.1:8000/ (also http://localhost:8000/)
- Login: `admin` / `admin123` (seeded bootstrap admin; demo residents are `demo.*`)
- Logs: `$TMPDIR/brgy-run/`

Run the script, then report the URL and login. Do not rediscover the setup.

## What the script handles (do not undo these)

- Starts Docker Desktop if the daemon is down (about 25s; the only slow step).
- Reuses the `brgy-db` container; creates it with a data volume if missing and
  imports `database/schema.sql` + `database/seed.sql` only when the database is
  empty. `schema.sql` drops every table, so never import it by hand into a
  database that has data.
- Starts two PHP listeners, `127.0.0.1:8000` and `[::1]:8000`. `php -S localhost`
  binds IPv6 only and the browser then fails on 127.0.0.1.
- Passes `APP_BASE_URL=http://localhost:8000` as an environment variable so the
  app serves from `/`. No `config/.env` is needed or written.

## Driving it

Log in with curl: GET `/auth/login.php` with a cookie jar, read the hidden
`csrf_token`, POST it with `username` and `password`; a 302 to
`/pages/dashboard.php` means success.

## Limits

- Apache `.htaccess` rules (web-deny on `config/`, `tools/`, `uploads/`) are not
  enforced by the built-in server.
- Mail is unconfigured, so password-reset emails do not send.
