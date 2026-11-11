# air.box

A Netflix-style streaming app for movies, books, and music — built with plain HTML/CSS/JS on the front end and PHP + a JSON-file NoSQL store on the back end. No frameworks, no database server required.

## Stack

- **Frontend:** hand-written HTML/CSS/JS (no build step). The only library is [PDF.js](https://mozilla.github.io/pdf.js/), bundled in `assets/vendor/`, which the admin Rotana Bot page uses to read PDFs (text and photos).
- **Backend:** PHP 8+
- **"NoSQL" data layer:** `includes/db.php` — a small `JsonDB` class that stores each collection (`users`, `movies`, `books`, `music`) as a JSON document array on disk, with file locking for safe concurrent writes. No MongoDB/server install needed. Swappable for real MongoDB later if you want.

## Requirements

- PHP 7.4+ (no extensions beyond the defaults). Deliberately written in plain, old-style PHP — no `match`, union return types, or other 8.0+-only syntax — so it runs as-is on whatever PHP ships with your distro, including the default `apt install php` on older Raspberry Pi OS / Debian releases. On Windows: [XAMPP](https://www.apachefriends.org/) or the plain [php.net](https://www.php.net/downloads) binary. On Linux: your distro's package (`apt install php php-fpm`, `dnf install php-fpm`, etc.).

## Running it

**Easiest — double-click `start.bat`:**

Finds PHP (checks PATH, then the winget install folder), starts the server, and opens http://localhost:8000 in your browser automatically. Close the window (or Ctrl+C) to stop it.

**Or from a terminal:**

```
php -c php.ini -S localhost:8000 router.php
```

Then open http://localhost:8000

`router.php` matters here: the built-in server ignores `.htaccess`, so without it a direct request to `/data/users.json` or a file under `/storage` would be served as a raw static file — leaking password hashes and bypassing login. The router blocks both paths. `-c php.ini` matters too — PHP's compiled-in default upload limit is 8MB (2MB for a single file), far below what the app allows (movies up to 1GB); without it, uploading anything beyond a few MB fails with a cryptic "headers already sent" error instead of your file. (`start.bat` already does both for you.)

**Or XAMPP/Apache:**

Copy this folder into `htdocs/`, start Apache, and visit it in the browser. The `.htaccess` files in `data/` and `storage/` already lock those folders down (`Require all denied`). Also copy the settings from [`php.ini`](php.ini) (`upload_max_filesize`, `post_max_size`, etc.) into XAMPP's own `php.ini` (`xampp/php/php.ini`) and restart Apache — otherwise the same 8MB upload ceiling applies here too.

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

# 4. Apply the upload/execution limits from php.ini (PHP-FPM's default 8MB
#    upload ceiling is far below the app's 1GB movie limit). Check your
#    version first (path depends on it, e.g. 7.4 vs 8.2):
php -v
#    Then either paste these into that version's php.ini, or scope them to
#    just this site via its PHP-FPM pool file (substitute your version below).
#    nginx also enforces its own upload ceiling independently of PHP
#    (client_max_body_size, set in nginx.conf.example):
echo 'php_admin_value[upload_max_filesize] = 1030M
php_admin_value[post_max_size] = 1030M
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 600' | sudo tee -a /etc/php/7.4/fpm/pool.d/www.conf
sudo systemctl restart php7.4-fpm   # or whatever your php-fpm service is named, e.g. php8.2-fpm
```

`router.php` and `start.bat` aren't used in this setup — they're for the PHP built-in dev server and Windows convenience respectively. nginx talks to PHP-FPM directly.

**Or as an offline Raspberry Pi device (Air_Box):**

[`deploy/raspberry-pi/`](deploy/raspberry-pi/README.md) builds a ready-to-flash Raspberry Pi OS Lite image with the app preinstalled, its own **Air_Box** Wi-Fi hotspot, and a captive portal that opens air.box as soon as a phone joins. Double-click `deploy\raspberry-pi\build-image.bat` (needs Docker Desktop), then flash the result from `dist\` with Raspberry Pi Imager. No internet is needed on the device.

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
data/            JSON "collections" (users [admin accounts only], movies, books, music, bot) — locked down
storage/         Uploaded media, organized by type — locked down
assets/          CSS/JS, self-hosted font, PDF.js (vendor/, used by the bot admin page)
admin/           Admin-only panel (own login, dashboard, upload/manage per content type)
image.php        Public poster/cover endpoint
stream.php       Public movie/music streaming endpoint
bot_api.php      Rotana Bot endpoint (includes/bot.php does the matching)
book_stream.php  Public PDF endpoint
index.php        Netflix-style browse home (hero + rows) — public
watch.php / book.php / listen.php   Player pages — public
```

## Admin panel

`/admin/login.php` is the only login in the app, gated by a `role: "admin"` field on the user record. From the dashboard you can upload a movie (thumbnail + optional video file), a book (cover + optional PDF), or a track (cover + optional audio file), and delete any of them.

## Rotana Bot

A chat assistant that answers visitors' questions about Rotana Jet from a PDF you provide: a question-and-answer sheet, or any document such as a brochure, manual or slide deck. It appears as a chat button in the corner of every public page once a PDF has been imported.

**Setting it up:** in the admin panel open **Rotana Bot**, choose the PDF and click **Import**. A large PDF takes a minute or two; progress is shown page by page.

- **Question-and-answer sheets** are recognised whether the questions are marked `Q:` / `A:` (or `س:` / `ج:`), numbered, or simply end in `?` followed by their answers.
- **Other documents** are learned topic by topic: each heading (in a slide deck, each slide's title) becomes a topic, with the text under it as the answer. Slides that are only pictures join the topic before them.
- **Photos and diagrams** come along with the answer or topic they appear in, and the bot shows them under its reply; visitors can tap one to see it full screen. Tiny icons and logos repeated on every page are left out.
- **Caution and warning notices and disclaimers** (`CAUTION`, `WARNING`, "for training purposes only", "refer to your operator manuals"…) are left out, so the bot never shows them. Headings that merely mention a warning, like "Fire Warning indication", stay.

Afterwards, check the imported list, fix anything that came out wrong, and try questions in the **Test the bot** box. Each answer can be edited by hand, including its photos: remove one, move it to a different answer, or add your own. You can also add answers and change the bot's name, welcome message and "don't know" reply.

**How it answers:** it compares the visitor's question with the PDF (allowing for different wording, common synonyms, small typos and plural/tense differences, in English and Arabic) and replies with the matching answer and its photos **exactly as they are in the PDF**. It never makes up answers. If nothing matches well enough, it says it doesn't know and suggests related questions. Because it matches words rather than understanding meaning, a question worded completely differently from the PDF may not be recognised; adding that wording as an extra question in the admin panel fixes it.

**Offline:** everything runs on the server with no internet and no AI service, so it works on the Air_Box device. The admin page reads the PDF (text and photos) in the browser with [PDF.js](https://mozilla.github.io/pdf.js/), bundled in `assets/vendor/pdfjs/`. Photos are stored in `storage/bot/images/` and served through `image.php`, like posters. A scanned PDF that's only images has no text to read, so paste the text instead.
