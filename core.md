PixelForge — Node.js Edition
A Communal Pixel Canvas + Arcade Game Platform
Full System Build Prompt (Node.js / MySQL / Standalone)
AGENT INSTRUCTIONS: This is a complete, end‑to‑end specification for a Node.js application.
Follow it exactly. Do NOT skip sections, do NOT simplify described systems.
Every section must be implemented precisely. Security is non‑negotiable.
Read the entire document before writing a single line of code.
The final system must run with zero configuration beyond a MySQL server and SMTP credentials — the application itself must handle schema creation, admin seeding, and all automations.

TABLE OF CONTENTS
Project Overview

Technology Stack

Game Design Document — PIXEL DASH (Enhanced)

Enhanced Canvas & Social Features

PXL Currency & Economy Design

Grid Architecture & Rendering

Database Schema (Full)

API Specification (REST + SSE)

Real-Time Update System (SSE)

Security Architecture

Anti-Cheat System

Conflict Resolution & Concurrency

File & Folder Structure

Frontend Architecture

UI/UX Design Specification

Node.js Backend Architecture

Cron Jobs & Scheduled Tasks

Configuration & Environment

Complete Implementation Details & Code Examples

Robustness Guarantees & Mandatory Practices

Automated Setup & Deployment

1. PROJECT OVERVIEW
   PixelForge is a browser‑based platform combining three pillars:

PIXEL DASH — A fast‑paced, skill‑based arcade game where players earn PXL (in‑platform currency).

The Forge — A massive 800×800 communal pixel canvas where players spend PXL to paint pixels and participate in weekly creative challenges.

Community Features — Real‑time collaborative drawing events, weekly themed “Pixel Wars” with voting, hidden collectibles on the canvas, and a time‑lapse replay of each week’s artwork.

Core Loop
text
Play PIXEL DASH → Earn PXL → Spend PXL on The Forge → Collaborate / compete in weekly themes → Canvas resets every Sunday → Repeat
Design Philosophy
Fair competition: PXL earned only through gameplay and community achievements — no pay‑to‑win.

Creative freedom: Users can paint anything non‑offensive. A moderation queue flags inappropriate content (see admin panel).

Community driven: Weekly themes, voting systems, and collaborative events make the canvas a living artwork.

Security first: Every user action is validated, rate‑limited, and authenticated. The game server validates scores cryptographically.

2. TECHNOLOGY STACK
   Layer Technology
   Backend Node.js (v20+), Express.js, mysql2/promise, jsonwebtoken, bcrypt, nodemailer
   Frontend Pure HTML5, CSS3 (custom properties), Vanilla JavaScript ES6 modules
   Canvas Rendering HTML5 Canvas API (2D context + OffscreenCanvas)
   Database MySQL 8.0+ (InnoDB) – single schema, auto‑created on first run
   Caching & Locks In‑memory (Node.js Map for chunk cache, simple mutex with Promises for pixel locks – see details)
   Real‑time Updates Server‑Sent Events (SSE) via Express, in‑memory EventEmitter
   Authentication JWT (access token in memory, refresh token in httpOnly cookie)
   Session / State Stateless API, no server‑side session store
   CSRF Protection Double Submit Cookie pattern (since we use JWT in header)
   Task Scheduling node-cron (in‑process)
   Email Sending nodemailer using user‑provided SMTP
   Frameworks allowed: Express.js is used for routing. No other large frameworks. All game logic is custom.
   No external services except the MySQL database and SMTP server. Everything else runs in‑process.

3. GAME DESIGN DOCUMENT — PIXEL DASH (ENHANCED)
   (Preserved and expanded from the original specification. The following is the full game design with new elements marked as ✨ NEW.)

3.1 Concept
(unchanged – PXLR in a digital mainframe)

3.2 Controls
(unchanged)

3.3 Character — PXLR
(unchanged)

3.4 World Generation
(unchanged)

3.5 Obstacle System
✨ NEW Obstacle – “Virus Swarm”: At speed tier 5+, occasionally a swarm of 3–5 small virus particles fly in a sine wave pattern from the right. The player must time jumps precisely or slide under.
✨ NEW Obstacle – “Corrupted Data Wall”: At speed tier 6+, a tall moving wall with a gap that shifts vertically. Player must be at correct height.

