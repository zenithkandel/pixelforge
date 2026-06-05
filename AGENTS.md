# PixelForge Agent Guide

PHP 8.0 + MySQL + Apache (XAMPP) web app. Match-3 game earns gems, gems buy pixels on a shared 200x200 canvas. No build tools, no framework, no package manager dependencies.

## Running Locally

- XAMPP Apache + MySQL required. Project lives at `C:\xampp\htdocs\pixelforge\` (or `/codes/pixelforge/` on this machine).
- Database: create `pixelforge` DB, import `database/schema.sql`.
- Config: `api/config.php` — DB creds, game constants (`PIXEL_COST`, `STARTING_BALANCE`, `MAX_MOVES`, `GRID_SIZE`).
- Verify setup: visit `start.php` — checks PHP extensions, DB tables, file integrity.
- No `composer install` needed (no dependencies).

## Architecture

**API pattern**: Each `api/*.php` file is a standalone endpoint. Routes use `?action=<name>` query param with a `switch` statement. Returns JSON. No router, no framework.

**Include chain**: `api/config.php` -> `includes/db.php` -> `includes/auth.php` -> `includes/csrf.php`. Always require these at the top of API files.

**Database**: `includes/db.php` provides `db()` singleton (PDO, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`, `FETCH_ASSOC`).

**Auth**: Session-based. `includes/auth.php` — `start_safe_session()`, `is_logged_in()`, `current_user_id()`, `current_user()`, `login_user()`, `logout_user()`. Admin check: `is_admin()` / `require_admin()`.

**CSRF**: `includes/csrf.php` — token stored in `$_SESSION['csrf_token']`. For API calls, send `X-CSRF-Token` header (verified by `csrf_header_verify()`). For forms, use `csrf_field()` hidden input.

## Critical: .htaccess

`RewriteBase /codes/pixelforge/` — must match your deploy path.

**Adding a new API file?** You MUST add it to the whitelist on line 5 of `.htaccess`:
```
RewriteRule ^(api/auth\.php|api/game\.php|...|api/yournewfile\.php)$ - [L]
```
Otherwise Apache returns 403.

## Key Files

| File | Purpose |
|------|---------|
| `api/auth.php` | Login, register, logout, profile, leaderboard |
| `api/game.php` | Start game, submit score, boosters |
| `api/pixels.php` | Place pixel, get pixels |
| `api/canvas.php` | Full canvas state (public, 5s cache) |
| `api/config.php` | All `define()` constants |
| `admin/api.php` | Admin CRUD (dashboard, users, pixels, sessions, transactions, achievements) |
| `includes/auth.php` | Session & user helpers |
| `includes/db.php` | PDO singleton |
| `includes/csrf.php` | CSRF token generation/verification |

## Gotchas

- **Free repaints**: Placing a pixel on your own pixel costs 0 gems (`pixels.php:130`).
- **Anti-cheat**: Score submission requires >= 30 seconds elapsed since game start (`game.php:165`). Returns 429.
- **Rate limit**: Pixel placement limited to 20/60s per session (`pixels.php:49-76`).
- **Level formula**: `floor(1 + sqrt(xp / 50))` (`game.php:183`).
- **Booster availability**: Computed from level, not stored in DB (`game.php:223-229`). Changing level formula affects boosters.
- **Pixel cost**: `PIXEL_COST = 1` gem per pixel (constant in `api/config.php`).
- **Starting balance**: New users get `STARTING_BALANCE = 10` gems.
- **Admin panel**: Separate `admin/` directory with own `api.php`. Uses role check `$_SESSION['user_id']` -> `users.role = 'admin'`.
- **No autoloader**: All includes are manual `require_once` with relative paths.
- **Password hashing**: Uses `PASSWORD_ARGON2ID` if available, falls back to `PASSWORD_BCRYPT`.
- **Login rate limiting**: 5 attempts per 15 minutes per IP (`auth.php:84-91`).

## Frontend

Vanilla JS, no framework. Pages: `index.html` (landing/auth), `game.html` (match-3), `canvas.html` (pixel canvas), `leaderboard.html`, `profile.html`.

JS modules in `assets/js/`: `utils.js`, `auth.js`, `game.js`, `game-renderer.js`, `game-animations.js`, `game-powerups.js`.

CSS in `assets/css/`: `main.css`, `auth.css`, `game.css`, `canvas.css`.

Admin panel: `admin/index.html` + `admin/admin.js` + `admin/admin.css`.

## Database Tables

`users`, `pixels` (composite PK: x,y), `game_sessions`, `score_log`, `achievements` (16 seeded), `user_achievements`, `login_attempts`, `transactions`.

Foreign keys cascade on delete for user-owned data. `pixels.owner_id` uses `ON DELETE SET NULL`.
