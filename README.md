# VoltTech

Gear marketplace, loadout customizer and bio-energy tracker for heroes, villains and civilians, with a
separate **Admin Console**. One project, one database, usable from any device with a web browser.

| | URL on your computer (XAMPP) | What it is |
|---|---|---|
| Player app | `http://localhost/VoltTech/` | register, log in, buy / sell / equip gear, track energy, change password |
| Admin Console | `http://localhost/VoltTech/admin/` | users, roles, gear catalogue, inventories, energy logs, audit log, statistics |

Online it is the same thing under your own address, e.g. `https://your-domain.com/` and `https://your-domain.com/admin/`.

> **Internal names are unchanged on purpose.** The visible brand is VoltTech, but the database file is still
> `powerforge.sqlite` and the tables are still `users`, `gear`, `inventory`, `energy_logs`, so your existing
> data keeps working.

---

## 1. First things first: bring your existing data

This package contains **no database**, so it can never overwrite yours. The database lives in `data/`.

1. Stop your old servers (close the `php -S` terminals, or stop Apache in XAMPP).
2. Copy your old file **`powerforge/data/powerforge.sqlite`** into **`VoltTech/data/`**.
   If the old `data/` folder also has `powerforge.sqlite-wal` and `powerforge.sqlite-shm`, copy all three together.
3. Run the self-test (section 3). It reports how many users, gear items, inventory rows and energy logs it finds,
   so you can confirm nothing is missing.
4. Keep your old `powerforge` / `powerforge-admin` folders as a backup until you are happy.

If you skip this and start fresh, VoltTech creates a new empty database with 10 starter gear items.

**What changes in an existing database:** nothing is altered or deleted. VoltTech only adds one small table,
`login_attempts` (used to slow down password guessing), plus `admin_audit_log` if your admin app had not created it yet. Existing tables, columns, rows and relationships are untouched.

---

## 2. Requirements

| | |
|---|---|
| PHP | **8.0 or newer** (8.2 / 8.3 recommended) |
| PHP extensions | `pdo`, `pdo_sqlite` (or `pdo_mysql` if you use MySQL), `json`, `session`. `mbstring` is recommended |
| Web server | Apache (XAMPP, cPanel hosting, most shared hosts) or nginx + PHP-FPM. For quick tests, PHP's built-in server |
| Database | SQLite (default, nothing to install) or MySQL / MariaDB 10.3+ |
| Browser | Any current Chrome, Edge, Firefox or Safari (desktop, Android, iPhone, iPad) |

There is nothing to compile and no `composer install`.

---

## 3. Run it on your own computer

### Option A: XAMPP (what you are already using)

1. Copy this whole folder to `C:\xampp\htdocs\VoltTech\` (so that `C:\xampp\htdocs\VoltTech\index.php` exists, not a double-nested folder).
2. Copy your database in (section 1). Start **Apache** in the XAMPP control panel.
3. Open `http://localhost/VoltTech/`.

### Option B: PHP's built-in server

```bash
cd VoltTech
php -S localhost:8000 router.php
```

Open `http://localhost:8000/` (and `http://localhost:8000/admin/`).
Always include **`router.php`**: the built-in server ignores `.htaccess`, so without it the database file could be downloaded.

To try it from a phone on the **same Wi-Fi**, use `php -S 0.0.0.0:8000 router.php`, find your computer's IPv4 address
(`ipconfig` on Windows) and browse to `http://THAT-IP:8000/`. Allow PHP through the Windows firewall if asked.
Only do this on a network you trust.

### Self-test (run this first)

```bash
php tools/selftest.php
```

It checks your PHP version and extensions, database permissions, row counts, and replays the purchase/sell logic on a
temporary in-memory database. `[FAIL]` lines tell you exactly what to fix; `[WARN]` lines are advice.

### Make your first admin

Admin rights can only be granted from the server, never from the website:

```bash
php tools/list_users.php                       # see who exists
php tools/promote_admin.php your_username      # grant admin
```

Then log in at `/admin/` with that account. (If your account was already promoted before, it still is.)

---

## 4. Putting it online (access from any device through a URL)

Your PC at home is **not** reachable from other networks. For a real URL you need a web host. Two common routes:

### Route 1: shared hosting with cPanel (cheapest and simplest)

