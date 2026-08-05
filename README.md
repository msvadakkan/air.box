# air.box

A Netflix-style streaming app for movies, books, and music — built with plain HTML/CSS/JS on the front end and PHP + a JSON-file NoSQL store on the back end. No frameworks, no database server required.

## Stack

- **Frontend:** hand-written HTML/CSS/JS (no build step, no libraries)
- **Backend:** PHP 8+
- **"NoSQL" data layer:** `includes/db.php` — a small `JsonDB` class that stores each collection (`users`, `movies`, `books`, `music`) as a JSON document array on disk, with file locking for safe concurrent writes. No MongoDB/server install needed. Swappable for real MongoDB later if you want.

## Requirements

- PHP 8.0+ (no extensions beyond the defaults) — install via [XAMPP](https://www.apachefriends.org/) (includes Apache) or the plain [php.net](https://www.php.net/downloads) binary.

## Running it

**Option A — PHP's built-in server (fastest to try):**

```
php -S localhost:8000 router.php
```

Then open http://localhost:8000

`router.php` matters here: the built-in server ignores `.htaccess`, so without it a direct request to `/data/users.json` or a file under `/storage` would be served as a raw static file — leaking password hashes and bypassing login. The router blocks both paths.

**Option B — XAMPP/Apache:**

Copy this folder into `htdocs/`, start Apache, and visit it in the browser. The `.htaccess` files in `data/` and `storage/` already lock those folders down (`Require all denied`).

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