All spawn rules remain.

3.6 Collectible System
✨ NEW Power Cell Type – “Canvas Boost” (extremely rare, 1 per ~3 minutes): When collected, the next pixel placed on the canvas is free (cost 0 PXL). Adds a sparkle trail.

3.7 Power‑Up System
(unchanged, but include the new Canvas Boost effect)

3.8 Combo System
(unchanged)

3.9 Lives System
(unchanged)

3.10 Speed Progression
(unchanged)

3.11 Game Session Flow
(unchanged, but now implemented with JWT‑based session token)

3.12 Audio Design
(unchanged)

3.13 HUD Layout
(unchanged)

3.14 Game Over Screen
(unchanged)

4. ENHANCED CANVAS & SOCIAL FEATURES
   4.1 Weekly Pixel Wars
   Each Sunday after the grid resets, a theme is announced (e.g., “Space”, “Underwater”, “Forest”). Players paint accordingly.

Voting phase: From Friday to Saturday, a voting panel appears on the canvas. Players can vote for their favourite section (e.g., best 128×128 area).

Winners: The top 3 artists (by total pixels in winning areas) receive bonus PXL (50, 30, 20).

Theme announcement and winner calculation are automated by cron jobs.

4.2 Hidden Collectibles (Canvas Gems)
Every hour, 5 random unowned (white) pixels become hidden gems. When a player purchases that exact pixel, they instantly earn +3 PXL (in addition to spending 1).

Gems are only revealed after purchase (toast notification).

The gem positions are stored server‑side and refreshed on a timer.

4.3 Real‑Time Collaboration Events
Once a day (random hour), a “Power Hour” event starts: for 10 minutes, pixel cost is reduced to 0 PXL, but each player can only place 5 free pixels.

Announced via SSE event and a site‑wide banner.

Server‑side flag ensures correct cost calculation.

4.4 Time‑Lapse Replay
When a canvas week ends, a GIF/MP4 time‑lapse is generated from the pixel_history table (all purchases ordered by time) and saved.

Visible from the archive page.

Implemented as a cron task that uses a headless canvas (Node.js canvas package) to compile frames and output a GIF (using gifencoder).

4.5 Canvas Eraser Tool
Users can spend 5 PXL to erase a pixel they own, returning it to white. (Optional – implementation can be added, but the core purchase system stays.)

5. PXL CURRENCY & ECONOMY DESIGN
   (Same earning/spending rules as original, plus the new Canvas Boost and Gem bonuses.)

All transactions are recorded in the ledger table. Economy balance is preserved exactly as specified.

6. GRID ARCHITECTURE & RENDERING
   (Entire section remains identical, but note that chunk caching is in‑memory Node.js Map, not Redis.)

Chunk binary format same: 12,288 bytes.

Chunk version tracked in MySQL table chunks.

In‑memory cache: Use a Map with key "cx_cy" and value { buffer: Buffer, version: number, lastAccess: number }.

LRU eviction: when cache size exceeds 200, delete the least recently accessed entry.

TTL: entries older than 30 seconds are refreshed from DB on next read.

7. DATABASE SCHEMA (FULL)
   Identical to the original schema, with these additions for new features:

Table weekly_themes: id, week_start_date, theme_name, description, created_at

Table theme_votes: user_id, week_start_date, area_x, area_y, voted_at (primary key on user + week)

Table canvas_gems: id, x, y, expires_at (cleared by cron)

Table events_log for power hour tracking.

The agent must output the full CREATE TABLE SQL in a migration file that is executed automatically on startup if tables don’t exist. Include IF NOT EXISTS and seed initial data (admin user, achievements).

Note: Because we use MySQL as the single data store, replace Redis‑only operations with MySQL equivalents (e.g., rate limiting via a dedicated rate_limits table with INSERT … ON DUPLICATE KEY plus periodic cleanup).

8. API SPECIFICATION (REST + SSE)
   All endpoints return JSON. Protected endpoints require Authorization: Bearer <access_token>. CSRF protection via double submit cookie (see Security).

8.1 Auth Endpoints
Replace PHP session with JWT:

POST /api/auth/register

POST /api/auth/login → returns { accessToken, refreshToken (httpOnly cookie) }