1. Buy hosting with **PHP 8.0+** and a domain; in cPanel, enable **SSL** (AutoSSL / Let's Encrypt).
2. Upload the contents of this folder into `public_html/` (or a sub-folder such as `public_html/volttech/`) with the File Manager or FTP.
3. **Keep the database out of the public folder.** In your hosting home directory (above `public_html`) create `volttech-data/`, upload `powerforge.sqlite` there and make it writable (permission 750/770).
4. Copy `config/db_credentials.example.php` to `config/db_credentials.php` and set:
   ```php
   'debug'       => false,
   'driver'      => 'sqlite',
   'sqlite_path' => '/home/YOUR_CPANEL_USER/volttech-data/powerforge.sqlite',
   'force_https' => true,
   ```
5. Visit `https://your-domain.com/`. Create your account, then run `promote_admin.php` (cPanel > *Terminal*, or ask your host), or use the cPanel cron/PHP-CLI if no terminal is available.

### Route 2: your own server (VPS) with Apache or nginx

```bash
sudo apt install apache2 php php-sqlite3 php-mbstring libapache2-mod-php   # or nginx + php-fpm
sudo a2enmod rewrite headers
# upload the project to /var/www/volttech, then:
sudo mkdir -p /var/lib/volttech && sudo chown www-data:www-data /var/lib/volttech
sudo chown -R www-data:www-data /var/www/volttech/data
sudo apt install certbot python3-certbot-apache && sudo certbot --apache -d your-domain.com
```

Apache needs `AllowOverride All` for the folder so the bundled `.htaccess` files work. For nginx use `deploy/nginx.conf.example`.
Set `sqlite_path` to `/var/lib/volttech/powerforge.sqlite` and `force_https` to `true`.

### Route 3: Render

This is a PHP application, so it does **not** need a `package.json`, `npm install`, or a Node build command. Render runs it from the included `Dockerfile` instead.

1. Push this folder to a GitHub or GitLab repository. Do not commit `config/db_credentials.php` or `data/powerforge.sqlite`.
2. In Render, select **New → Blueprint**, connect the repository, and deploy the detected `render.yaml`. It creates a free Docker web service.
3. Open the generated `https://…onrender.com` URL. On the first start, VoltTech creates its starter SQLite database.

> **Free-tier limit:** Render's filesystem is temporary, so its SQLite database is deleted when the service restarts or redeploys. The free plan is suitable only for a demo. For real accounts or data, use a paid persistent disk or move the app to an external MySQL database.

The Docker image supplies the required `pdo_sqlite` extension, Apache rewrite support, and listens on Render's assigned `PORT`. You do not need to add a Build Command or Start Command in the Render dashboard.

### Go-live checklist

- [ ] `https://` works (padlock) and `'force_https' => true`
- [ ] `'debug' => false`
- [ ] The database file is **outside** the web folder (or at least `/data/powerforge.sqlite` returns 403/404 in your browser: try it!)
- [ ] `config/db_credentials.php` is not downloadable (`/config/db_credentials.php` must return 403/404)
- [ ] `php tools/selftest.php` shows no `[FAIL]`
- [ ] A daily backup is scheduled (section 7)
- [ ] The admin password is long and unique
- [ ] You can delete `deploy/` from the server (it is only examples)

### Which database? SQLite or MySQL

| | SQLite (default) | MySQL / MariaDB |
|---|---|---|
| Setup | none | create a database and user |
| Good for | one server, up to a few hundred active users, class projects, small communities | many users writing at the same time, several servers, hosts with restricted files |
| Limit | one writer at a time (reads are concurrent; waits up to 5 s; plenty for this app) | none relevant here |
| Backup | `php tools/backup.php` | `mysqldump` |

**Recommendation:** keep SQLite while you have one server and modest traffic, since it is simpler and your data is already in it.
Move to MySQL when you expect many simultaneous users or your host recommends it. Do not migrate "just because".

**Switching to MySQL (data preserved):**

```bash
# 1. in MySQL: CREATE DATABASE powerforge CHARACTER SET utf8mb4; and a user with rights on it
# 2. put the credentials in config/db_credentials.php under 'mysql' (leave 'driver' => 'sqlite' for now)
mysql -u USER -p powerforge < config/mysql_schema.sql     # 3. create the empty tables
php tools/migrate_to_mysql.php                            # 4. copy the data and verify row counts
# 5. only if it ends with "Done": set 'driver' => 'mysql' in config/db_credentials.php
```

The migration only *reads* the SQLite file (keep it as a backup), keeps every id, never truncates or drops a row silently, and compares counts at the end.
MySQL treats `Alex` and `alex` as the same username; if your SQLite data contains two such users the migration stops and tells you which.

---

## 5. Configuration reference (`config/db_credentials.php`)

| Key | Default | Meaning |
|---|---|---|
| `debug` | `false` | `true` shows PHP errors on screen. **Local computer only.** |
| `driver` | `'sqlite'` | `'sqlite'` or `'mysql'` |
| `sqlite_path` | `null` | `null` = `data/powerforge.sqlite`. Use an absolute path to keep the file outside the web folder |
| `force_https` | `false` | Redirects `http://` to `https://` and sends HSTS. Turn on once SSL works |
| `trust_proxy` | `false` | `true` only behind a proxy/CDN (e.g. Cloudflare) so the real visitor IP and HTTPS are detected |
| `mysql` | see file | `host`, `port`, `dbname`, `user`, `pass`, `charset` (only used when `driver` is `mysql`) |

The file can live outside the project: set the environment variable `VOLTTECH_CONFIG` to its full path.
**Never commit or upload your real `db_credentials.php` to a public place** (it is in `.gitignore`).

---

## 6. What the Admin Console does

| Page | Features |
|---|---|
| Dashboard | totals (users, admins, gear, inventory rows, equipped items, credits in circulation, energy log entries), users by role, most-owned gear, newest accounts, recent admin activity |
| Users | search, filter by role, pagination, **create** account |
| User details | profile and equipped drain, edit credits and energy cap, change role, **reset password**, inventory (remove items), recent energy history, **delete** account |
| Gear | add, edit, delete, search, filter by category, owner count per item |
| Inventories | every item of every user, search, remove |
| Energy logs | every energy event, search, manual add/deduct (logged as `[Admin]`) |
| Audit log | every admin action (who, what, when), search and filter |

Safeguards: you cannot change your own role or delete your own account (so the system can never end up with no admin);
admin rights are re-checked on every request; only the fields you actually change are saved (an old open form can't overwrite a player's live balance);
destructive actions ask for confirmation.

---

## 7. Backups

```bash
php tools/backup.php                     # -> data/backups/volttech-YYYYMMDD-HHMMSS.sqlite
php tools/backup.php /home/you/backups   # -> any folder (recommended: outside the web folder)
```

Makes a consistent copy even while the site is in use and keeps the newest 14. Schedule it daily with cron:
`15 3 * * * /usr/bin/php /home/you/volttech/tools/backup.php /home/you/backups`.
Also download a copy to another machine now and then.

---

## 8. Security summary

| Area | What VoltTech does |
|---|---|
| Passwords | `password_hash()` (bcrypt) with automatic upgrade of old hashes at login; new passwords 8 to 72 characters; no passwords or hashes are ever shown |
| SQL injection | every query uses prepared statements; `LIMIT/OFFSET` are cast to integers |
| XSS | all output goes through `h()` (HTML-escaped); a **Content-Security-Policy** forbids inline scripts, so injected `<script>` cannot run |
| CSRF | a random token on every POST form and on the equip API; logout is POST-only |
| Sessions | HttpOnly + SameSite cookies, `Secure` over HTTPS, strict mode, new ID at login, separate cookie for the admin console, idle timeout (2 h players, 30 min admins) |
| Brute force | login: 8 failed tries per 15 min per IP; admin login: 5; registration: 10 per hour; password change: 6 per 15 min. Unknown-user and wrong-password look and time the same |
| Authorization | admin pages re-check the `admin` role in the database on every request; players can only touch their own inventory |
| Money logic | buying and selling are single transactions with the credit check inside the SQL, so two taps or two devices cannot double-spend or double-refund |
| Input | all inputs validated on the server (length, range, allowed values), not only in the browser |
| Files | `config/`, `includes/`, `data/`, `tools/`, `deploy/` are blocked from the web (Apache `.htaccess`, nginx example, `router.php`); `.sqlite`/`.sql`/`.log` files are never served |
| Errors | visitors see a friendly page; details go to the server log (or on screen only if `debug` is on) |
| Headers | CSP, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS on HTTPS, no caching of private pages |

**Known limits (be aware):** there is no e-mail password recovery (an admin resets passwords from the Admin Console);
changing a password does not sign out the account's other devices until they time out; and two simultaneous
"equip" taps on different devices could briefly exceed the energy cap (harmless, corrected on the next change).

---

## 9. How this version was checked

**Be aware:** the environment where this was built had **no PHP interpreter**, so the PHP code could not be executed there.
Instead the following was done, and **you should run `php tools/selftest.php` plus the click-through below on your own machine**.

| Checked | How |
|---|---|
| PHP syntax | custom tokenizer on all 36 PHP files (balanced brackets, missing semicolons, unterminated strings), proven by injecting 7 deliberate typos into a copy and confirming each was caught |
| Names | every variable is assigned before use; every `$row['column']` is a real column or alias; every form field read by PHP exists in a form (proven with 5 injected typos) |
| Includes, links, assets, icons | every `require`, link, redirect, CSS/JS file and icon reference resolves |
| CSRF / CSP | every POST form has a token and every handler verifies it; no inline scripts or handlers |
| SQL | the 90 real SQL statements (including the dynamic search/filter/pagination ones) were extracted from the source and compiled against the real schema in SQLite |
| Data safety | a simulated live database was upgraded exactly as the code does; every row and every table definition stayed identical, only `login_attempts` was added |
| Money logic | buy/sell flows replayed with two competing connections: no double-spend, no double-refund, no negative credits |
| Browser (real Chromium) | the real page templates rendered with sample data and served with the real CSP: 15 pages x 5 screen sizes (360, 390, 768, 1024, 1440 px): no horizontal scrolling, no CSP violations, no JS errors, no missing icons; mobile menu, stacked tables, 44 px touch targets, confirm dialogs, auto-submit filters, equip/unequip against a mock of the API, light/dark theme |
| JavaScript | `node --check` on all scripts |

**Not tested (cannot be tested without PHP):** the PHP code paths running together on a real server, MySQL against a real MySQL server,
Safari/Firefox rendering (Chromium only), and a real phone. The browser tests used sample data, not your database.

### Your 5-minute acceptance test

1. `php tools/selftest.php` shows no `[FAIL]`.
2. Register a new account on your phone (over Wi-Fi or the live URL), check the menu button opens and closes.
3. Marketplace: buy an item. Credits drop. Try to buy it again: refused.
4. Customizer: equip and unequip it. Sell it: credits come back (half price).
5. Tracker: log an energy use; History shows it.
6. Account: change the password, log out, log in with the new one.
7. Promote that account (`promote_admin.php`), open `/admin/`, find the user, change its credits, check the **Audit Log** shows it.
8. Open `/data/powerforge.sqlite` and `/config/db_credentials.php` in the browser: both must be refused.

---

## 10. Troubleshooting

| Problem | Fix |
|---|---|
| **500 error right after upload** | Your host may not allow `Options` in `.htaccess`. Rename `.htaccess` to `.htaccess.off`, reload; if it works, delete the `Options -Indexes` line and rename it back. Then check the PHP error log |
| Blank/odd page | Set `'debug' => true` temporarily on a **local** machine, or read the server error log |
| `unable to open database file` / `attempt to write a readonly database` | The web server user cannot write the database **and its folder**. Fix permissions on the `data/` folder (SQLite needs to create temporary files beside the database) |
| `Security check failed (invalid or expired form token)` | The page was open too long, or cookies are blocked. Go back, refresh, try again |
| Logged out unexpectedly | Idle timeout (2 h player / 30 min admin) |
| `Too many failed attempts` | Wait 15 minutes. (Behind a proxy/CDN, set `'trust_proxy' => true` or all visitors share one IP) |
| `database is locked` | Rare busy moment; retry. If constant, move to MySQL |
| `Could not open input file: tools/...` | Your terminal is in the wrong folder. `cd` into the folder that contains `index.php` first |
| Everyone logged out after upgrading | Expected once: the session cookie was renamed (`volttech_sess`, `volttech_admin_sess`) |
| Admin Console says invalid login | The account must be promoted first: `php tools/promote_admin.php <username>` |

---

## 11. What changed from PowerForge

- **One project:** the separate `powerforge-admin` app is now the `admin/` folder, sharing config, database, styles and security code. `admin.php` in the player app redirects to it.
- **Fixed:** a failed login with a *valid* username re-rendered the page with that account's name, role, credits and energy (and revealed which usernames exist); double-spend / double-refund races in buy and sell; admin role dropdown overwriting live credits with old values; admins able to demote or delete themselves; admin login revealing whether an account exists; `php -S` exposing the database; MySQL `rowCount()` and `INSERT IGNORE` pitfalls in the migration.
- **Added:** Account page (change password); admin user-details page, create user, reset password, pagination, extra statistics; login/registration throttling; Content-Security-Policy and other headers; friendly error page; HTTPS enforcement option; `selftest.php`, `backup.php`, `router.php`; nginx example.
- **Behaviour you will notice:** passwords for **new** accounts must be 8+ characters (existing accounts are unaffected); usernames are 3-30 letters/numbers/`.`/`-`/`_` for new accounts; registration asks for the password twice; logging out is a button, not a link.

---

## 12. Project structure

```
VoltTech/
├── index.php  marketplace.php  customizer.php  tracker.php  account.php   player pages
├── login.php  register.php  logout.php  admin.php (redirect)
├── api/toggle_equip.php        equip/unequip (JSON, CSRF-protected)
├── admin/                      Admin Console (own login, own session)
│   ├── index.php users.php user.php gear.php inventories.php energy_logs.php audit_log.php
│   └── includes/               admin auth, header, footer
├── includes/                   shared bootstrap (security, sessions, helpers), player auth, layout, icons
├── config/                     database layer, db_credentials(.example).php, mysql_schema.sql
├── css/  js/                   style.css, admin.css | app.js, ui.js, theme.js, theme-init.js
├── data/                       put powerforge.sqlite here (never served to browsers)
├── tools/                      selftest, promote_admin, list_users, backup, migrate_to_mysql  (CLI only)
├── deploy/                     nginx.conf.example (can be deleted on the server)
├── router.php                  for `php -S` only
├── .htaccess  .gitignore  README.md
```
#   v o l l t e c h  
 #   v o l l t e c h  
 
