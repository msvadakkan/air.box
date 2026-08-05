# air.box

A Netflix-style streaming app for movies, books, and music — built with plain HTML/CSS/JS on the front end and PHP + a JSON-file NoSQL store on the back end. No frameworks, no database server required.

## Stack

- **Frontend:** hand-written HTML/CSS/JS (no build step, no libraries)
- **Backend:** PHP 8+
- **"NoSQL" data layer:** `includes/db.php` — a small `JsonDB` class that stores each collection (`users`, `movies`, `books`, `music`) as a JSON document array on disk, with file locking for safe concurrent writes. No MongoDB/server install needed. Swappable for real MongoDB later if you want.

## Requirements

- PHP 8.1+ (no extensions beyond the defaults). On Windows: [XAMPP](https://www.apachefriends.org/) or the plain [php.net](https://www.php.net/downloads) binary. On Linux: your distro's package (`apt install php php-fpm`, `dnf install php-fpm`, etc.) — Ubuntu 22.04+/Debian 12+/RHEL 9+ all ship 8.1 or newer by default.

## Running it

**Easiest — double-click `start.bat`:**

Finds PHP (checks PATH, then the winget install folder), starts the server, and opens http://localhost:8000 in your browser automatically. Close the window (or Ctrl+C) to stop it.

**Or from a terminal:**

```
php -S localhost:8000 router.php
```

Then open http://localhost:8000

`router.php` matters here: the built-in server ignores `.htaccess`, so without it a direct request to `/data/users.json` or a file under `/storage` would be served as a raw static file — leaking password hashes and bypassing login. The router blocks both paths. (`start.bat` already runs this command for you.)

**Or XAMPP/Apache:**

Copy this folder into `htdocs/`, start Apache, and visit it in the browser. The `.htaccess` files in `data/` and `storage/` already lock those folders down (`Require all denied`).

**Or nginx + PHP-FPM (Linux):**

nginx ignores `.htaccess` entirely, so those files provide no protection here — use [`nginx.conf.example`](nginx.conf.example) instead, which denies `/data/` and `/storage/` directly in the server block and routes `.php` to PHP-FPM.

```bash
# 1. Copy the project where nginx can serve it
sudo cp -r air.box /var/www/air.box

# 2. Let PHP-FPM's user own it, so the app can create data/ and storage/
#    subfolders and write uploads on first run — this is the #1 thing
#    people hit on a fresh Linux box (permission denied on first request).
sudo chown -R www-data:www-data /var/www/air.box   # Debian/Ubuntu PHP-FPM user
# RHEL/CentOS/Fedora typically use "nginx" instead of "www-data"

# 3. Install the site config
sudo cp /var/www/air.box/nginx.conf.example /etc/nginx/sites-available/air.box
sudo ln -s /etc/nginx/sites-available/air.box /etc/nginx/sites-enabled/
# — edit root and fastcgi_pass in that file to match your setup first —
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl restart php8.1-fpm   # or whatever your php-fpm service is named
```

`router.php` and `start.bat` aren't used in this setup — they're for the PHP built-in dev server and Windows convenience respectively. nginx talks to PHP-FPM directly.

## First run

On first request, the app auto-creates `data/*.json`, the `storage/` subfolders, a default **admin account**, and a few demo catalog entries (so the UI isn't empty). This happens once, guarded by `data/.seeded`.

- **Browsing, watching, reading, listening is public** — no account or login needed.
- **Admin login (the only login in the app):** `/admin/login.php` → `admin` / `Admin123`

Demo movies ship with posters but no video file (upload one from the admin panel to make them playable). Demo books and tracks include a real placeholder PDF/audio file generated at seed time, so reading/listening works out of the box.

**Change the admin password** from `/admin/settings.php` (or delete the account and make your own admin user) before putting this anywhere but your own machine — `includes/seed.php` has the default credentials in one place.

## How "no download, stream/read only" is enforced

- Movie/book/music files live in `storage/`, which is never web-servable directly (blocked by `router.php` and `data|storage/.htaccess`).
- The browser only ever talks to `stream.php` (movies/music, HTTP Range support for seeking) and `book_stream.php` (PDFs), which serve `Content-Disposition: inline` and never reveal the real file path — both are open to any visitor, matching the "free to watch/read" requirement, but the raw files themselves stay unreachable directly.
- Poster/cover art is public and served through `image.php` the same way, so `storage/` stays fully locked even for images.
- Players set `controlsList="nodownload"`, disable right-click on media, and the PDF viewer hides its toolbar.
- This is a UX-level deterrent, not DRM — a determined user can always capture a stream. That's an inherent limit of a client-side player, not something any web app can fully close.
- The admin **upload/delete** actions are the only things gated behind login (`require_admin()`).

## Project layout

```
includes/        Shared PHP: config, JsonDB, auth, helpers, header/footer, seed
data/            JSON "collections" (users [admin accounts only], movies, books, music) — locked down
storage/         Uploaded media, organized by type — locked down
assets/          CSS/JS
admin/           Admin-only panel (own login, dashboard, upload/manage per content type)
image.php        Public poster/cover endpoint
stream.php       Public movie/music streaming endpoint
book_stream.php  Public PDF endpoint
index.php        Netflix-style browse home (hero + rows) — public
watch.php / book.php / listen.php   Player pages — public
```

## Admin panel

`/admin/login.php` is the only login in the app, gated by a `role: "admin"` field on the user record. From the dashboard you can upload a movie (thumbnail + optional video file), a book (cover + optional PDF), or a track (cover + optional audio file), and delete any of them.