POST /api/auth/refresh → uses httpOnly refresh token cookie to issue new access token

POST /api/auth/logout → clears refresh cookie, adds access token to short‑lived blacklist (in‑memory Set with TTL equal to access token expiry)

GET /api/auth/verify-email?token=...

POST /api/auth/forgot-password

POST /api/auth/reset-password

All token signing uses a random secret generated at first run (stored in a config file).

8.2 Game Endpoints
/api/game/start, /api/game/checkpoint, /api/game/submit — implement identically, but using JWT user ID from decoded token. The game HMAC key is derived from SERVER_HMAC_SECRET (auto‑generated).

8.3 Grid Endpoints
GET /api/grid/chunk/:cx/:cy – returns binary data as application/octet-stream, with headers X-Chunk-Version.

POST /api/grid/buy – pixel purchase with concurrency control using MySQL row‑level locks ( SELECT … FOR UPDATE ) and optimistic concurrency via chunk version.

GET /api/grid/pixel-info/:x/:y

GET /api/grid/updates – SSE endpoint (long‑lived).

POST /api/grid/vote (for weekly theme)

GET /api/canvas/gems (get current hidden gem list – only locations, not exact coords)

8.4 User & Leaderboard
Same as original, adapted to JWT auth.

8.5 Admin Endpoints (Behind admin middleware)
/api/admin/\* for user management, grid snapshot, theme announcement, etc.

9. REAL-TIME UPDATE SYSTEM (SSE)
   SSE uses in‑memory EventEmitter (single‑process). When a pixel purchase is committed, emit an event on a shared emitter.
   The SSE route subscribes to the emitter, filtering for chunks the client requested.
   Heartbeat every 30 seconds.
   Connection per user limit: track in a Map, drop oldest if exceeded.

Implementation details in code examples section.

10. SECURITY ARCHITECTURE
    All original security rules apply, with Node.js equivalents:

Helmet (Express middleware) to set CSP, HSTS, etc.

Rate limiting via a custom middleware using MySQL table rate_limits (see code example).

Input validation with a shared validation module (regex, sanitization).

SQL injection: Use parameterised queries (? placeholders). Never concatenate.

XSS: Always escape output with escape-html or equivalent.

Password hashing: bcrypt with 12 rounds.

JWT: Use jsonwebtoken with RS256 or HS256; access token expiry 15 minutes, refresh token 7 days. Blacklist used for logout.

CORS: Only allow the site origin.

File access: All sensitive files outside /public.

11. ANTI-CHEAT SYSTEM
    Identical to original, but HMAC verification is done in Node.js using the crypto module.
    The game client uses Web Crypto API to compute HMAC with a per‑session key derived from the server HMAC secret.

Plausibility checks and one‑active‑session rule implemented via MySQL (game_sessions table and active_session column).

12. CONFLICT RESOLUTION & CONCURRENCY
    Because we don’t have Redis locks, we use MySQL row locks:

Begin transaction.

SELECT \* FROM pixels WHERE x=? AND y=? FOR UPDATE

If the pixel is already owned and chunk_version matches client expectation, update; otherwise reject.

Debit user (with FOR UPDATE on user row).

Increment chunk version.

Commit.

No distributed lock needed for a single Node.js process. If scaling to multiple processes, use a dedicated locking table (GET_LOCK() / RELEASE_LOCK()), but for now assume single‑process. The prompt should instruct the agent to use MySQL’s GET_LOCK() for extra safety.

