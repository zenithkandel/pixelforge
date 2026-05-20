# PixelFlap — Agent Guide

**Stack:** Pure PHP · HTML · CSS · Vanilla JS · MySQL. No frameworks, no build tools.

---

## Critical PHP Rules

- `session_start()` must be the **first statement** in every file that uses sessions — no whitespace or output before it.
- Every `header('Location: ...')` must be immediately followed by `exit()`. No exceptions.
- All PDO queries use `prepare()` + `execute()`. Zero string interpolation in SQL. Ever.
- No PHP closing tag `?>` at the end of any file.
- All `require_once` uses absolute paths: `require_once __DIR__ . '/../includes/logger.php';`
- All API endpoints (`/api/*.php`) set `header('Content-Type: application/json')` before output and end with `echo json_encode(...)`.
- Every value echoed to HTML is escaped: `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
- Every integer from user input is cast: `(int)$_POST['x']`. Every string is trimmed.

---

## Critical JS Rules

- Every DOM query null-checked before use.
- Every `fetch()` has a `.catch()` handler.
- Game loop uses `requestAnimationFrame`, not `setInterval`. Cancel with `cancelAnimationFrame()` on game over/restart.
- All timers stored and cleared on reset/unload.
- Touch events handled alongside mouse events everywhere.

---

## Build Order

1. `config.php` — DB credentials + app constants (never commit secrets)
2. `includes/logger.php` — **Built first**, required by everything. Sets up log rotation, error/exception handlers
3. `install.php` — One-time DB setup (self-deletes after run)
4. CSS design system (`assets/css/style.css`) — `logger.php` is built second, CSS is built second
5. Then build pages in required order — never skip ahead

---

## Architecture

```
/
├── config.php              ← DB credentials + constants
├── install.php             ← One-time setup (self-deletes)
├── index.php               ← Public canvas (no login)
├── game.php                ← Flappy Bird (login required)
├── canvas.php              ← Interactive canvas (login required)
├── login.php / register.php / logout.php / leaderboard.php / profile.php
├── admin/                  ← Admin panel (re-query role from DB every request)
├── api/                    ← JSON API endpoints
├── includes/
│   ├── logger.php          ← REQUIRED first; every file includes this
│   ├── db.php              ← PDO singleton
│   ├── auth.php            ← Session helpers, auth guards
│   ├── csrf.php            ← Token generation + validation
│   ├── headers.php         ← Security HTTP headers
│   ├── xp.php              ← XP/level helpers
│   └── achievements.php    ← Achievement checker + awarder
├── assets/css/            ← style.css (global design system)
└── assets/js/             ← game.js, canvas.js, territory.js, achievements.js
```

---

## Logging System

All app activity logged to `/logs/event.log` (auto-created by logger.php).

- Cron (optional): `0 * * * * curl -s http://yoursite.com/api/get_canvas.php > /dev/null` — ensures hourly pixel decay cleanup even without visitors.

- Helper functions: `log_info()`, `log_warn()`, `log_error()`, `log_debug()`, `log_sec()`, `log_admin()`
- Format: `[timestamp] [LEVEL] [CATEGORY] [user:id(name)] [ip:ip] [METHOD /uri] message | {context}`
- Protect `/logs/` AND `/includes/` with `.htaccess` (Deny from all). Never expose raw logs.
- Log rotation: auto-renames to `.bak` when >10MB.
- Useful filters: `grep "\[SECURITY\]" logs/event.log`, `grep "\[ERROR\]" logs/event.log`

---

## Key Conventions

- **CSRF:** Every POST form has `<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">`. Every API POST calls `csrf_verify()`.
- **Anti-cheat:** Game scores use one-time tokens (`game_tokens` table, single-use). Server independently recalculates multiplier. Mismatch → reject + log security event.
- **Pixel decay:** `expires_at = placed_at + 14 days`. 0–7 days: full opacity. 7–14 days: 70% opacity + shimmer. 14+: deleted on next fetch.
- **Rate limits:** Login 5/IP/15min, Register 3/IP/hr, pixel placement 10/user/min, score 1/token.
- **Admin role:** Re-query `role` from DB on every request. Never trust session role alone.
- **Output style:** No explanations, no markdown formatting, no conversational text. Only emit code.

---

## Database Notes

- PDO: `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_EMULATE_PREPARES => false`
- DB user needs SELECT, INSERT, UPDATE, DELETE only — no DDL privileges
- All tables use `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`
- Pixel ownership uses `ON DELETE SET NULL` (orphaned pixels become unclaimed)

---

## Setup for a fresh session

1. Configure `config.php` with DB credentials
2. Navigate to `/install.php` (runs schema setup, then self-deletes)
3. Create `logs/` directory with write permissions for web server
4. Navigate to `/logs/` → must return 403