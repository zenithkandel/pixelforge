# PixelForge Implementation Plan

## Overview

This plan outlines the step-by-step implementation of **PixelForge**, a browser-based platform combining:
1. **PIXEL DASH** - An endless runner arcade game where players earn PXL (in-platform currency)
2. **The Forge** - An 800×800 communal pixel canvas where players spend PXL to paint pixels

The canvas resets every 7 days (Sunday 00:00 UTC).

---

## Phase 1: Infrastructure Setup

### 1.1 Environment & Configuration
- [ ] Create `.env` file in project root with all required variables:
  - APP_ENV, APP_SECRET, GAME_HMAC_KEY
  - DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
  - REDIS_HOST, REDIS_PORT, REDIS_PASS, REDIS_DB, REDIS_SESSION_DB
  - SMTP credentials for email
  - ADMIN_USERNAME, ADMIN_PASSWORD_HASH
  - GRID_RESET_DAY=0, GRID_PIXEL_COST=1

- [ ] Create includes/config.php to load .env and define all constants
- [ ] Create includes/bootstrap.php that:
  - Sets error reporting (log all, display none in production)
  - Loads config
  - Starts custom Redis-backed session
  - Sends all security headers
  - Sets up global exception handler

### 1.2 Database Setup
- [ ] Create database `pixelforge` with charset utf8mb4
- [ ] Create ALL 17 tables in exact order specified in Section 6:

| Order | Table | Purpose |
|-------|-------|---------|
| 1 | grid_sessions | Canvas sessions (current + history) |
| 2 | users | User accounts |
| 3 | pxl_transactions | PXL ledger (append-only) |
| 4 | pixels | Current pixel state (800×800) |
| 5 | chunks | Chunk version tracking |
| 6 | game_sessions | Game session records |
| 7 | scores | Leaderboard data |
| 8 | pixel_history | Permanent pixel purchase record |
| 9 | achievements | Achievement definitions |
| 10 | user_achievements | User-achievement junction |
| 11 | login_attempts | Security logging |
| 12 | admins | Admin users |

- [ ] Seed initial data:
  - First grid session (is_current=1)
  - All achievement definitions (20 achievements)

### 1.3 Redis Setup
- [ ] Configure Redis connection in includes/redis.php
- [ ] Verify Redis is running and accessible
- [ ] Test Pub/Sub functionality for SSE