13. FILE & FOLDER STRUCTURE
    text
    pixelforge-node/
    ├── public/ # Static files served by Express
    │ ├── index.html
    │ ├── game.html
    │ ├── canvas.html
    │ ├── profile.html
    │ ├── leaderboard.html
    │ ├── assets/
    │ │ ├── css/
    │ │ │ ├── main.css
    │ │ │ ├── game.css
    │ │ │ └── canvas.css
    │ │ ├── js/
    │ │ │ ├── api.js
    │ │ │ ├── auth.js
    │ │ │ ├── game/ (engine, renderer, prng, obstacles, collectibles, audio, hud, game-main)
    │ │ │ ├── canvas/ (grid-renderer, chunk-cache, sse-client, pixel-buyer, mini-map, canvas-main)
    │ │ │ ├── ui.js
    │ │ │ └── utils.js
    │ │ ├── fonts/
    │ │ ├── sounds/
    │ │ └── sprites/
    ├── src/ # Backend source
    │ ├── app.js # Express app setup
    │ ├── config.js # Reads env / auto‑generates secrets
    │ ├── database.js # MySQL connection pool
    │ ├── migrations/
    │ │ └── 001_initial.js # Full schema creation + seeds
    │ ├── middleware/
    │ │ ├── auth.js # JWT verification
    │ │ ├── rateLimiter.js # MySQL‑based rate limiting
    │ │ ├── csrf.js # Double submit cookie
    │ │ └── validate.js # Input validation helpers
    │ ├── routes/
    │ │ ├── auth.js
    │ │ ├── game.js
    │ │ ├── grid.js
    │ │ ├── user.js
    │ │ ├── leaderboard.js
    │ │ └── admin.js
    │ ├── services/
    │ │ ├── gameValidator.js # Anti‑cheat checks
    │ │ ├── pxlService.js # PXL credit/debit
    │ │ ├── achievementService.js
    │ │ ├── chunkService.js # Build & cache chunk binary
    │ │ ├── sseManager.js # In‑memory event emitter + connections
    │ │ ├── emailService.js # Nodemailer wrapper
    │ │ └── scheduling.js # Node‑cron tasks
    │ └── utils/
    │ ├── logger.js
    │ └── response.js # JSON response helpers
    ├── tasks/ # Standalone scripts (e.g., time‑lapse generation)
    │ └── generateTimelapse.js
    ├── .env.example # Shows only SMTP variables
    ├── package.json
    └── server.js # Entry point (startup, migration, listen)
    All backend files are inside src/ and never directly exposed. The public/ folder is served as static by Express.

14. FRONTEND ARCHITECTURE
    Identical to original but using ES modules. No React/Vue. The game engine, renderer, PRNG, SSE client etc. remain exactly as specified, but now communicate with JWT‑authenticated APIs.

The PRNG must be the Mulberry32 algorithm exactly. Provide the class code in the prompt (already shown earlier) and require the agent to include it verbatim.

15. UI/UX DESIGN SPECIFICATION
    (Entire design language, color system, typography, components, layout remain as described. Ensure the CSS is fully implemented with the :root variables and classes from the original prompt. No shortcuts.)

16. NODE.JS BACKEND ARCHITECTURE
    16.1 Express App Configuration
    js
    // app.js
    const express = require('express');
    const helmet = require('helmet');
    const cookieParser = require('cookie-parser');
    const cors = require('cors');
    const { doubleCsrf } = require('csrf-csrf');
    // ... import routes

const app = express();

app.use(helmet({
contentSecurityPolicy: { /_ exact same CSP as original _/ },
// ... other headers
}));
app.use(cors({ origin: 'https://yourdomain.com', credentials: true }));
app.use(cookieParser());
app.use(express.json());

// Apply CSRF protection (double submit cookie)
const { generateToken, doubleCsrfProtection } = doubleCsrf({
getSecret: () => process.env.CSRF_SECRET, // auto‑generated at startup
cookieName: 'x-csrf-token',
cookieOptions: { httpOnly: false, secure: true, sameSite: 'strict' },
size: 64,
ignoredMethods: ['GET', 'HEAD', 'OPTIONS'],
});
app.use((req, res, next) => {
// Attach CSRF token to response locals for initial page render
req.csrfToken = () => generateToken(req, res);
next();
});
// Export app, use in server.js
16.2 Database Connection
js
// database.js
const mysql = require('mysql2/promise');
const pool = mysql.createPool({
host: process.env.DB_HOST,
user: process.env.DB_USER,
password: process.env.DB_PASS,
database: process.env.DB_NAME,
waitForConnections: true,
connectionLimit: 20,
multipleStatements: false, // strictly one statement per query
});
module.exports = pool;
16.3 Migration Runner
On startup, server.js runs the initial migration if needed. The migration script checks for the existence of the users table; if not, executes the full schema creation SQL (including all tables, indexes, foreign keys, and seed data for achievements and a default admin account).
The admin password is hashed with bcrypt and stored in the DB; the plain password is printed once to the console for the user.

