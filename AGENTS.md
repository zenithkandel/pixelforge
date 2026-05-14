# PixelForge — Agent Guide

## Stack
- **Backend:** Pure PHP 8.2+, no framework
- **Frontend:** Vanilla JS ES6 modules, no bundler, no build step
- **Database:** MySQL 8.0+ (InnoDB, utf8mb4)
- **Cache/Sessions/PubSub:** Redis 7.0+
- **Web Server:** Nginx (PHP-FPM). Serves `public/` directory ONLY.

## File Structure (must follow exactly)
```
/var/www/pixelforge/       ← parent (NOT web root)
├── public/                ← Nginx serves THIS directory only
│   ├── index.php           ← Landing (login/register)
│   ├── game.php            ← PIXEL DASH (auth required)
│   ├── canvas.php          ← The Forge (public view)
│   ├── profile.php, leaderboard.php, verify.php
│   ├── assets/css/, assets/js/, assets/fonts/, assets/sounds/, assets/sprites/
│   └── api/                ← All endpoints
│       ├── auth/, game/, grid/, user/, leaderboard.php
├── includes/               ← NOT web-accessible (Nginx deny all)
│   ├── bootstrap.php       ← Every API endpoint starts with: require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
│   ├── config.php, db.php, redis.php, session.php
│   ├── security.php, response.php, validate.php, rate_limit.php
│   ├── auth.php, logger.php, game_validator.php, pxl.php, achievement.php
├── cron/                   ← Grid reset (Sunday 00:00 UTC), cleanup scripts
├── admin/                  ← Admin panel
├── logs/                   ← Server logs
└── .env                    ← Environment variables
```

## Security Rules (mandatory, never skip)
- **XSS:** ALL PHP output uses `h()` / `htmlspecialchars()`. JavaScript uses `textContent`, never `innerHTML` with user data.
- **SQL:** Every query uses PDO prepared statements. `PDO::ATTR_EMULATE_PREPARES = false`.
- **CSRF:** Synchronizer token pattern. Every state-changing POST verifies `X-CSRF-Token`.
- **Passwords:** `password_hash($p, PASSWORD_BCRYPT, ['cost' => 12])`.
- **Session:** Redis-backed, `sess:{session_id}`, 24h TTL. Regenerate ID on login.
- **Headers:** CSP, X-Frame-Options: DENY, X-Content-Type-Options: nosniff, HSTS. Set in Nginx AND PHP.
- **Rate Limiting:** Redis sliding window. All endpoints checked before business logic.
- **Error Messages:** Generic codes, no stack traces, no SQL errors exposed to client.

## Grid Architecture (The Forge)
- **Size:** 800×800 pixels (coords 0–799)
- **Chunks:** 64×64 pixels each → 32×32 = 1024 chunks total
- **Chunk ID:** `cx_cy` where `cx = floor(x/64)`, `cy = floor(y/64)` (range 0–31)
- **Binary format:** 12,288 bytes per chunk (64×64×3 bytes RGB). Unowned pixels = `\xFF\xFF\xFF` (white).
- **Redis:** `chunk:{cx}:{cy}` → binary, TTL 300s. `chunk_v:{cx}:{cy}` → version counter.
- **Min purchase zoom:** 4× (below that, pixels unclickable)

## Pixel Purchase Concurrency (critical)
Pixel buy uses Redis distributed lock + MySQL transaction:
1. `SET pixel_lock:{x}:{y} {token} NX PX 5000` (Redis lock, 5s expiry)
2. MySQL: `BEGIN` → `SELECT ... FOR UPDATE` (user balance row) → deduct PXL → `INSERT/UPDATE pixels` → `INSERT pixel_history` → `UPDATE chunks SET version=version+1` → `COMMIT`
3. Invalidate Redis chunk cache, increment chunk version, publish SSE event via Redis Pub/Sub `sse_channel`
4. Always release lock in `finally` block (compare token before DEL)

## Game Anti-Cheat
- Server issues `session_id` (random) + `seed` (32-bit int) + `hmac` (HMAC-SHA256 over session_id:seed:user_id with GAME_HMAC_KEY)
- Client PRNG (Mulberry32) seeded with server-provided seed — obstacle sequence is deterministic
- Checkpoint/submit: client computes HMAC-SHA256 of `session_id:score:elapsed_ms` using server-provided client key
- Score plausibility: `score/duration_sec <= 200` (hard cap), sustained `<= 80` for games > 30s
- One active game session per user (Redis key `game_active:{user_id}`, TTL 7200s)

## PXL Economy
- Earn: 200 game score = 1 PXL (server-calculated), plus bonuses
- Spend: 1 pixel = 1 PXL (flat, no premium zones)
- All transactions logged in `pxl_transactions` (append-only ledger)
- 20 achievements, PXL credited only when user **claims** via UI (not auto-credited)

## API Response Format
```json
{"ok": true, "data": {...}}
{"ok": false, "error": "error_code", "message": "Human readable"}
```
Standard helpers: `respond_success($data)`, `respond_error($error, $msg, $code, $extra)`.

## Redis Key Patterns
```
chunk:{cx}:{cy}        → chunk binary, TTL 300s
chunk_v:{cx}:{cy}      → version int
game_active:{user_id}  → session_id, TTL 7200s
sess:{session_id}      → serialized session, TTL 86400s
rl:{action}:{id}       → rate limit sorted set
pixel_lock:{x}:{y}     → lock token, 5000ms
login_fail:{ip}        → sorted set, TTL 900s
daily_bonus:{uid}:{date}, daily_game:{uid}:{date}
lb:daily/weekly/alltime → leaderboard JSON, TTL 60/300/600s
sse_channel            → Pub/Sub channel
```

## Cron Jobs
```
# Grid reset — every Sunday 00:00 UTC
0 0 * * 0 php /var/www/pixelforge/cron/reset_grid.php
# Cleanup old sessions — daily 03:00 UTC
0 3 * * * php /var/www/pixelforge/cron/cleanup_sessions.php
# Cleanup login attempts — daily 04:00 UTC
0 4 * * * php /var/www/pixelforge/cron/cleanup_login_attempts.php
```

## SSE (Real-time Canvas Updates)
- Endpoint: `GET /api/grid/updates.php?chunks=cx1,cy1,cx2,cy2,...`
- Redis Pub/Sub: `PUBLISH sse_channel {json}` on pixel purchase; `SUBSCRIBE` in SSE endpoint
- Heartbeat every 25s. `X-Accel-Buffering: no` in Nginx for SSE.
- Reconnect with exponential backoff on client.

## Development Notes
- Font: `Outfit` (display/body) + `JetBrains Mono` (coords, scores, PXL amounts)
- Accent color: `#5b4fff` (electric violet). PXL currency: `#f59e0b` (amber)
- Sidebar: dark (`#111318`), content area: light (`#f7f7f8`)
- `core.md` and `plan.md` contain the complete system specification. Read them before modifying any subsystem.