### 1.4 Nginx Configuration
- [ ] Configure Nginx with PHP-FPM
- [ ] Set up security headers (CSP, X-Frame-Options, etc.)
- [ ] Block direct access to includes/, cron/, logs/, .env
- [ ] Configure SSE endpoint (disable buffering)
- [ ] Set up SSL (Let's Encrypt)
- [ ] Configure static file caching

---

## Phase 2: Core Backend Infrastructure

### 2.1 Includes/Helper Files (Create in exact order)

| File | Purpose |
|------|---------|
| includes/config.php | Load .env, define constants |
| includes/db.php | PDO singleton with proper settings |
| includes/redis.php | Redis singleton |
| includes/session.php | Custom Redis-backed session handler |
| includes/security.php | Headers, CSRF, input helpers |
| includes/response.php | JSON response helpers (respond_success, respond_error) |
| includes/validate.php | Input validation functions (username, email, password, color, coord) |
| includes/rate_limit.php | Redis-based sliding window rate limiting |
| includes/auth.php | Auth helpers (require_auth, require_verified, get_current_user_data) |
| includes/logger.php | Error/audit logging |
| includes/game_validator.php | Anti-cheat validation functions |
| includes/pxl.php | PXL credit/debit functions (within transactions) |
| includes/achievement.php | Achievement check/grant functions |

### 2.2 Bootstrap Integration
- [ ] Create includes/bootstrap.php that requires all includes in correct order
- [ ] Every API endpoint will start with: `require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';`

---

## Phase 3: Authentication System

### 3.1 API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| /api/auth/register.php | POST | Public | User registration with validation |
| /api/auth/login.php | POST | Public | Login with rate limiting |
| /api/auth/logout.php | POST | Required | Logout, destroy session |
| /api/auth/verify.php | GET | Public | Email verification |
| /api/auth/forgot-password.php | POST | Public | Password reset request |
| /api/auth/reset-password.php | POST | Public | Password reset |
| /api/auth/me.php | GET | Required | Get current user info |

### 3.2 Registration Flow
- [ ] Validate username (^[a-zA-Z0-9_]{3,20}$)
- [ ] Validate email (filter_var)
- [ ] Validate password (min 8 chars, 1 letter, 1 number)
- [ ] Check uniqueness (username, email)
- [ ] Hash password with bcrypt cost 12
- [ ] Send verification email with signed token (expires 24h)
- [ ] Log to login_attempts table

### 3.3 Login Flow
- [ ] Check IP rate limit (Redis)
- [ ] Fetch user by username
- [ ] Check account lockout
- [ ] Verify password
- [ ] On fail: increment failed_login_count, set lockout if ≥5
- [ ] On success: session_regenerate_id(true), reset failed_login_count
- [ ] Process daily login streak
- [ ] Process daily bonus

### 3.4 Session Security
- [ ] Use strict mode, httponly, secure, samesite=Strict
- [ ] Custom session handler backed by Redis (sess:{session_id})
- [ ] Session expires after 24 hours

---

## Phase 4: Frontend Shell & UI

### 4.1 Public Pages (PHP)

| Page | Description |
|------|-------------|
| index.php | Landing page (login/register split-view) |
| canvas.php | The Forge (public read-only) |
| game.php | PIXEL DASH (auth required) |
| profile.php | User profile (auth required) |
| leaderboard.php | Leaderboard (public) |
| verify.php | Email verification redirect |

### 4.2 CSS Architecture
- [ ] Create assets/css/main.css with:
  - CSS custom properties (Section 14.3)
  - Typography (Outfit, JetBrains Mono)
  - Layout (sidebar, main content, header)
  - Cards, buttons, form inputs
  - Toast notifications
  - Responsive design (media queries)

### 4.3 Sidebar Navigation
- [ ] Brand section (logo, name, tagline)
- [ ] Nav items: The Forge, Pixel Dash, Leaderboard, Profile
- [ ] Footer: PXL balance, username
- [ ] Responsive: collapse to icons on < 768px

### 4.4 JavaScript Architecture
- [ ] Create assets/js/utils.js (h, getCsrfToken, etc.)
- [ ] Create assets/js/ui.js (toast, modal, tooltips)
- [ ] Create assets/js/api.js (ApiClient class with CSRF handling)
- [ ] All JS as ES6 modules (type="module")

---

## Phase 5: Canvas Grid Viewer (The Forge)

### 5.1 Grid Architecture
- [ ] Grid size: 800 × 800 pixels
- [ ] Chunk size: 64 × 64 pixels
- [ ] Total chunks: 32 × 32 = 1024 chunks
- [ ] Each chunk: 12,288 bytes (64×64×3 RGB)

### 5.2 Backend API

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| /api/grid/chunk.php | GET | Public | Get chunk binary data |
| /api/grid/buy.php | POST | Required | Purchase pixel |
| /api/grid/pixel-info.php | GET | Public | Get pixel owner info |
| /api/grid/updates.php | GET | Public | SSE for real-time updates |

### 5.3 Chunk Loading
- [ ] Implement build_chunk_cache() function
- [ ] Cache chunks in Redis (TTL 300s)
- [ ] Track chunk versions in Redis and MySQL
- [ ] Handle 304 Not Modified when version matches

### 5.4 Frontend Canvas
- [ ] Create assets/js/canvas/grid-renderer.js (GridRenderer class)
- [ ] Create assets/js/canvas/chunk-cache.js (LRU cache, max 200 chunks)
- [ ] Implement virtual scrolling (render visible chunks only)
- [ ] Implement zoom levels: 1×, 2×, 4×, 8×, 16×
- [ ] Draw grid lines at zoom ≥ 4×

### 5.5 Pan & Zoom Controls
- [ ] Mouse wheel zoom
- [ ] Click and drag to pan
- [ ] Touch: pinch to zoom, drag to pan
- [ ] Mini-map (200×200px) with viewport indicator
- [ ] Coordinate search (Go to x,y)
- [ ] Keyboard shortcuts: Space (toggle Pan/Paint), +/- (zoom), G (Go dialog)

### 5.6 Color Palette
- [ ] 32 colors in 4 rows of 8 (Section 5.6)
- [ ] Custom hex input with validation
- [ ] Color preview in toolbar

### 5.7 Pixel Purchase Flow
- [ ] Click pixel → show confirmation popover
- [ ] Confirm → send POST /api/grid/buy.php
- [ ] Optimistic update (show pending state)
- [ ] Handle responses: success, conflict, insufficient_funds, rate_limited
- [ ] Update balance, re-render affected chunk

### 5.8 SSE Real-Time Updates
- [ ] Connect to /api/grid/updates.php
- [ ] Subscribe to visible chunks (+1 buffer)
- [ ] On pixel event: update local cache, re-render
- [ ] Reconnect with exponential backoff on disconnect
- [ ] Heartbeat every 25 seconds

---

## Phase 6: PIXEL DASH Game

### 6.1 Game Architecture (Pure JavaScript, no canvas libraries)

| File | Purpose |
|------|---------|
| assets/js/game/prng.js | Seeded PRNG (Mulberry32) |
| assets/js/game/engine.js | Core game loop, physics |
| assets/js/game/renderer.js | Canvas sprite/scene rendering |
| assets/js/game/obstacles.js | Obstacle generation and logic |
| assets/js/game/collectibles.js | Shard and power-up logic |
| assets/js/game/audio.js | Web Audio API management |
| assets/js/game/hud.js | HUD rendering and updates |
| assets/js/game/game-main.js | Entry point, session management |

### 6.2 Game Mechanics

#### Character (PXLR)
- [ ] 16×16 pixel sprite (run, jump, slide, death frames)
- [ ] Cyan (#00F5FF) with white eye
- [ ] Glow effect when powered-up

#### World Generation
- [ ] Seeded PRNG (server issues seed)
- [ ] 3-layer parallax background
- [ ] Neon-trimmed data floor
- [ ] Procedural obstacle and collectible spawning

#### Obstacles (Section 3.5)
- [ ] Ground: Glitch Block, Double Stack, Spike Array, Crawl Barrier, Triple Stack, Combo Block
- [ ] Aerial: Firewall Beam, High Beam, Data Spike, Double Beam
- [ ] Special: Glitch Zone, Quantum Block, Data Storm (unlock at tier 3+)
- [ ] Minimum gaps, spawn weights, special rules

#### Collectibles (Section 3.6)
- [ ] Color Shards: Gray (+1), Red (+5), Blue (+5), Green (+10), Rainbow Prism (+50)
- [ ] Power Cells: Random power-up activation

#### Power-Ups (Section 3.7)
- [ ] SHIELD (30%, 8s) - One hit absorption
- [ ] MAGNET (25%, 12s) - Auto-collect within 120px
- [ ] TIMEWARP (20%, 6s) - 40% speed reduction
- [ ] SCORE SURGE (15%, 15s) - 3× score multiplier
- [ ] EXTRA LIFE (7%, instant) - Restore 1 life
- [ ] PIXEL BOMB (3%, instant) - Explode obstacles to shards

#### Combo System (Section 3.8)
- [ ] Track consecutive shards without missing
- [ ] Multipliers: 1×, 1.5×, 2×, 3×, 4× (MAX)
- [ ] Visual effects at each tier

#### Lives System
- [ ] 3 lives per session
- [ ] Mercy invincibility 2.5s after hit
- [ ] Game over when all lives lost

#### Speed Progression
- [ ] 8 speed tiers with increasing BPS
- [ ] Score thresholds unlock new features
- [ ] "SPEED UP" flash effect between tiers

### 6.3 Game Audio
- [ ] Web Audio API (no external library)
- [ ] Background music: 3 tracks (switch at tier 3 and 6)
- [ ] SFX: jump, collect, hit, powerup, death, levelup
- [ ] Mute toggle in HUD

### 6.4 Game API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| /api/game/start.php | POST | Required | Start new game session |
| /api/game/checkpoint.php | POST | Required | Send checkpoint data |
| /api/game/submit.php | POST | Required | Submit final score |

### 6.5 Anti-Cheat Implementation
- [ ] Server-issued session_id + seed + HMAC
- [ ] Client stores token and computes HMAC for checkpoint/submit
- [ ] Score plausibility validation (MAX_SCORE_PER_SECOND_HARD = 200, SUSTAINED = 80)
- [ ] Checkpoint chain validation (monotonically increasing)
- [ ] One active session per user (Redis key)
- [ ] Session expiry after 2 hours

### 6.6 Game Session Flow
1. User clicks PLAY → Server issues session token + seed + HMAC
2. Client seeds PRNG, generates obstacles
3. Every 30s: client sends checkpoint (score, lives, speed_tier, HMAC)
4. Game ends (death or quit) → client sends final score payload
5. Server validates entire session → calculates PXL → credits account
6. Client receives: pxl_earned, new_balance, rank, achievements

---

## Phase 7: PXL Economy System

### 7.1 Earning PXL
- [ ] Base conversion: 200 score = 1 PXL (server-calculated)
- [ ] Daily First Game Bonus: 2× PXL for first game of day
- [ ] Daily High Score Bonus: +5 PXL if beats daily best
- [ ] Combo Tier Bonuses: +1, +2, +5, +10 PXL

### 7.2 Spending PXL
- [ ] 1 pixel = 1 PXL (anywhere on canvas)
- [ ] No premium zones
- [ ] Users cannot transfer PXL

### 7.3 Transaction Logging
- [ ] Every PXL change recorded in pxl_transactions
- [ ] Types: game_earn, pixel_spend, achievement, daily_bonus, streak_bonus, combo_bonus, daily_highscore_bonus, admin_credit, admin_debit

### 7.4 Daily Login Streak
- [ ] Track login_streak in users table
- [ ] Award bonus on first login of day
- [ ] Streak bonuses: 2, 3, 5, 8, 15, 25, 50 PXL at 1,2,3,5,7,14,30 days

---

## Phase 8: Achievement System

### 8.1 Achievement Definitions (20 total)
- [ ] Game achievements: first_game, speed_tier_3/5/7, score_500/2000/5000/10000, combo_15/35, rainbow_5, bomb_used
- [ ] Canvas achievements: first_pixel, pixels_50/250/1000
- [ ] Overall: total_earned_100
- [ ] Streak: streak_3/7/30

### 8.2 Backend Logic
- [ ] check_and_grant_achievements() function
- [ ] Call from: game_submit, pixel_buy, login contexts
- [ ] Store earned achievements in user_achievements table
- [ ] PXL credited when user CLAIMS via UI (not auto-credited)

### 8.3 API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| /api/user/achievements.php | GET | Required | Get all achievements with earned status |
| /api/user/claim-achievement.php | POST | Required | Claim earned achievement |

---

## Phase 9: Leaderboard System

### 9.1 Database Queries
- [ ] Daily: today's scores
- [ ] Weekly: this week's scores
- [ ] All-Time: all scores

### 9.2 API Endpoint
- [ ] GET /api/leaderboard.php?type={daily|alltime|weekly}&page=1&limit=20
- [ ] Cached in Redis (60s daily, 300s weekly, 600s alltime)
- [ ] Rate limit: 60 per minute per IP

### 9.3 Frontend
- [ ] Tabs for Daily | Weekly | All-Time
- [ ] Table: Rank | Player | Score | PXL Earned | Duration | Date
- [ ] Top 3 highlighting (gold/silver/bronze)
- [ ] User's own row highlighted in accent

---

## Phase 10: Profile Page

### 10.1 Sections
- [ ] User Card: avatar (generated from username hash), username, join date, streak badge
- [ ] Stats Row: Total PXL Earned, Pixels Painted, Best Score, Games Played
- [ ] Achievements Grid: earned (color) vs unearned (grayscale)
- [ ] Recent Pixels: last 20 purchases
- [ ] Score History: last 10 games
- [ ] PXL Transaction History: last 30 transactions

### 10.2 API Endpoints

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| /api/user/profile.php | GET | Public | Public profile by username |
| /api/user/me.php | GET | Required | Full profile with balance, streak |

---

## Phase 11: Cron Jobs

### 11.1 Scheduled Tasks

| Script | Schedule | Purpose |
|--------|----------|---------|
| cron/reset_grid.php | Sunday 00:00 UTC | Canvas reset every 7 days |
| cron/cleanup_sessions.php | Daily 03:00 UTC | Remove old game sessions |
| cron/cleanup_login_attempts.php | Daily 04:00 UTC | Purge old login attempts |

### 11.2 Grid Reset Algorithm
1. Get current grid_session_id
2. Take snapshot: render all pixels to PNG
3. Update grid_sessions: is_current=0, ended_at=NOW(), record stats
4. INSERT new grid_sessions (is_current=1)
5. DELETE FROM pixels (truncate current state)
6. FLUSH all chunk caches in Redis
7. Reset chunk versions to 0
8. Broadcast SSE event: {"type":"grid_reset"}

---

## Phase 12: Security Hardening

### 12.1 Required Implementation
- [ ] CSP header (Section 9.1)
- [ ] X-Frame-Options: DENY
- [ ] X-Content-Type-Options: nosniff
- [ ] Referrer-Policy: strict-origin-when-cross-origin
- [ ] HSTS: max-age=31536000

### 12.2 Input Validation
- [ ] ALL user input validated via validate.php functions
- [ ] Color regex: ^#[0-9A-Fa-f]{6}$
- [ ] Coordinates: 0-2047 for pixels, 0-31 for chunks

### 12.3 Rate Limiting (Redis)
| Action | Key Pattern | Max | Window |
|--------|-------------|-----|--------|
| Login attempts | login_fail:{ip} | 5 | 900s |
| Registration | register:{ip} | 3 | 3600s |
| Pixel purchase | buy:{user_id} | 10 | 60s |
| Game start | game_start:{user_id} | 20 | 3600s |
| Chunk request | chunk:{ip} | 200 | 60s |
| API general | api:{ip} | 300 | 60s |
| SSE connect | sse:{ip} | 2 | concurrent |

### 12.4 SQL Injection Prevention
- [ ] ALL queries use PDO prepared statements
- [ ] PDO::ATTR_EMULATE_PREPARES = false

### 12.5 XSS Prevention
- [ ] PHP output uses h() / htmlspecialchars()
- [ ] JavaScript uses textContent, not innerHTML

---

## Phase 13: Responsive & Mobile

### 13.1 Breakpoints
- [ ] Desktop: ≥ 1024px (full layout)
- [ ] Tablet: 768px - 1023px (collapsed sidebar)
- [ ] Mobile: < 768px (hamburger menu, bottom toolbar)

### 13.2 Game Mobile Controls
- [ ] Tap left half: Jump/Double Jump
- [ ] Tap right half: Slide/Duck
- [ ] Pause button in HUD

### 13.3 Canvas Mobile
- [ ] Full screen canvas
- [ ] Toolbar becomes bottom drawer
- [ ] Two-finger pinch zoom, one-finger drag pan

---

## Implementation Order Summary

```
PHASE 1: Infrastructure
├── 1.1 Environment & Configuration
├── 1.2 Database Setup (17 tables)
├── 1.3 Redis Setup
└── 1.4 Nginx Configuration

PHASE 2: Core Backend
├── 2.1 Includes/Helper Files (12 files)
└── 2.2 Bootstrap Integration

PHASE 3: Authentication
├── 3.1 API Endpoints (7 endpoints)
├── 3.2 Registration Flow
├── 3.3 Login Flow
└── 3.4 Session Security

PHASE 4: Frontend Shell
├── 4.1 Public Pages (6 pages)
├── 4.2 CSS Architecture
├── 4.3 Sidebar Navigation
└── 4.4 JavaScript Architecture

PHASE 5: Canvas Grid
├── 5.1 Grid Architecture
├── 5.2 Backend API (4 endpoints)
├── 5.3 Chunk Loading
├── 5.4 Frontend Canvas
├── 5.5 Pan & Zoom
├── 5.6 Color Palette
├── 5.7 Pixel Purchase
└── 5.8 SSE Updates

PHASE 6: PIXEL DASH
├── 6.1 Game Architecture (8 files)
├── 6.2 Game Mechanics
├── 6.3 Game Audio
├── 6.4 Game API (3 endpoints)
├── 6.5 Anti-Cheat
└── 6.6 Game Session Flow

PHASE 7: PXL Economy
├── 7.1 Earning PXL
├── 7.2 Spending PXL
├── 7.3 Transaction Logging
└── 7.4 Daily Login Streak

PHASE 8: Achievements
├── 8.1 Achievement Definitions (20)
├── 8.2 Backend Logic
└── 8.3 API Endpoints

PHASE 9: Leaderboard
├── 9.1 Database Queries
├── 9.2 API Endpoint
└── 9.3 Frontend

PHASE 10: Profile Page

PHASE 11: Cron Jobs (3 scripts)

PHASE 12: Security Hardening
├── 12.1 Headers
├── 12.2 Input Validation
├── 12.3 Rate Limiting
├── 12.4 SQL Injection Prevention
└── 12.5 XSS Prevention

PHASE 13: Responsive & Mobile

TOTAL: ~150+ individual tasks
```

---

## Critical Reminders

1. **Every PHP output to HTML must use h() / htmlspecialchars()**
2. **Every SQL query must use PDO prepared statements**
3. **CSRF token must be verified on every state-changing POST**
4. **Rate limits must be checked before any business logic**
5. **Game score validation is mandatory — never trust client**
6. **Redis distributed lock is mandatory for pixel purchase**
7. **MySQL transaction wraps entire pixel purchase operation**
8. **Session must be regenerated on login**
9. **Passwords hashed with bcrypt cost 12**
10. **SSE endpoint: disable nginx buffering, long timeout**
11. **Chunk binary: exactly 12,288 bytes per chunk**
12. **Email verification required for pixel purchase**
13. **.env and includes/ must never be web-accessible**

---

## File & Folder Structure

```
/var/www/pixelforge/
├── public/                          ← Nginx serves this ONLY
│   ├── index.php                    ← Landing page
│   ├── game.php                     ← PIXEL DASH
│   ├── canvas.php                   ← The Forge
│   ├── profile.php                  ← User profile
│   ├── leaderboard.php              ← Leaderboard
│   ├── verify.php                   ← Email verification
│   ├── assets/
│   │   ├── css/
│   │   │   ├── main.css
│   │   │   ├── game.css
│   │   │   └── canvas.css
│   │   ├── js/
│   │   │   ├── api.js
│   │   │   ├── auth.js
│   │   │   ├── ui.js
│   │   │   ├── utils.js
│   │   │   ├── game/
│   │   │   │   ├── engine.js
│   │   │   │   ├── renderer.js
│   │   │   │   ├── prng.js
│   │   │   │   ├── obstacles.js
│   │   │   │   ├── collectibles.js
│   │   │   │   ├── audio.js
│   │   │   │   ├── hud.js
│   │   │   │   └── game-main.js
│   │   │   └── canvas/
│   │   │       ├── grid-renderer.js
│   │   │       ├── chunk-cache.js
│   │   │       ├── sse-client.js
│   │   │       ├── pixel-buyer.js
│   │   │       ├── mini-map.js
│   │   │       └── canvas-main.js
│   │   ├── fonts/
│   │   ├── sounds/
│   │   └── sprites/
│   └── api/
│       ├── auth/
│       ├── game/
│       ├── grid/
│       ├── user/
│       └── leaderboard.php
├── includes/                        ← NOT web accessible
│   ├── bootstrap.php
│   ├── config.php
│   ├── db.php
│   ├── redis.php
│   ├── session.php
│   ├── security.php
│   ├── response.php
│   ├── validate.php
│   ├── rate_limit.php
│   ├── auth.php
│   ├── logger.php
│   ├── game_validator.php
│   ├── pxl.php
│   └── achievement.php
├── cron/
│   ├── reset_grid.php
│   ├── cleanup_sessions.php
│   └── cleanup_login_attempts.php
├── admin/
├── logs/
└── .env
```

---

## Verification Checklist

After each phase, verify:
- [ ] All files created in correct locations
- [ ] No syntax errors in PHP
- [ ] No console errors in JavaScript
- [ ] API endpoints return correct JSON
- [ ] Rate limiting works
- [ ] Security headers present
- [ ] Database queries use prepared statements
- [ ] CSRF protection working

---

*Plan created based on core.md (3165 lines, v1.0)*
*Total estimated tasks: ~150+*