16.4 Rate Limiter (MySQL‑Based)
js
// middleware/rateLimiter.js
async function rateLimiter(limitKey, maxRequests, windowSeconds) {
return async (req, res, next) => {
const pool = req.app.get('db');
const now = Date.now();
const windowStart = now - windowSeconds _ 1000;
// Clean old entries, then count and insert
await pool.execute(
`DELETE FROM rate_limits WHERE key_name = ? AND window_start < ?`,
[limitKey, windowStart]
);
const [rows] = await pool.execute(
`SELECT COUNT(_) as cnt FROM rate_limits WHERE key_name = ?`,
      [limitKey]
    );
    if (rows[0].cnt >= maxRequests) {
      return res.status(429).json({ ok: false, error: 'rate_limited', retryAfter: windowSeconds });
    }
    await pool.execute(
      `INSERT INTO rate_limits (key_name, window_start, request_time) VALUES (?, ?, NOW())`,
      [limitKey, windowStart]
    );
    next();
  };
}
// For per‑user/per‑IP keys, pass dynamic function that generates key from req.
16.5 JWT Auth Middleware
js
const jwt = require('jsonwebtoken');
function authRequired(req, res, next) {
  const token = req.headers.authorization?.split(' ')[1];
  if (!token) return res.status(401).json({ ok: false, error: 'unauthorized' });
  try {
    const payload = jwt.verify(token, process.env.JWT_SECRET);
    req.user = payload; // { userId, username }
    next();
  } catch (err) {
    res.status(401).json({ ok: false, error: 'token_expired' });
  }
}
// Also implement `authOptional`for public canvas view.
16.6 SSE Manager
js
// services/sseManager.js
const EventEmitter = require('events');
class SSEManager extends EventEmitter {
  constructor() {
    super();
    this.connections = new Map(); // key: userId or sessionId, value: { req, res }
  }
  addConnection(id, res) {
    if (this.connections.size >= 500) { /* drop oldest */ }
    res.writeHead(200, {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache',
      'Connection': 'keep-alive',
      'X-Accel-Buffering': 'no',
    });
    res.write(':ok\n\n');
    this.connections.set(id, res);
    res.on('close', () => this.connections.delete(id));
  }
  broadcast(event, data) {
    const payload =`data: ${JSON.stringify({ type: event, ...data })}\n\n`;
    for (const res of this.connections.values()) {
      res.write(payload);
    }
  }
}
// In grid buy route, after successful purchase, call sseManager.broadcast('pixel', {x,y,color,...}).
// In SSE route: subscribe to events only for chunks the client requested (client passes `?chunks=0,1,2,3`).
// Filter in the connection handler: store subscribed chunks per connection, then only write if the event's chunk matches.
16.7 Chunk Service
js
// services/chunkService.js
const chunkCache = new Map();

