# Masar (مسار) — AI-Supported Academic Advising Platform

Masar helps Middle East University (MEU) students plan their next semester.
A student uploads the official English academic record (PDF). Masar reads
it, shows academic progress, recommends next-semester courses, and answers
planning questions through the **Ask Masar** assistant.

Graduation project — Faculty of Information Technology, MEU.

## How it works

```
PDF upload ──► validation (type, signature, MEU markers, duplicates)
           ──► text rows rebuilt from PDF coordinates (smalot/pdfparser)
                 ├─► deterministic facts: prerequisites, marks, completion states
                 └─► personal identifiers redacted ──► Gemini (structured JSON)
           ──► PHP validation of every AI suggestion
                 (exists in record · not completed · prerequisites met ·
                  credit hours verified · semester credit limit)
           ──► MySQL ──► My Path dashboard · Recommendations · Ask Masar
```

Rejected AI suggestions are stored with the rejection reason, so the
administrator dashboard can report how often the model was corrected.

## Tech stack

| Layer     | Technology                                     |
|-----------|------------------------------------------------|
| Frontend  | HTML5, CSS3, vanilla JavaScript (EN/AR, RTL, dark mode) |
| Backend   | PHP 8.1+ (no framework), PDO                   |
| Database  | MySQL 8 / MariaDB 10.4+                        |
| PDF       | smalot/pdfparser (Composer)                    |
| AI        | Google Gemini API (`gemini-2.5-flash` by default) |

## Project structure

```
admin/        administrator pages (dashboard, users, links, activity, alerts)
auth/         login, registration, logout
student/      student pages and JSON endpoints
includes/     shared PHP (session, auth, CSRF, AI client, parser, analyzer)
config/       .env loader and database connection
lang/         English and Arabic translations
assets/       CSS, JavaScript, brand assets
database/     schema.sql, seed.sql, migrations/
tools/        command-line tools (create admin, backup, parser tests)
storage/      uploaded records and sessions (never web-accessible)
```

## Installation (Ubuntu / Debian server)

Masar expects to be served at `/masar/` (for example
`/var/www/html/masar`).

```bash
# 1. Packages
sudo apt install apache2 mariadb-server php php-mysql php-curl php-mbstring php-xml php-intl composer

# 2. Code and dependencies
cd /var/www/html
sudo git clone https://github.com/zaiidq/Masar.git masar
cd masar
composer install --no-dev

# 3. Database
sudo mysql -e "CREATE DATABASE masar_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
sudo mysql -e "CREATE USER 'masar'@'localhost' IDENTIFIED BY 'choose-a-strong-password'"
sudo mysql -e "GRANT ALL PRIVILEGES ON masar_db.* TO 'masar'@'localhost'"
sudo mysql masar_db < database/schema.sql
sudo mysql masar_db < database/seed.sql

# 4. Configuration
cp .env.example .env
nano .env            # DB_USER, DB_PASSWORD, GEMINI_API_KEY

# 5. Permissions: the web server writes uploads and sessions
sudo chown -R www-data:www-data storage
sudo chmod 640 .env && sudo chown root:www-data .env

# 6. First administrator
php tools/create-admin.php
```

### Securing the server

Masar ships `.htaccess` files that block `.env`, `storage/`, `vendor/`,
`includes/`, `config/`, `database/` and `tools/`. Apache only reads them
when overrides are allowed. On Ubuntu they are **off by default**, so add
this to your site configuration (e.g. `/etc/apache2/sites-available/000-default.conf`):

```apache
<Directory /var/www/html/masar>
    AllowOverride All
    Require all granted
</Directory>
```

Then `sudo a2enmod rewrite && sudo systemctl reload apache2`, and verify:

```bash
curl -I http://your-server/masar/.env        # must be 403 Forbidden
```

Using **nginx**? `.htaccess` is ignored; deny the same paths in the
server block:

```nginx
location ~ ^/masar/(\.|vendor|includes|config|database|tools|lang|storage|backups) { deny all; }
location ~ \.(md|sql|sh|lock|env)$ { deny all; }
```

### Local development (XAMPP on Windows)

Copy the project to `C:\xampp\htdocs\masar`, enable `extension=curl` in
`php.ini`, import `database/schema.sql` and `database/seed.sql` from
phpMyAdmin, copy `.env.example` to `.env`, then run
`C:\xampp\php\php.exe tools\create-admin.php`.

## Upgrading an existing installation

Databases created before November 2026 need the v2 migration once:

```bash
sudo mysqldump masar_db > ~/masar_backup_before_v2.sql
sudo mysql masar_db < database/migrations/2026_11_01_masar_v2_upgrade.sql
sudo mysql masar_db < database/seed.sql
sudo mysql masar_db < database/migrations/2026_11_02_bilingual_links.sql
sudo mysql masar_db < database/migrations/2026_11_12_plan_accuracy.sql
sudo mysql masar_db < database/migrations/2026_11_13_account_features.sql
```

The last two are required for any installation older than 13 November
2026: without `2026_11_12_plan_accuracy.sql` analysis and Ask Masar fail,
and without `2026_11_13_account_features.sql` nobody can sign in.

The migrations add the new columns and the Ask Masar / alerts tables
without touching existing data; re-running the seed fills in the Arabic
faculty and major names. Re-analyse a record (or re-plan from the
Recommendations page) to get Arabic recommendation reasons.

## Known limitations

- **Login lockout is per IP address.** After `LOGIN_MAX_ATTEMPTS` failed
  logins from one address, that address is blocked for 15 minutes.
  - Behind a reverse proxy, Apache sees every visitor as the proxy's
    address unless `TRUST_PROXY=1` is set, so a few wrong passwords from
    anyone would block sign-in for everyone. With `TRUST_PROXY=1` the IP
    column of the admin Activity log must show different addresses, not
    `127.0.0.1`.
  - People who share one public address (a university or home network)
    are still blocked together. Counting per account as well would
    remove this.

## Public access (reverse proxy)

The production site is published under a domain name through
[Caddy](https://caddyserver.com), which obtains the HTTPS certificate
automatically and passes requests to Apache:

```
example.ddns.net {
    encode gzip
    redir / /masar/
    reverse_proxy 127.0.0.1:8080
}
```

Then in `.env`:

```
APP_URL=https://example.ddns.net/masar
TRUST_PROXY=1
```

Forward **only port 443** on the router. Apache's own port (8080 above)
must not be reachable from the internet: with `TRUST_PROXY=1` Masar
believes the proxy's `X-Forwarded-*` headers, and a visitor who reaches
Apache directly could forge them.

## Configuration (`.env`)

| Key | Default | Purpose |
|-----|---------|---------|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` | `localhost`, `masar_db`, `root`, empty | Database connection |
| `GEMINI_API_KEY` | — | Required for analysis and Ask Masar |
| `GEMINI_MODEL` | `gemini-2.5-flash` | Model used for both features |
| `GEMINI_TIMEOUT` | `120` | Seconds per AI request |
| `SESSION_LIFETIME` | `28800` | Seconds of inactivity before logout |
| `CHAT_DAILY_LIMIT` | `30` | Ask Masar questions per student per day |
| `LOGIN_MAX_ATTEMPTS` | `5` | Failed logins per IP per 15 minutes |
| `TRUST_PROXY` | `0` | Trust `X-Forwarded-For` / `X-Forwarded-Proto` (only behind your own proxy); makes the session cookie `Secure` over HTTPS |
| `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASSWORD` | empty, `587` | Mail server for password reset links; empty host switches email off |
| `MAIL_FROM`, `MAIL_FROM_NAME` | `SMTP_USER`, `Masar` | Sender of those emails |
| `APP_URL` | detected | Public address used in emailed links and in link previews (set it behind a proxy, e.g. `https://example.ddns.net/masar`) |

### Password reset

A student who forgets their password uses "Forgot your password?" on the
sign-in page and receives a link that works once and expires after 60
minutes. Without a mail server (`SMTP_HOST` empty) the request appears
under Alerts instead, and an administrator creates the link with
**Reset link** on the Users page and passes it to the student. Changing
or resetting a password signs the account out on every other device.

## Backups

```bash
bash tools/backup.sh                 # writes to masar/backups/, keeps 14
```

Daily at 03:15 via cron: `15 3 * * * bash /var/www/html/masar/tools/backup.sh`

## Command-line tools

| Command | Purpose |
|---------|---------|
| `php tools/create-admin.php` | Create the first admin or promote a user |
| `php tools/test-analysis.php record.pdf [--gemini]` | Test parsing (and AI analysis) without the website |
| `php tools/test-pdf-parser.php record.pdf` | Dump raw PDF text |
| `bash tools/backup.sh` | Database and uploads backup |

## Privacy

- Student name, university ID, advisor and nationality are removed from
  the record text before it is sent to the AI provider, and are never
  stored by the analysis.
- Ask Masar sends only course data and the academic summary.
- Deleting an account removes the user, their uploaded PDFs, analyses
  and conversations.
