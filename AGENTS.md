# PixelForge Agent Instructions

## Project Location
All code is in `pixelforge-node/` subdirectory.

## Running the Server
```bash
cd pixelforge-node
npm install
node server.js
```

First run creates `.env` from `.env.example` and exits. Edit `.env` with SMTP settings, then run again. Server auto-generates secrets (JWT_SECRET, CSRF_SECRET, GAME_HMAC_SECRET) and runs database migrations on subsequent starts.

## Required Env Variables
- `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME` (MySQL)
- `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS` (email)
- Secrets are auto-generated; do not set manually

## Build/Test Commands
None defined. No lint, test, or typecheck scripts in package.json.

## Key Architecture
- **Entry**: `server.js` - initializes config, DB pool, migrations, Express app, cron jobs
- **Routes**: `src/routes/` - auth, game, grid, user, leaderboard, admin
- **Services**: `src/services/` - sseManager, chunkService, gameValidator, achievementService, scheduling
- **DB**: MySQL with connection pool in `src/database.js`
- **Auth**: JWT (15min access + 7d refresh), CSRF double-submit cookie, bcrypt password hashing
- **Real-time**: SSE via `src/services/sseManager.js` - in-memory EventEmitter (single-process only)

## Concurrency
Pixel purchases use MySQL transactions with `FOR UPDATE` row locks. Do not assume distributed locking - only single Node process.

## Reference
See `core.md` for complete specification (~12k words). Contains game design, API specs, database schema, and implementation details.

## Gotchas
- Chunk binary is exactly 12,288 bytes (64x64x3 RGB)
- HMAC verification required for game score submission
- Grid resets weekly (Sunday 00:00 UTC) - creates new grid_session
- In-memory chunk cache (Map) with LRU eviction at 200 entries, 30s TTL
- Rate limiting uses MySQL `rate_limits` table, not Redis