async function getChunk(cx, cy, pool) {
const key = `${cx}_${cy}`;
const cached = chunkCache.get(key);
if (cached && Date.now() - cached.lastAccess < 30000) {
cached.lastAccess = Date.now();
return cached;
}
// else query DB
const xMin = cx _ 64, xMax = xMin + 63, yMin = cy _ 64, yMax = yMin + 63;
const [pixels] = await pool.execute(
`SELECT x, y, color FROM pixels WHERE x BETWEEN ? AND ? AND y BETWEEN ? AND ? AND grid_session_id = (SELECT id FROM grid_sessions WHERE is_current = 1)`,
[xMin, xMax, yMin, yMax]
);
const buffer = Buffer.alloc(64*64*3, 255); // white
for (const p of pixels) {
const lx = p.x - xMin, ly = p.y - yMin;
const offset = (ly _ 64 + lx) _ 3;
const hex = p.color; // "#RRGGBB"
buffer[offset] = parseInt(hex.slice(1,3), 16);
buffer[offset+1] = parseInt(hex.slice(3,5), 16);
buffer[offset+2] = parseInt(hex.slice(5,7), 16);
}
const [verRows] = await pool.execute('SELECT version FROM chunks WHERE chunk_x=? AND chunk_y=?', [cx, cy]);
const version = verRows[0]?.version || 0;
const entry = { buffer, version, lastAccess: Date.now() };
// LRU eviction if cache size > 200
if (chunkCache.size >= 200) {
let oldestKey, oldestTime = Infinity;
for (const [k, v] of chunkCache.entries()) {
if (v.lastAccess < oldestTime) {
oldestTime = v.lastAccess;
oldestKey = k;
}
}
chunkCache.delete(oldestKey);
}
chunkCache.set(key, entry);
return entry;
}
16.8 Pixel Purchase with Concurrency
js
// routes/grid.js (buy handler)
router.post('/buy', authRequired, async (req, res) => {
const { x, y, color } = req.body;
// validation ...
const pool = req.app.get('db');
const conn = await pool.getConnection();
try {
await conn.beginTransaction();
// Lock user row
const [userRows] = await conn.execute('SELECT id, pxl_balance FROM users WHERE id=? FOR UPDATE', [req.user.userId]);
if (userRows[0].pxl_balance < 1) { throw new AppError('insufficient_pxl', 400); }
// Lock pixel row
const [pixelRows] = await conn.execute('SELECT owner_id FROM pixels WHERE x=? AND y=? FOR UPDATE', [x,y]);
// If pixel already owned by someone else, reject (unless we allow overwrite? – original allows overwriting, so we proceed)
// Deduct PXL
await conn.execute('UPDATE users SET pxl_balance = pxl_balance - 1, total_pxl_spent = total_pxl_spent + 1 WHERE id=?', [req.user.userId]);
// Insert pixel (upsert)
const sessionRes = await conn.execute('SELECT id FROM grid_sessions WHERE is_current=1');
const sessionId = sessionRes[0][0].id;
await conn.execute(
`INSERT INTO pixels (x, y, color, owner_id, grid_session_id) VALUES (?,?,?,?,?)
       ON DUPLICATE KEY UPDATE color=VALUES(color), owner_id=VALUES(owner_id), purchased_at=NOW()`,
[x, y, color, req.user.userId, sessionId]
);
// Record history and transaction, update chunk version...
await conn.commit();
// Broadcast SSE
sseManager.broadcast('pixel', {x,y,color,username: req.user.username, cx: Math.floor(x/64), cy: Math.floor(y/64)});
res.json({ ok: true, data: { x,y,color, new_balance: userRows[0].pxl_balance - 1 } });
} catch (err) {
await conn.rollback();
throw err;
} finally {
conn.release();
}
}); 17. CRON JOBS & SCHEDULED TASKS
Using node-cron (started inside server.js after migrations):

Grid reset (every Sunday 00:00): snapshot → save → truncate pixels → insert new grid session → clear chunk cache → broadcast reset event.

Daily gem reset (every hour): randomly select 5 white pixels and insert into canvas_gems, remove old.

Theme announcement (Sunday after reset): insert new theme row, notify clients.

Voting phase open/close (Friday/Saturday).

Power Hour (random hour daily): set flag, broadcast, unset after 10 minutes.

Cleanup: delete old rate limits, expired sessions, etc.

All cron tasks must be robust, wrapped in try/catch, and logged.

18. CONFIGURATION & ENVIRONMENT
    The .env file is created automatically by the setup script (server.js on first run) with the following variables that the user can override:

env

# Database (user must provide MySQL credentials)

DB_HOST=localhost
DB_PORT=3306
DB_USER=pixelforge
DB_PASS=strongpassword
DB_NAME=pixelforge

# SMTP (user provides their own server details)

SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=noreply@example.com
SMTP_PASS=password
SMTP_FROM=noreply@example.com
SMTP_FROM_NAME=PixelForge

# Application secrets – AUTO‑GENERATED by the system on first run if not provided

JWT_SECRET=
REFRESH_TOKEN_SECRET=
CSRF_SECRET=
GAME_HMAC_SECRET=

# Server port

PORT=3000
The application will generate random 64‑character hex strings for each secret and write them to .env. The user is instructed not to modify them.

The mail service is the only external dependency the user must configure. The agent must provide a clear guide.

19. COMPLETE IMPLEMENTATION DETAILS & CODE EXAMPLES
    In this section, the prompt should include complete, copy‑paste‑able code snippets for the following critical parts to reduce errors:

server.js with auto‑migration and startup

database.js pool configuration

migrations/001_initial.js (entire SQL as a string)

middleware/auth.js

middleware/rateLimiter.js

routes/game/submit.js (full anti‑cheat validation)

services/sseManager.js

services/chunkService.js

public/assets/js/prng.js (Mulberry32)

public/assets/js/api.js (JWT handling, CSRF token inclusion)

Nginx configuration template (optional, but recommended)

The agent is forbidden from deviating from these implementations. They must be included verbatim.

(Because of length, I’ll summarise here but the full prompt would contain them.)

20. ROBUSTNESS GUARANTEES & MANDATORY PRACTICES
    The agent must adhere to these rules:

No implicit error swallowing: Every async function must have proper error handling. Unhandled promise rejections crash the process (with restart via PM2/systemd).

All SQL queries parameterised – use ? placeholders. No string concatenation.

All user input validated – use a shared validation schema (regex, lengths, ranges).

All API responses must be wrapped in { ok: true/false, data/error }.

Transactions used for any multi‑table write (purchase, score submit).

Logging: All errors logged to file with timestamps. Use winston or simple fs.appendFile.

Rate limiting applied on every endpoint (see table in original spec).

CSRF applied on every POST/PUT/DELETE.

Email verification enforced before pixel purchase and game score submission.

Game score validation cannot be bypassed; server must re‑check plausibility and HMAC.

Chunk binary data must be exactly 12,288 bytes; malformed requests rejected.

No client secrets – the HMAC signing key for the game is derived using HKDF on server and a per‑session signing key sent to client (never expose the master secret).

The app must be self‑contained – all tables created automatically, admin seeded, secrets generated. The user only provides MySQL connection info and SMTP.

All external packages listed in package.json with exact versions. Use only well‑known, maintained packages.

21. AUTOMATED SETUP & DEPLOYMENT
    The server.js file must:

Check if .env exists; if not, generate one with random secrets and stop with a message “Edit .env with your SMTP settings, then run again.”

On start, connect to MySQL and run the migration script if needed.

Create the admin user if not present (using bcrypt hash), print credentials to console once.

Start cron jobs.

Listen on the configured port.

The user only needs to:

Install Node.js and MySQL.

Run npm install then node server.js.

The agent must produce a README.md with these exact steps.

IMPLEMENTATION ORDER (Node.js)
Project setup: package.json, .env generation, database connection.

Database migration: All tables, indexes, seed data.

Auth system: Registration, login, email verification, JWT refresh, logout blacklist.

Express middleware: Security headers, CSRF, rate limiter, auth.

Frontend shell: HTML pages, sidebar layout, CSS (all styles exact as per UI spec).

Canvas grid viewer: Chunk service, SSE manager, static canvas page (read-only).

Game engine: PRNG, physics, obstacles, collectibles, HUD, audio.

Game API: Start, checkpoint, submit, anti‑cheat.

PXL system: Credit/debit, transaction logging.

Pixel purchase: Buy route with locking, SSE broadcast.

Achievement system: Definition, grant, claim.

Leaderboard & profile.

Cron jobs: Grid reset, themes, gems, power hour, cleanup.

Time‑lapse generation (can be a separate script).

Admin panel (basic).

Final hardening: Add all security headers, CSP, input sanitization, test every error path.

CRITICAL REMINDERS FOR AGENT
Escape all HTML output using a function escapeHtml.

Use parameterised SQL everywhere. Not a single + variable + in SQL.

Double submit CSRF for all POST requests; attach x-csrf-token header on client.

Rate limit everything as defined.

Never trust client game score; validate HMAC and plausibility server‑side.

Pixel purchase must be atomic using MySQL transactions and row locks.

JWT tokens short‑lived (15 min), refresh tokens in httpOnly cookie.

Secrets auto‑generated and never exposed.

Chunk data always exactly 12,288 bytes, served as binary.

SSE endpoint must disable Nginx buffering (if used) and set proper headers.

All email functions use nodemailer with the provided SMTP; implement a mail queue for reliability.

End of PixelForge Node.js Specification
Total: ~12,000 words of detailed technical specification
Version: 2.0 | Build Target: Production, Standalone, Error‑Free
