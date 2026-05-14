# PixelForge — Full System Build Prompt

## A Communal Pixel Canvas + Arcade Game Platform

---

> **AGENT INSTRUCTIONS**: This is a complete, end-to-end specification. Follow it exactly. Do NOT skip sections, do NOT simplify described systems, do NOT substitute technologies. Every section must be implemented as described. Security is non-negotiable. Read the entire document before writing a single line of code.

---

## TABLE OF CONTENTS

1. [Project Overview](#1-project-overview)
2. [Technology Stack](#2-technology-stack)
3. [Game Design Document — PIXEL DASH](#3-game-design-document--pixel-dash)
4. [PXL Currency & Economy Design](#4-pxl-currency--economy-design)
5. [Grid Architecture & Rendering](#5-grid-architecture--rendering)
6. [Database Schema (Full)](#6-database-schema-full)
7. [API Specification](#7-api-specification)
8. [Real-Time Update System (SSE)](#8-real-time-update-system-sse)
9. [Security Architecture](#9-security-architecture)
10. [Anti-Cheat System](#10-anti-cheat-system)
11. [Conflict Resolution & Concurrency](#11-conflict-resolution--concurrency)
12. [File & Folder Structure](#12-file--folder-structure)
13. [Frontend Architecture](#13-frontend-architecture)
14. [UI/UX Design Specification](#14-uiux-design-specification)
15. [PHP Backend Architecture](#15-php-backend-architecture)
16. [Redis Architecture](#16-redis-architecture)
17. [Cron Jobs & Scheduled Tasks](#17-cron-jobs--scheduled-tasks)
18. [Configuration & Environment](#18-configuration--environment)
19. [Complete Implementation Details](#19-complete-implementation-details)

---

## 1. PROJECT OVERVIEW

**PixelForge** is a browser-based platform combining two experiences:

1. **PIXEL DASH** — A fast-paced, skill-based arcade game where players earn **PXL** (the in-platform currency).
2. **The Forge** — A massive 2048×2048 communal pixel canvas where players spend PXL to paint individual pixels, collaboratively creating art. The canvas resets every 7 days, starting fresh for the next cycle.

### Core Loop

```
Play PIXEL DASH → Earn PXL → Spend PXL on The Forge → Collaborate to create art → Canvas resets in 7 days → Repeat
```

### Design Philosophy

- Fair competition: PXL earned only through gameplay, no pay-to-win mechanics.
- Creative freedom: Users can paint any non-violating content.
- Community: The canvas is a shared creative space visible to all, even logged-out visitors.
- Security first: Every single user action is validated, rate-limited, and authenticated.

---

## 2. TECHNOLOGY STACK

| Layer                     | Technology                                                     |
| ------------------------- | -------------------------------------------------------------- |
| Frontend                  | Pure HTML5, CSS3 (no preprocessors), Vanilla JavaScript (ES6+) |
| Canvas Rendering          | HTML5 Canvas API (2D context + OffscreenCanvas)                |
| Backend                   | PHP 8.2+ (no frameworks)                                       |
| Web Server                | Nginx (PHP-FPM)                                                |
| Primary Database          | MySQL 8.0+ (InnoDB)                                            |
| Cache / Sessions / PubSub | Redis 7.0+                                                     |
| Real-time Updates         | Server-Sent Events (SSE) via PHP                               |
| Password Hashing          | PHP `password_hash()` with `PASSWORD_BCRYPT`, cost 12          |
| Session Management        | PHP Sessions (custom handler backed by Redis)                  |
| CSRF                      | Synchronizer Token Pattern                                     |
| Task Scheduling           | Linux Cron                                                     |

**Absolutely NO frameworks**: no Laravel, no Symfony, no React, no Vue, no jQuery. Pure, clean, raw code only.

---

## 3. GAME DESIGN DOCUMENT — PIXEL DASH

### 3.1 Concept

**PIXEL DASH** is a side-scrolling endless runner set inside a corrupted digital mainframe. You control **PXLR** (pronounced "Pixeler"), a lone color fragment racing through a glitch-ridden data stream, collecting Color Shards to restore the dying canvas before the system resets.

The tone is neon-meets-retro-pixel-art. The gameplay is simple to learn, nearly impossible to master — inspired by Chrome Dino but with richer collectible mechanics, combo systems, and meaningful power-up strategy.

### 3.2 Controls

| Action      | Keyboard                                     | Mobile                  |
| ----------- | -------------------------------------------- | ----------------------- |
| Jump        | `Space` or `ArrowUp` or `W`                  | Tap left half of screen |
| Double Jump | `Space`/`ArrowUp`/`W` again (while airborne) | Tap left half again     |
| Slide/Duck  | `ArrowDown` or `S`                           | Tap right half          |
| Pause       | `Escape` or `P`                              | Pause button            |

### 3.3 Character — PXLR

- A small, chunky 16×16 pixel-art character
- Rendered as an animated sprite sheet (4 frames run cycle, 2 frames jump, 2 frames slide, 1 frame idle, 2 frames death)
- Body is a bright cyan (#00F5FF) pixel block with white "eye" pixel
- When powered-up: glows with the color of the active power-up (shield = blue, magnet = yellow, slow = purple, surge = orange)
- Has a small particle trail while running

### 3.4 World Generation

**The world is procedurally generated in real time using a seeded pseudo-random number generator (PRNG) on the client, with the seed transmitted to the server at game start for validation.**

- Side-scrolling, the world moves left at increasing speed
- Background: 3-layer parallax
  - Layer 1 (slowest, 20% speed): Dark grid lines on deep black (#0A0A1A), faint cyan glow
  - Layer 2 (40% speed): Floating binary data columns, semi-transparent
  - Layer 3 (80% speed): Glitching pixel blocks in the far background
- Ground: A neon-trimmed data floor (#111122 fill, #00F5FF 2px top border)
- Sky ceiling: Invisible wall but visually indicated by subtle glow at top

**Terrain Profile:**

- Flat ground with raised platforms (for aerial collectible clusters)
- No procedural terrain height changes on the ground — only obstacles vary

### 3.5 Obstacle System

All obstacles have a minimum gap of 600ms (at starting speed) between instances so they are always humanly avoidable.

#### Ground Obstacles

| Obstacle          | Visual                        | Required Action             | Spawn Weight |
| ----------------- | ----------------------------- | --------------------------- | ------------ |
| **Glitch Block**  | 1×1 unit magenta cube         | Jump over                   | 40%          |
| **Double Stack**  | 2×1 unit tower                | Jump over (higher arc)      | 20%          |
| **Spike Array**   | 3 thin spikes across          | Jump over (any height)      | 15%          |
| **Crawl Barrier** | 4×0.5 unit low wall           | Slide under                 | 15%          |
| **Triple Stack**  | 3×1 unit tall tower           | Jump + reach max arc        | 5%           |
| **Combo Block**   | 1×1 unit + adjacent 0.5 crawl | Jump then immediately slide | 5%           |

#### Aerial Obstacles

| Obstacle          | Visual                           | Required Action                              | Spawn Weight |
| ----------------- | -------------------------------- | -------------------------------------------- | ------------ |
| **Firewall Beam** | Horizontal laser at chest height | Slide under                                  | 35%          |
| **High Beam**     | Horizontal laser at high height  | Duck slightly but can run through with slide | 25%          |
| **Data Spike**    | Falling spike from ceiling       | Move forward, don't jump                     | 20%          |
| **Double Beam**   | Two beams with gap               | Time jump through gap                        | 20%          |

#### Special Obstacles (unlock at speed tier 3+)

| Obstacle          | Visual                                                | Required Action                                  |
| ----------------- | ----------------------------------------------------- | ------------------------------------------------ |
| **Glitch Zone**   | Screen static effect for 1.5 seconds, obstacles flash | React to rapid visual changes                    |
| **Quantum Block** | Blinks every 0.3s (solid only when visible)           | Timing-based jump                                |
| **Data Storm**    | Moving projectile from right                          | Horizontal dodge (slide or jump based on height) |

**Obstacle Generation Rules:**

- Never spawn two successive aerial obstacles without a ground obstacle in between
- Never spawn an obstacle within 1.5 seconds of another at initial speed
- Never spawn Triple Stack + Combo Block consecutively
- Minimum 800ms of clear ground after any Special Obstacle
- An obstacle cluster (2 obstacles close together) can only appear after speed tier 2

### 3.6 Collectible System

Collectibles spawn on the ground, floating above ground, or in aerial clusters on platforms.

#### Color Shards (Primary Currency Collectible)

These are the main source of PXL conversion. Each Color Shard is a small (8×8px) glowing geometric crystal.

| Shard Type        | Color        | Value     | Frequency         | Notes                      |
| ----------------- | ------------ | --------- | ----------------- | -------------------------- |
| **Gray Shard**    | #888888      | +1 Score  | Very Common (50%) | Appear in long chains      |
| **Red Shard**     | #FF3366      | +5 Score  | Common (25%)      | Single or pairs            |
| **Blue Shard**    | #3366FF      | +5 Score  | Common (15%)      | Often near obstacles       |
| **Green Shard**   | +10 Score    | #33FF66   | Uncommon (7%)     | Floating on platforms      |
| **Rainbow Prism** | Animated RGB | +50 Score | Rare (3%)         | Single spawn, pulsing glow |

**Chain Spawning:** Gray Shards spawn in chains of 3–8. Other shards spawn alone or in pairs.

#### Power Cells

A small hexagonal cell (12×12px, pulsing white glow). Collecting it activates a random power-up from your available pool. Power cells are rare — one approximately every 25–40 seconds.

### 3.7 Power-Up System

When a Power Cell is collected, a power-up is drawn from a weighted random pool:

| Power-Up        | Probability | Duration   | Effect                                                                                                |
| --------------- | ----------- | ---------- | ----------------------------------------------------------------------------------------------------- |
| **SHIELD**      | 30%         | 8 seconds  | One hit absorption. PXLR glows blue. Obstacle causes shield to break (visual flash) instead of death. |
| **MAGNET**      | 25%         | 12 seconds | Auto-collects all shards within 120px. PXLR glows yellow.                                             |
| **TIMEWARP**    | 20%         | 6 seconds  | Game speed reduced by 40%. PXLR glows purple. Music slows. Timer still runs normally.                 |
| **SCORE SURGE** | 15%         | 15 seconds | All shard values ×3. PXLR glows orange. Score numbers shown in orange.                                |
| **EXTRA LIFE**  | 7%          | Instant    | Restores 1 life (max 3). Green "1UP" animation.                                                       |
| **PIXEL BOMB**  | 3%          | Instant    | All current on-screen obstacles explode into Gray Shards worth +1 each. Flash effect.                 |

**Power-up stacking rules:**

- Only one power-up active at a time (new one overrides, except EXTRA LIFE and PIXEL BOMB which are instant)
- Duration bar shown above PXLR
- If TIMEWARP + SCORE SURGE would both be available, the later one queues for 3 seconds after current ends

### 3.8 Combo System

The combo system rewards consistent shard collection without missing.

- A **combo counter** tracks consecutive shards collected without missing one
- Shards are "missed" if they enter the left side of the screen while PXLR is too far from their Y position
- Combo breaks are forgiven if the player was in the process of dodging an obstacle (grace period: 400ms after any obstacle clearance)

| Combo Milestone | Score Multiplier | Visual Effect                                          |
| --------------- | ---------------- | ------------------------------------------------------ |
| 0–4 shards      | 1×               | None                                                   |
| 5–9 shards      | 1.5×             | Combo counter turns yellow                             |
| 10–19 shards    | 2×               | Combo counter turns orange + pulse                     |
| 20–34 shards    | 3×               | Combo counter turns red + shake                        |
| 35+ shards      | 4× (MAX)         | Counter glows white + constant pulse, "MAX COMBO" text |

**Combo Bonus PXL:** Reaching each multiplier tier for the first time in a session awards a small bonus:

- Reach 1.5× → +1 PXL flat bonus (not counted in score conversion)
- Reach 2× → +2 PXL flat bonus
- Reach 3× → +5 PXL flat bonus
- Reach MAX → +10 PXL flat bonus

### 3.9 Lives System

- PXLR starts with **3 lives** per game session
- Hitting an obstacle costs 1 life
- **Mercy Invincibility:** After losing a life, PXLR is invincible for 2.5 seconds (blinking animation)
- Losing all 3 lives ends the game
- Lives shown as 3 pixel heart icons in HUD

### 3.10 Speed Progression

Speed tiers are the core difficulty curve. Speed is measured in "blocks per second" (BPS).

| Tier | BPS        | Score Threshold | New Feature Introduced                           |
| ---- | ---------- | --------------- | ------------------------------------------------ |
| 1    | 5.0        | 0               | Basic obstacles only                             |
| 2    | 6.5        | 300             | Aerial obstacles introduced                      |
| 3    | 8.0        | 800             | Special obstacles, tighter gaps                  |
| 4    | 10.0       | 1800            | Combo blocks, obstacle pairs                     |
| 5    | 12.0       | 3500            | Obstacle triplets possible                       |
| 6    | 14.0       | 6000            | Glitch Zone introduced                           |
| 7    | 15.5       | 10000           | Maximum chaos: all obstacle types, fast spawning |
| 8+   | 15.5 (cap) | —               | No new mechanics, highest challenge              |

Speed increases linearly within each tier. Between tiers, a brief (0.5s) "SPEED UP" flash effect plays.

### 3.11 Game Session Flow

```
[User clicks PLAY] → [Server issues signed game session token]
       ↓
[Client stores token + seed + timestamp]
       ↓
[Game starts: PRNG seeded, obstacles generate]
       ↓
[Every 30 seconds: client sends checkpoint to server]
  - checkpoint payload: {session_token, score_at_time, lives_remaining, speed_tier, hmac}
  - Server validates plausibility and stores checkpoint
       ↓
[Game ends (all lives lost or user quits)]
       ↓
[Client sends final score payload: {session_token, final_score, duration, checkpoints_hash, hmac}]
       ↓
[Server validates entire session: plausibility, HMAC, checkpoint chain]
       ↓
[Server calculates PXL earned, credits user account, stores score]
       ↓
[Client receives: {pxl_earned, new_balance, rank, achievements_unlocked}]
```

### 3.12 Audio Design

- Background music: 8-bit looping chiptune track (3 tracks, switches at speed tier 3 and 6)
- Sound effects: Jump (short blip), shard collect (short chime), obstacle hit (buzz), power-up (ascending arpeggio), death (descending arpeggio), level up (fanfare)
- All audio: Web Audio API only. Sound files are small .ogg files.
- Mute toggle button always visible in HUD

### 3.13 HUD Layout

```
┌────────────────────────────────────────────────────────────────┐
│  ❤️❤️❤️   SCORE: 1,247   COMBO: x2   PXL: 23   [🔇] [⏸]  │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│              [GAME CANVAS - FULL WIDTH]                        │
│                                                                │
│  [POWER-UP BAR ABOVE PXLR if active]                           │
└────────────────────────────────────────────────────────────────┘
```

### 3.14 Game Over Screen

Shows:

- Final score with animation
- PXL earned (calculated server-side, shown after API response)
- Personal best (if beaten, celebration animation)
- Daily rank position
- Achievements unlocked this session (if any)
- "PLAY AGAIN" button and "GO TO FORGE" button

---

## 4. PXL CURRENCY & ECONOMY DESIGN

### 4.1 Earning PXL

#### From Games (Primary Source)

- Base conversion: **200 game score points = 1 PXL** (server-calculated)
- **Daily First Game Bonus:** First submitted score each day = 2× PXL conversion for that game
- **Daily High Score Bonus:** If a user beats their daily best: +5 PXL bonus
- **Combo Tier Bonuses** (per session, first time reached): see Section 3.8

#### From Achievements (One-Time)

All achievements must be claimed via the UI (displayed as notifications).

| Achievement Key    | Title           | Description              | PXL Reward |
| ------------------ | --------------- | ------------------------ | ---------- |
| `first_game`       | First Blood     | Complete your first game | 5          |
| `speed_tier_3`     | Getting Fast    | Reach Speed Tier 3       | 8          |
| `speed_tier_5`     | Blazing         | Reach Speed Tier 5       | 15         |
| `speed_tier_7`     | Unstoppable     | Reach Speed Tier 7       | 25         |
| `score_500`        | Decent Run      | Score 500 in one game    | 5          |
| `score_2000`       | Impressive      | Score 2,000 in one game  | 15         |
| `score_5000`       | Legend          | Score 5,000 in one game  | 30         |
| `score_10000`      | Mythic          | Score 10,000 in one game | 60         |
| `combo_15`         | Chain Reaction  | Reach 15× combo          | 10         |
| `combo_35`         | MAX COMBO       | Reach MAX COMBO          | 25         |
| `first_pixel`      | Painter's Debut | Place your first pixel   | 10         |
| `pixels_50`        | Contributor     | Place 50 pixels          | 20         |
| `pixels_250`       | Artist          | Place 250 pixels         | 40         |
| `pixels_1000`      | Master Painter  | Place 1,000 pixels       | 80         |
| `rainbow_5`        | Prism Hunter    | Collect 5 Rainbow Prisms | 15         |
| `bomb_used`        | Demolition      | Trigger a Pixel Bomb     | 8          |
| `total_earned_100` | Century         | Earn 100 PXL total       | 20         |
| `streak_3`         | Regular         | 3-day login streak       | 10         |
| `streak_7`         | Dedicated       | 7-day login streak       | 20         |
| `streak_30`        | Devotee         | 30-day login streak      | 60         |

#### Daily Login Streak Bonus

Awarded once per day upon first login. Streak resets if a day is missed.

| Streak Days    | PXL Bonus |
| -------------- | --------- |
| 1              | 2         |
| 2              | 3         |
| 3              | 5         |
| 5              | 8         |
| 7              | 15        |
| 14             | 25        |
| 30             | 50        |
| Every 30 after | 50        |

### 4.2 Spending PXL

- **1 pixel anywhere on the canvas = 1 PXL** (flat cost, no premium zones)
- Grid reset clears all placed pixels but PXL balances persist
- Users cannot transfer PXL to other users
- No purchases with real money (pure merit-based)

### 4.3 Economy Balance Notes (for agent)

The economy is designed so that:

- A casual player (1–2 games/day, ~500 avg score) earns ~2–5 PXL/day → ~14–35 PXL/week → ~14–35 pixels per reset cycle
- A skilled player (5+ games/day, ~3000 avg score) earns ~25–50 PXL/day → ~175–350 PXL/week → significant canvas influence
- This creates a healthy spectrum of participation

---

## 5. GRID ARCHITECTURE & RENDERING

### 5.1 The Grid

- **Size:** 2048 × 2048 pixels
- **Total Pixels:** 4,194,304
- **Reset Cycle:** Every 7 days (cron-triggered)
- **Default Color:** White (#FFFFFF) — all pixels start white on reset
- **Pixel Cost:** 1 PXL per pixel

### 5.2 Chunk System

The grid is divided into **chunks** to enable efficient loading, caching, and real-time updates.

- **Chunk Size:** 64 × 64 pixels
- **Total Chunks:** 32 × 32 = 1,024 chunks
- **Chunk ID:** `cx_cy` where cx = Math.floor(x/64), cy = Math.floor(y/64)
- **Each chunk:** Stored in Redis as a packed binary string (64×64 pixels × 3 bytes per pixel = 12,288 bytes per chunk = ~12KB per chunk, ~12MB total for all chunks in Redis)

**Chunk data format in Redis:**

- Key: `chunk:{cx}:{cy}`
- Value: Flat binary array, 12,288 bytes. Pixel at (lx, ly) within chunk is at offset `(ly * 64 + lx) * 3` — 3 bytes for R, G, B.
- Version key: `chunk_v:{cx}:{cy}` → integer version counter (incremented on any write)

### 5.3 Canvas Rendering Architecture

The grid viewer is a full-page canvas application.

#### Canvases Used

1. **`gridCanvas`** (main display canvas): Full visible area, rendered from chunk data
2. **`overlayCanvas`** (same size, positioned on top, transparent): Handles cursor highlight, selection box, hover effects

#### Zoom Levels

| Zoom | Pixels per Screen Pixel | Canvas Display Size      | View Area  |
| ---- | ----------------------- | ------------------------ | ---------- |
| 1×   | 1                       | 2048 × 2048 (scrollable) | Full grid  |
| 2×   | 0.5                     | 1024 × 1024 per view     | 1/4 grid   |
| 4×   | 0.25                    | 512 × 512 per view       | 1/16 grid  |
| 8×   | 0.125                   | 256 × 256 per view       | 1/64 grid  |
| 16×  | 0.0625                  | 128 × 128 per view       | 1/256 grid |

Minimum zoom for pixel purchasing is **4×** (pixels are too small at lower zoom to click accurately).

#### Rendering Pipeline

```
[User viewport change]
       ↓
[Calculate visible chunks (viewport + 1 chunk buffer)]
       ↓
[For each visible chunk: check if cached in client chunkCache Map]
  → [Cache hit: render chunk immediately from ImageData]
  → [Cache miss: request chunk from API, render placeholder (white with grid lines)]
       ↓
[API returns chunk binary data → decode to ImageData → cache → render]
       ↓
[Overlay canvas: draw grid lines at zoom ≥ 4×, cursor highlight, hover info]
```

#### Client-Side Chunk Cache

```javascript
const chunkCache = new Map(); // key: "cx_cy", value: { imageData: ImageData, version: number, lastFetched: timestamp }
const CHUNK_CACHE_TTL = 30000; // 30 seconds before refresh check
const MAX_CACHED_CHUNKS = 200; // LRU eviction after this count
```

**LRU Eviction:** When cache exceeds 200 chunks, evict the least recently accessed entries.

#### Rendering Grid Lines

At zoom ≥ 4×, draw 1px grid lines every pixel in `rgba(200,200,200,0.3)`.
At zoom ≥ 8×, draw 2px chunk boundary lines in `rgba(100,100,255,0.4)`.
Never draw grid lines at zoom < 4× (performance).

#### Virtual Scrolling

The canvas does not attempt to render all 2048×2048 pixels. Instead:

- Maintain `viewX`, `viewY` (top-left corner of current view in grid coordinates)
- Maintain `zoom` level
- Compute visible pixel range: `[viewX, viewY]` to `[viewX + viewport_w/zoom, viewY + viewport_h/zoom]`
- Compute visible chunk range from visible pixel range
- Render only those chunks

### 5.4 Pixel Purchase Flow (Client)

```
1. User selects a color from palette
2. User clicks/taps a pixel at zoom ≥ 4×
3. Client calculates grid coordinates (x, y) from canvas click position
4. Client shows purchase confirmation tooltip: "Paint (x, y) with [color]? Cost: 1 PXL. Balance after: N PXL"
5. User confirms
6. Client sends POST /api/grid/buy.php with {x, y, color, csrf_token}
7. Client optimistically updates the pixel in its local cache (mark as "pending" with slightly different shade)
8. Server processes purchase (see conflict resolution in Section 11)
9. Server responds:
   - Success: {ok: true, new_balance: N} → Client confirms pixel, removes "pending" marker, SSE broadcast occurs
   - Conflict: {ok: false, error: "pixel_taken", current_color: "#RRGGBB"} → Client reverts, shows toast "That pixel was just bought!"
   - Insufficient funds: {ok: false, error: "insufficient_pxl"} → Client reverts, shows toast
   - Rate limited: {ok: false, error: "rate_limited", retry_after: N} → Client shows countdown
```

### 5.5 Canvas Pan & Zoom Controls

- **Pan:** Click and drag on canvas (when not in "purchase mode")
- **Zoom In/Out:** Mouse wheel, or +/- buttons in toolbar
- **Touch:** Two-finger pinch to zoom, one-finger drag to pan
- **Mini-Map:** A 200×200px thumbnail in bottom-right corner showing the full 2048×2048 grid, with a rectangle indicating current viewport. Click/drag mini-map to navigate.
- **Coordinate display:** Shows current mouse-over pixel coordinates `(x, y)` in a fixed top bar
- **Go to coordinates:** Input field to jump to specific x, y coordinates

### 5.6 Color Palette

Default palette: 32 colors presented in 4 rows of 8.

```
Row 1 (Neutral): #000000, #111111, #333333, #555555, #777777, #999999, #BBBBBB, #FFFFFF
Row 2 (Primary/Secondary): #FF0000, #FF6600, #FFCC00, #00CC00, #0066FF, #6600CC, #FF00FF, #00FFCC
Row 3 (Pastels): #FFB3B3, #FFD9B3, #FFFFB3, #B3FFB3, #B3D9FF, #D9B3FF, #FFB3FF, #B3FFFF
Row 4 (Dark Variants): #660000, #663300, #666600, #006600, #003366, #330066, #660066, #006666
```

User can also input a custom hex code (validated: must match `^#[0-9A-Fa-f]{6}$`).

---

## 6. DATABASE SCHEMA (FULL)

Create all tables in this exact order to respect foreign key dependencies.

```sql
-- ============================================================
-- DATABASE: pixelforge
-- ENGINE: InnoDB throughout
-- CHARSET: utf8mb4
-- COLLATION: utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS pixelforge
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE pixelforge;

-- ============================================================
-- GRID SESSIONS (must be created before pixels)
-- ============================================================
CREATE TABLE grid_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    snapshot_filename VARCHAR(255) DEFAULT NULL COMMENT 'PNG snapshot saved on reset',
    total_pixels_painted INT UNSIGNED DEFAULT 0,
    unique_painters INT UNSIGNED DEFAULT 0,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_current (is_current)
) ENGINE=InnoDB;

-- Insert the first grid session on setup
INSERT INTO grid_sessions (is_current) VALUES (1);

-- ============================================================
-- USERS
-- ============================================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    pxl_balance INT UNSIGNED NOT NULL DEFAULT 0,
    total_pxl_earned INT UNSIGNED NOT NULL DEFAULT 0,
    total_pxl_spent INT UNSIGNED NOT NULL DEFAULT 0,
    login_streak INT UNSIGNED NOT NULL DEFAULT 0,
    last_login_date DATE DEFAULT NULL,
    email_verified TINYINT(1) NOT NULL DEFAULT 0,
    email_verify_token VARCHAR(64) DEFAULT NULL,
    email_verify_expires TIMESTAMP NULL DEFAULT NULL,
    password_reset_token VARCHAR(64) DEFAULT NULL,
    password_reset_expires TIMESTAMP NULL DEFAULT NULL,
    is_banned TINYINT(1) NOT NULL DEFAULT 0,
    ban_reason VARCHAR(255) DEFAULT NULL,
    failed_login_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    lockout_until TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_username (username),
    UNIQUE KEY uq_email (email),
    INDEX idx_email_verify (email_verify_token),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- PXL TRANSACTIONS (ledger, append-only)
-- ============================================================
CREATE TABLE pxl_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    amount INT NOT NULL COMMENT 'Positive = credit, negative = debit',
    type ENUM(
        'game_earn',
        'pixel_spend',
        'achievement',
        'daily_bonus',
        'streak_bonus',
        'combo_bonus',
        'daily_highscore_bonus',
        'admin_credit',
        'admin_debit'
    ) NOT NULL,
    reference_id VARCHAR(64) DEFAULT NULL COMMENT 'game_session_id, achievement_key, etc.',
    balance_after INT UNSIGNED NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_pxl_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_created (user_id, created_at),
    INDEX idx_type (type)
) ENGINE=InnoDB;

-- ============================================================
-- PIXELS (Current State)
-- ============================================================
CREATE TABLE pixels (
    x SMALLINT UNSIGNED NOT NULL,
    y SMALLINT UNSIGNED NOT NULL,
    color CHAR(7) NOT NULL DEFAULT '#FFFFFF',
    owner_id INT UNSIGNED NOT NULL,
    grid_session_id INT UNSIGNED NOT NULL,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (x, y),
    FOREIGN KEY fk_pixel_owner (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_pixel_session (grid_session_id) REFERENCES grid_sessions(id),
    INDEX idx_owner (owner_id),
    INDEX idx_session (grid_session_id),
    INDEX idx_purchased (purchased_at)
) ENGINE=InnoDB;

-- ============================================================
-- CHUNKS (Cache Version Tracking)
-- ============================================================
CREATE TABLE chunks (
    chunk_x TINYINT UNSIGNED NOT NULL,
    chunk_y TINYINT UNSIGNED NOT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (chunk_x, chunk_y)
) ENGINE=InnoDB;

-- Pre-populate all 1024 chunks
INSERT INTO chunks (chunk_x, chunk_y)
SELECT a.n, b.n FROM
  (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30 UNION SELECT 31) a,
  (SELECT 0 n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9 UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14 UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19 UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24 UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29 UNION SELECT 30 UNION SELECT 31) b;

-- ============================================================
-- GAME SESSIONS
-- ============================================================
CREATE TABLE game_sessions (
    id VARCHAR(64) PRIMARY KEY COMMENT 'Secure random hex token',
    user_id INT UNSIGNED NOT NULL,
    prng_seed VARCHAR(64) NOT NULL COMMENT 'Seed issued by server for obstacle generation',
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_checkpoint_at TIMESTAMP NULL DEFAULT NULL,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    final_score INT UNSIGNED DEFAULT NULL,
    duration_seconds INT UNSIGNED DEFAULT NULL,
    pxl_earned INT UNSIGNED DEFAULT 0,
    lives_at_end TINYINT UNSIGNED DEFAULT NULL,
    max_speed_tier TINYINT UNSIGNED DEFAULT NULL,
    checkpoints_json JSON DEFAULT NULL COMMENT 'Array of checkpoint payloads',
    is_valid TINYINT(1) NOT NULL DEFAULT 1,
    invalidation_reason VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) NOT NULL,
    FOREIGN KEY fk_gs_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_started (started_at),
    INDEX idx_valid (is_valid)
) ENGINE=InnoDB;

-- ============================================================
-- SCORES (Leaderboard)
-- ============================================================
CREATE TABLE scores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    game_session_id VARCHAR(64) NOT NULL,
    score INT UNSIGNED NOT NULL,
    pxl_earned INT UNSIGNED NOT NULL,
    duration_seconds INT UNSIGNED NOT NULL,
    max_speed_tier TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_score_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_score_session (game_session_id) REFERENCES game_sessions(id),
    INDEX idx_score_desc (score DESC),
    INDEX idx_user_score (user_id, score DESC),
    INDEX idx_daily (created_at, score DESC)
) ENGINE=InnoDB;

-- ============================================================
-- PIXEL PURCHASE HISTORY (permanent record)
-- ============================================================
CREATE TABLE pixel_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    x SMALLINT UNSIGNED NOT NULL,
    y SMALLINT UNSIGNED NOT NULL,
    color CHAR(7) NOT NULL,
    pxl_cost INT UNSIGNED NOT NULL DEFAULT 1,
    grid_session_id INT UNSIGNED NOT NULL,
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY fk_ph_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_ph_session (grid_session_id) REFERENCES grid_sessions(id),
    INDEX idx_user (user_id),
    INDEX idx_coords (x, y),
    INDEX idx_session (grid_session_id),
    INDEX idx_time (purchased_at)
) ENGINE=InnoDB;

-- ============================================================
-- ACHIEVEMENTS DEFINITIONS
-- ============================================================
CREATE TABLE achievements (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(50) NOT NULL,
    title VARCHAR(100) NOT NULL,
    description VARCHAR(255) NOT NULL,
    pxl_reward INT UNSIGNED NOT NULL,
    icon_class VARCHAR(50) DEFAULT NULL,
    UNIQUE KEY uq_key (key_name)
) ENGINE=InnoDB;

-- Seed achievement definitions
INSERT INTO achievements (key_name, title, description, pxl_reward) VALUES
('first_game', 'First Blood', 'Complete your first game', 5),
('speed_tier_3', 'Getting Fast', 'Reach Speed Tier 3', 8),
('speed_tier_5', 'Blazing', 'Reach Speed Tier 5', 15),
('speed_tier_7', 'Unstoppable', 'Reach Speed Tier 7', 25),
('score_500', 'Decent Run', 'Score 500 in one game', 5),
('score_2000', 'Impressive', 'Score 2,000 in one game', 15),
('score_5000', 'Legend', 'Score 5,000 in one game', 30),
('score_10000', 'Mythic', 'Score 10,000 in one game', 60),
('combo_15', 'Chain Reaction', 'Reach a 15× combo', 10),
('combo_35', 'MAX COMBO', 'Reach MAX COMBO (35×)', 25),
('first_pixel', "Painter's Debut", 'Place your first pixel', 10),
('pixels_50', 'Contributor', 'Place 50 pixels on the canvas', 20),
('pixels_250', 'Artist', 'Place 250 pixels on the canvas', 40),
('pixels_1000', 'Master Painter', 'Place 1,000 pixels on the canvas', 80),
('rainbow_5', 'Prism Hunter', 'Collect 5 Rainbow Prisms in games', 15),
('bomb_used', 'Demolition', 'Trigger a Pixel Bomb power-up', 8),
('total_earned_100', 'Century', 'Earn 100 PXL total (lifetime)', 20),
('streak_3', 'Regular', 'Maintain a 3-day login streak', 10),
('streak_7', 'Dedicated', 'Maintain a 7-day login streak', 20),
('streak_30', 'Devotee', 'Maintain a 30-day login streak', 60);

-- ============================================================
-- USER ACHIEVEMENTS (Junction)
-- ============================================================
CREATE TABLE user_achievements (
    user_id INT UNSIGNED NOT NULL,
    achievement_id TINYINT UNSIGNED NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pxl_claimed TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, achievement_id),
    FOREIGN KEY fk_ua_user (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY fk_ua_ach (achievement_id) REFERENCES achievements(id)
) ENGINE=InnoDB;

-- ============================================================
-- LOGIN ATTEMPTS (Security)
-- ============================================================
CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username_attempted VARCHAR(100) DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at),
    INDEX idx_cleanup (attempted_at)
) ENGINE=InnoDB;

-- ============================================================
-- ADMIN USERS
-- ============================================================
CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_admin_username (username)
) ENGINE=InnoDB;
```

---

## 7. API SPECIFICATION

All API endpoints are under `/api/`. All responses are `Content-Type: application/json`. All POST requests require a valid CSRF token in the `X-CSRF-Token` header (except login and register which use a form-embedded token). All endpoints require valid session (authenticated) unless noted.

### Standard Response Envelope

**Success:**

```json
{"ok": true, "data": { ... }}
```

**Error:**

```json
{ "ok": false, "error": "error_code", "message": "Human readable message" }
```

### 7.1 Auth Endpoints

#### `POST /api/auth/register.php`

**Auth:** Public (no session required)
**Rate Limit:** 3 registrations per hour per IP

Request body:

```json
{
  "username": "string (3-20 chars, alphanumeric + underscore only)",
  "email": "string (valid email)",
  "password": "string (min 8 chars, max 128 chars)",
  "csrf_token": "string"
}
```

Server validation:

- Username: `^[a-zA-Z0-9_]{3,20}$`
- Email: `filter_var($email, FILTER_VALIDATE_EMAIL)`
- Password: minimum 8 characters, at least 1 letter and 1 number
- Username uniqueness check
- Email uniqueness check
- Send verification email with signed token (expires 24h)

Response:

```json
{
  "ok": true,
  "data": { "message": "Check your email to verify your account." }
}
```

#### `POST /api/auth/login.php`

**Auth:** Public
**Rate Limit:** 5 failed attempts per 15 minutes per IP → 15-minute lockout

Request body:

```json
{
  "username": "string",
  "password": "string",
  "csrf_token": "string"
}
```

Server logic:

1. Check IP rate limit (Redis: `login_fail:{ip}`)
2. Fetch user by username
3. Check if account is locked out (`lockout_until` column)
4. `password_verify($password, $user['password_hash'])`
5. If fail: increment `failed_login_count`, set `lockout_until` if ≥5, log to `login_attempts`
6. If success: `session_regenerate_id(true)`, set session vars, reset `failed_login_count`, process daily login streak, process daily bonus
7. Return user info

Response:

```json
{
  "ok": true,
  "data": {
    "user_id": 123,
    "username": "cooldude",
    "pxl_balance": 42,
    "login_streak": 3,
    "daily_bonus_earned": 5,
    "streak_bonus_earned": 0
  }
}
```

#### `POST /api/auth/logout.php`

Destroys session, clears cookie. No body needed.

#### `GET /api/auth/verify.php?token={token}`

Verifies email. Marks `email_verified=1`, invalidates token. Redirects to login with success message.

#### `POST /api/auth/forgot-password.php`

Accepts email, sends reset link if email exists (always responds success to prevent enumeration).

#### `POST /api/auth/reset-password.php`

Accepts `token`, `new_password`. Validates token not expired, updates hash, invalidates token.

#### `GET /api/auth/me.php`

Returns current user info. Returns `{"ok": false, "error": "unauthenticated"}` if not logged in.

### 7.2 Game Endpoints

#### `POST /api/game/start.php`

**Auth:** Required, email_verified=1
**Rate Limit:** 20 game starts per hour per user

Server logic:

1. Invalidate any existing active game session for this user
2. Generate cryptographically random 32-byte session ID: `bin2hex(random_bytes(32))`
3. Generate a 32-bit PRNG seed: `random_int(0, PHP_INT_MAX)`
4. Insert into `game_sessions`
5. Sign the session token with HMAC: `hash_hmac('sha256', $session_id . ':' . $seed . ':' . $user_id, SECRET_GAME_KEY)`
6. Cache active session in Redis: `game_active:{user_id}` = `session_id` (expire 2 hours)

Response:

```json
{
  "ok": true,
  "data": {
    "session_id": "hexstring64",
    "seed": 1234567890,
    "hmac": "hexstring64",
    "server_time": 1700000000000
  }
}
```

#### `POST /api/game/checkpoint.php`

**Auth:** Required
**Rate Limit:** 4 checkpoints per minute per user (max 1 per 25 seconds)

Request body:

```json
{
  "session_id": "string",
  "score": 1234,
  "lives": 2,
  "speed_tier": 3,
  "elapsed_ms": 35000,
  "hmac": "string"
}
```

Server validation:

1. Verify session belongs to user and is active
2. Verify HMAC: `hash_hmac('sha256', $session_id . ':' . $score . ':' . $elapsed_ms, SECRET_GAME_KEY)`
3. Plausibility check: `$score / ($elapsed_ms / 1000) <= MAX_SCORE_PER_SECOND` (see Section 10)
4. Store checkpoint in `game_sessions.checkpoints_json`

Response: `{"ok": true}`

#### `POST /api/game/submit.php`

**Auth:** Required
**Rate Limit:** 1 per active session

Request body:

```json
{
  "session_id": "string",
  "final_score": 2456,
  "duration_ms": 95000,
  "lives_remaining": 1,
  "max_speed_tier": 4,
  "max_combo": 18,
  "prisms_collected": 2,
  "bomb_used": false,
  "hmac": "string"
}
```

Server logic:

1. Verify session is active, belongs to user, not already submitted
2. Verify HMAC
3. Full plausibility validation (Section 10)
4. Calculate PXL earned: `floor($final_score / 200)`
5. Check daily first game bonus (2× if this is first submitted score today)
6. Check daily high score bonus (+5 PXL if beats today's best)
7. Credit PXL: UPDATE users SET pxl_balance = pxl_balance + N (with transaction + pxl_transactions INSERT)
8. Insert into `scores`
9. Check and award new achievements
10. Mark session as ended
11. Check combo achievements (combo_15, combo_35)
12. Check prism achievement (rainbow_5)
13. Check bomb achievement

Response:

```json
{
  "ok": true,
  "data": {
    "pxl_earned": 12,
    "daily_bonus": 12,
    "highscore_bonus": 5,
    "new_balance": 54,
    "personal_best": false,
    "daily_rank": 42,
    "achievements_unlocked": [
      { "key": "speed_tier_3", "title": "Getting Fast", "pxl": 8 }
    ]
  }
}
```

### 7.3 Grid Endpoints

#### `GET /api/grid/chunk.php?cx={0-31}&cy={0-31}&v={version}`

**Auth:** Not required (public canvas viewing)
**Rate Limit:** 200 requests per minute per IP

Returns chunk pixel data as binary (packed RGB) or JSON fallback.

Server logic:

1. Validate cx/cy range (0-31)
2. Check Redis cache: `chunk:{cx}:{cy}`
3. Cache miss: Query MySQL `SELECT x, y, color FROM pixels WHERE x BETWEEN ? AND ? AND y BETWEEN ? AND ?`
4. Build 12,288-byte binary: 3 bytes (R,G,B) per pixel, white (#FFFFFF = 255,255,255) for unowned pixels
5. Store in Redis with TTL 300s
6. If client sends `v=` parameter matching current version: respond 304 Not Modified
7. Otherwise: respond with binary data + version header

Response: Binary (`Content-Type: application/octet-stream`) with headers:

```
X-Chunk-Version: {version}
X-Chunk-X: {cx}
X-Chunk-Y: {cy}
Cache-Control: no-cache
```

Client decodes: For each pixel index `i` in 0..4095: `r=data[i*3], g=data[i*3+1], b=data[i*3+2]`.

#### `POST /api/grid/buy.php`

**Auth:** Required, email_verified=1
**Rate Limit:** 10 pixel purchases per minute per user

Request body:

```json
{
  "x": 500,
  "y": 300,
  "color": "#FF3366",
  "csrf_token": "string"
}
```

Server logic (see full detail in Section 11 Conflict Resolution):

1. Validate session, CSRF, rate limit
2. Validate x: integer 0–2047, y: integer 0–2047
3. Validate color: regex `^#[0-9A-Fa-f]{6}$`
4. Check user PXL balance ≥ 1
5. Acquire Redis distributed lock for `pixel_lock:{x}:{y}` (SETNX with 5s expiry)
6. If lock fails: return `{"ok": false, "error": "concurrent_conflict", "retry_after": 1}`
7. BEGIN MySQL transaction
8. SELECT pixel at (x,y) FOR UPDATE — check current state
9. Deduct 1 PXL from user (UPDATE users + INSERT pxl_transactions) within same transaction
10. INSERT INTO pixels (x, y, color, owner_id, grid_session_id) ... ON DUPLICATE KEY UPDATE color=..., owner_id=..., purchased_at=NOW()
11. INSERT INTO pixel_history ...
12. UPDATE chunks SET version=version+1 WHERE chunk_x=? AND chunk_y=?
13. COMMIT
14. Release Redis lock
15. Invalidate Redis chunk cache: DEL `chunk:{cx}:{cy}`
16. Increment Redis chunk version: INCR `chunk_v:{cx}:{cy}`
17. Publish SSE event: RPUSH `sse_queue` with JSON `{x, y, color, owner, username}`
18. Check pixel-related achievements (first_pixel, pixels_50, pixels_250, pixels_1000)

Response:

```json
{
  "ok": true,
  "data": {
    "x": 500,
    "y": 300,
    "color": "#FF3366",
    "new_balance": 41,
    "chunk_version": 15
  }
}
```

#### `GET /api/grid/pixel-info.php?x={x}&y={y}`

**Auth:** Not required
Returns owner info for a pixel (for hover tooltip).

Response:

```json
{
  "ok": true,
  "data": {
    "x": 500,
    "y": 300,
    "color": "#FF3366",
    "owner": "cooldude",
    "purchased_at": "2024-11-15T10:23:45Z",
    "is_owned": true
  }
}
```

### 7.4 User Endpoints

#### `GET /api/user/profile.php?username={username}`

Public profile. Returns: username, total_pxl_earned, pixels_painted (count from pixel_history), achievements list, join date, best score.

#### `GET /api/user/me.php`

Auth required. Returns full profile including balance, streak, recent transactions (last 20), recent purchases.

#### `GET /api/user/achievements.php`

Auth required. Returns all achievements with earned status and claim status.

#### `POST /api/user/claim-achievement.php`

Auth required. Body: `{achievement_key: "string"}`. Claims earned unclaimed achievement, credits PXL.

### 7.5 Leaderboard Endpoint

#### `GET /api/leaderboard.php?type={daily|alltime|weekly}&page={1}&limit={20}`

**Auth:** Not required
**Rate Limit:** 60 per minute per IP

Returns sorted score list with ranks. Cached in Redis for 60 seconds.

---

## 8. REAL-TIME UPDATE SYSTEM (SSE)

Server-Sent Events broadcast pixel purchases to all viewing clients in real time.

### 8.1 SSE Endpoint

`GET /api/grid/updates.php?chunks={cx1,cy1,cx2,cy2,...}`

**Auth:** Not required (read-only broadcast)
**Connection Limit:** Max 2 concurrent SSE connections per IP

```php
// PHP SSE implementation sketch (must be fully implemented)
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no'); // nginx: disable buffering
header('Connection: keep-alive');

set_time_limit(0); // SSE runs long
ignore_user_abort(false);

// Parse subscribed chunks from query string
// Loop: poll Redis list "sse_queue" using BLPOP with 5s timeout
// If event arrives: filter for chunks client is subscribed to
// Send: "data: {json}\n\n"
// If client disconnected (connection_aborted()): exit
// Heartbeat every 25 seconds: ": heartbeat\n\n"
```

### 8.2 SSE Event Format

```
data: {"type":"pixel","x":500,"y":300,"color":"#FF3366","username":"cooldude","cx":7,"cy":4,"chunk_version":15}

data: {"type":"heartbeat","server_time":1700000000000}
```

### 8.3 Client SSE Handling

```javascript
// Connect to SSE when entering canvas view
// Subscribe to visible chunks + 1 buffer chunk around viewport
// On "pixel" event: update client chunk cache ImageData at correct offset, re-render affected canvas region
// On connection drop: reconnect with exponential backoff (1s, 2s, 4s, 8s, max 30s)
// On viewport change: close existing SSE, reconnect with new chunk list
// Maximum chunk subscription per connection: 64 chunks
```

### 8.4 SSE Queue in Redis

- `pixel_buy.php` publishes: `RPUSH sse_queue {json_payload}` — list acts as queue
- `updates.php` consumes: `BLPOP sse_queue 5` — blocking pop with 5s timeout
- Fan-out: Since multiple SSE connections need the same event, do NOT use BLPOP destructively for fan-out. Instead:
  - Use Redis Pub/Sub: `PUBLISH sse_channel {json_payload}` in buyer
  - `updates.php` uses: `$redis->subscribe(['sse_channel'], $callback)`
  - Each SSE connection has its own subscriber

---

## 9. SECURITY ARCHITECTURE

**This is the most critical section. Every point must be implemented exactly.**

### 9.1 HTTP Security Headers

Set these on EVERY response, at the Nginx level AND in PHP:

```
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self'; font-src 'self'; media-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none';
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()
Strict-Transport-Security: max-age=31536000; includeSubDomains; preload
```

### 9.2 Session Security

```php
// In config.php / session initialization - called ONCE on every PHP request
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);      // HTTPS only
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_only_cookies', 1);
ini_set('session.sid_length', 48);
ini_set('session.sid_bits_per_character', 6);
ini_set('session.gc_maxlifetime', 86400); // 24 hours
session_name('PXLSESS');

// Custom session handler backed by Redis
// Keys: sess:{session_id} with TTL 24h
// On session_regenerate_id: delete old Redis key

// After login: ALWAYS call session_regenerate_id(true)
// After logout: session_unset(); session_destroy(); clear cookie
```

### 9.3 CSRF Protection

Implementation: **Synchronizer Token Pattern**

```php
// Generate on session start if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Embed in HTML forms:
// <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

// For AJAX: send as header: X-CSRF-Token
// Verification function:
function verify_csrf(string $token): bool {
    if (empty($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Always verify on every state-changing request (POST/PUT/DELETE)
// Exempt: GET requests, SSE endpoint (read-only)
```

### 9.4 SQL Injection Prevention

**ABSOLUTE RULE: Never concatenate user input into SQL. Use PDO prepared statements for every query.**

```php
// Good:
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
$stmt->execute([$username]);

// NEVER:
$result = $pdo->query("SELECT * FROM users WHERE username = '$username'");
```

All PDO connections use:

```php
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,   // Critical: real prepared statements
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::MYSQL_ATTR_FOUND_ROWS => true,
]);
```

### 9.5 XSS Prevention

**ALL output to HTML must be escaped. No exceptions.**

```php
// PHP output: always use htmlspecialchars
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

echo h($user['username']);
```

**JavaScript DOM manipulation: NEVER use innerHTML with user data.**

```javascript
// WRONG:
element.innerHTML = username;

// CORRECT:
element.textContent = username;

// For complex HTML with user data, build DOM nodes:
const span = document.createElement("span");
span.textContent = username;
element.appendChild(span);
```

**Content Security Policy** (Section 9.1) prevents inline scripts and external script injection.

### 9.6 Input Validation (All Endpoints)

Every input must be validated before use. Implement a shared `validate.php` helper:

```php
function validate_username(string $v): bool { return (bool)preg_match('/^[a-zA-Z0-9_]{3,20}$/', $v); }
function validate_email(string $v): bool { return (bool)filter_var($v, FILTER_VALIDATE_EMAIL) && strlen($v) <= 255; }
function validate_password(string $v): bool { return strlen($v) >= 8 && strlen($v) <= 128 && preg_match('/[a-zA-Z]/', $v) && preg_match('/[0-9]/', $v); }
function validate_color(string $v): bool { return (bool)preg_match('/^#[0-9A-Fa-f]{6}$/', $v); }
function validate_coord(mixed $v): bool { return is_numeric($v) && (int)$v >= 0 && (int)$v <= 2047; }
function validate_chunk_coord(mixed $v): bool { return is_numeric($v) && (int)$v >= 0 && (int)$v <= 31; }
function validate_positive_int(mixed $v): bool { return filter_var($v, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) !== false; }
```

### 9.7 Rate Limiting (Redis-based Sliding Window)

```php
// In rate_limit.php
function check_rate_limit(string $key, int $max_hits, int $window_seconds): bool {
    $redis = get_redis();
    $now = microtime(true);
    $window_start = $now - $window_seconds;

    $redis_key = "rl:{$key}";

    $redis->multi(); // MULTI
    $redis->zRemRangeByScore($redis_key, 0, $window_start);
    $redis->zAdd($redis_key, $now, $now . '_' . random_int(0, 999999));
    $redis->zCard($redis_key);
    $redis->expire($redis_key, $window_seconds + 1);
    $results = $redis->exec(); // EXEC

    $current_hits = $results[2];
    return $current_hits <= $max_hits;
}

// Usage example:
// if (!check_rate_limit("buy:{$user_id}", 10, 60)) { respond_error('rate_limited'); }
// if (!check_rate_limit("login_fail:{$ip}", 5, 900)) { respond_error('locked_out'); }
```

Rate limits table:

| Action         | Key Pattern              | Max                  | Window       |
| -------------- | ------------------------ | -------------------- | ------------ |
| Login attempts | `login_fail:{ip}`        | 5                    | 900s (15min) |
| Registration   | `register:{ip}`          | 3                    | 3600s (1hr)  |
| Pixel purchase | `buy:{user_id}`          | 10                   | 60s          |
| Game start     | `game_start:{user_id}`   | 20                   | 3600s        |
| Score submit   | `score_submit:{user_id}` | 1 per active session | —            |
| Chunk request  | `chunk:{ip}`             | 200                  | 60s          |
| API general    | `api:{ip}`               | 300                  | 60s          |
| SSE connect    | `sse:{ip}`               | 2 concurrent         | —            |
| Password reset | `pwreset:{ip}`           | 3                    | 3600s        |

### 9.8 Password Security

```php
// Hashing on registration:
$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

// Verification:
if (!password_verify($password, $user['password_hash'])) { /* fail */ }

// Always check if rehash needed:
if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
    $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    // Update in DB
}
```

### 9.9 Preventing Information Leakage

- All error responses use generic codes, no stack traces, no SQL errors shown to client
- Log all errors server-side to `/var/log/pixelforge/errors.log` with full detail
- Registration: always return same success message whether email exists or not (use delayed response to normalize timing)
- Password reset: always say "if this email exists, a reset link was sent"
- Never expose internal user IDs in URLs (use username instead)
- Database column names never appear in responses

### 9.10 File Security

- No user file uploads in this application
- `config.php` and all includes: placed outside web root or protected by Nginx
- `.php` files in `includes/` not directly accessible (Nginx: `deny all` on `/includes/`)
- No `eval()`, no `exec()`, no shell_exec() anywhere
- `open_basedir` PHP restriction set in php.ini

### 9.11 Clickjacking & Framing

- `X-Frame-Options: DENY` prevents all framing
- CSP `frame-ancestors 'none'` reinforces this

---

## 10. ANTI-CHEAT SYSTEM

All game scoring must be validated server-side. The game client should never be trusted.

### 10.1 Session Token System

1. Server issues `session_id` (random) + `seed` (deterministic obstacle sequence) + `hmac` signature
2. Client uses the server-issued seed for its PRNG — obstacle positions are deterministic
3. Server can theoretically replay the obstacle sequence to validate score plausibility

### 10.2 HMAC Signature

```php
define('SECRET_GAME_KEY', 'your-64-char-random-secret-here'); // From environment

// Client receives and stores session_id, seed, hmac
// For checkpoint/submit: client creates payload_hmac = HMAC-SHA256(session_id + ':' + score + ':' + elapsed_ms, SECRET_GAME_KEY)
// Server verifies: hash_equals(expected_hmac, provided_hmac)
```

The JavaScript side must implement the HMAC:

```javascript
async function computeHMAC(sessionId, score, elapsedMs) {
  const key = await crypto.subtle.importKey(
    "raw",
    new TextEncoder().encode(gameState.gameKey), // Server sends a per-session client key (NOT the server secret)
    { name: "HMAC", hash: "SHA-256" },
    false,
    ["sign"],
  );
  const msg = `${sessionId}:${score}:${elapsedMs}`;
  const sig = await crypto.subtle.sign(
    "HMAC",
    key,
    new TextEncoder().encode(msg),
  );
  return Array.from(new Uint8Array(sig))
    .map((b) => b.toString(16).padStart(2, "0"))
    .join("");
}
```

**Note:** Server sends a per-session signing key to the client (derived from the secret + session_id using HKDF). This prevents the client key from being reusable across sessions.

### 10.3 Score Plausibility Limits

These are the maximum theoretically achievable values based on game math:

```php
// Maximum score per second at each speed tier:
// At tier 1 (5 BPS): ~15 pts/sec (shards + distance)
// At tier 4 (10 BPS): ~30 pts/sec (more shards, faster movement)
// With MAX COMBO + Score Surge active: 4× × 3× = 12× multiplier at peak
// Absolute theoretical max: ~180 pts/sec (extremely short bursts)
// Sustained max over 60+ seconds: ~60 pts/sec

define('MAX_SCORE_PER_SECOND_HARD', 200); // Absolute never-exceed (even with all power-ups)
define('MAX_SCORE_PER_SECOND_SUSTAINED', 80); // For sessions > 30 seconds

function validate_score_plausibility(int $score, int $duration_ms, array $checkpoints): bool {
    $duration_sec = $duration_ms / 1000;
    if ($duration_sec < 1) return false;

    // Hard cap
    if ($score / $duration_sec > MAX_SCORE_PER_SECOND_HARD) return false;

    // Sustained cap for longer games
    if ($duration_sec > 30 && $score / $duration_sec > MAX_SCORE_PER_SECOND_SUSTAINED) return false;

    // Validate checkpoints are monotonically increasing
    $prev_score = 0;
    $prev_time = 0;
    foreach ($checkpoints as $cp) {
        if ($cp['score'] < $prev_score) return false; // Score can't decrease
        if ($cp['elapsed_ms'] <= $prev_time) return false; // Time must advance
        $delta_score = $cp['score'] - $prev_score;
        $delta_time = ($cp['elapsed_ms'] - $prev_time) / 1000;
        if ($delta_score / $delta_time > MAX_SCORE_PER_SECOND_HARD) return false;
        $prev_score = $cp['score'];
        $prev_time = $cp['elapsed_ms'];
    }

    // Final score must match last checkpoint direction
    if ($score < $prev_score) return false;

    return true;
}
```

### 10.4 One Active Session Per User

```php
// On game start:
$existing = $redis->get("game_active:{$user_id}");
if ($existing) {
    // Invalidate old session in DB
    $stmt = $pdo->prepare("UPDATE game_sessions SET is_valid=0, invalidation_reason='new_session_started' WHERE id=? AND user_id=?");
    $stmt->execute([$existing, $user_id]);
}
$redis->setex("game_active:{$user_id}", 7200, $session_id);
```

### 10.5 Session Expiry

- Game sessions expire after 2 hours (Redis key TTL)
- Score submissions rejected if session is > 2 hours old
- Score submissions rejected if session `ended_at` is already set

### 10.6 IP-Based Anomaly Logging

- Log to `game_sessions.ip_address`
- If same IP submits > 50 game sessions/day: flag for admin review
- Admin panel shows flagged sessions

---

## 11. CONFLICT RESOLUTION & CONCURRENCY

### 11.1 The Conflict Problem

If two users simultaneously request to buy pixel (500, 300), the server must:

- Grant it to exactly one user
- Return an informative error to the other
- Not deduct PXL from the loser
- Update all caches correctly

### 11.2 Solution: Redis Distributed Lock + MySQL Transaction

```php
// In buy.php:
function purchase_pixel(int $user_id, int $x, int $y, string $color): array {
    $redis = get_redis();
    $pdo = get_db();

    $lock_key = "pixel_lock:{$x}:{$y}";
    $lock_token = bin2hex(random_bytes(16)); // Unique token to prevent accidental unlock by wrong process

    // Attempt to acquire lock: SET key value NX PX 5000
    $locked = $redis->set($lock_key, $lock_token, ['NX', 'PX' => 5000]);

    if (!$locked) {
        return ['ok' => false, 'error' => 'concurrent_conflict', 'retry_after' => 1];
    }

    try {
        $pdo->beginTransaction();

        // Get current user balance (FOR UPDATE prevents other transactions from reading stale balance)
        $stmt = $pdo->prepare("SELECT pxl_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if (!$user || $user['pxl_balance'] < 1) {
            $pdo->rollBack();
            return ['ok' => false, 'error' => 'insufficient_pxl'];
        }

        // Get current pixel state (FOR UPDATE)
        $stmt = $pdo->prepare("SELECT owner_id, color FROM pixels WHERE x = ? AND y = ? FOR UPDATE");
        $stmt->execute([$x, $y]);
        $existing = $stmt->fetch();

        // Get current grid session
        $session_stmt = $pdo->query("SELECT id FROM grid_sessions WHERE is_current = 1 LIMIT 1");
        $grid_session = $session_stmt->fetch();

        $new_balance = $user['pxl_balance'] - 1;

        // Deduct PXL
        $stmt = $pdo->prepare("UPDATE users SET pxl_balance = ?, total_pxl_spent = total_pxl_spent + 1 WHERE id = ?");
        $stmt->execute([$new_balance, $user_id]);

        // Record transaction
        $stmt = $pdo->prepare("INSERT INTO pxl_transactions (user_id, amount, type, reference_id, balance_after, description) VALUES (?, -1, 'pixel_spend', ?, ?, ?)");
        $stmt->execute([$user_id, "{$x},{$y}", $new_balance, "Pixel ({$x},{$y}) set to {$color}"]);

        // Upsert pixel
        $stmt = $pdo->prepare("
            INSERT INTO pixels (x, y, color, owner_id, grid_session_id)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE color=VALUES(color), owner_id=VALUES(owner_id), purchased_at=NOW()
        ");
        $stmt->execute([$x, $y, $color, $user_id, $grid_session['id']]);

        // Record in history
        $stmt = $pdo->prepare("INSERT INTO pixel_history (user_id, x, y, color, pxl_cost, grid_session_id) VALUES (?, ?, ?, ?, 1, ?)");
        $stmt->execute([$user_id, $x, $y, $color, $grid_session['id']]);

        // Increment chunk version in DB
        $chunk_x = intdiv($x, 64);
        $chunk_y = intdiv($y, 64);
        $stmt = $pdo->prepare("UPDATE chunks SET version = version + 1 WHERE chunk_x = ? AND chunk_y = ?");
        $stmt->execute([$chunk_x, $chunk_y]);

        $pdo->commit();

        // Post-commit: Update Redis
        $redis->del("chunk:{$chunk_x}:{$chunk_y}"); // Invalidate chunk cache
        $redis->incr("chunk_v:{$chunk_x}:{$chunk_y}"); // Increment version counter

        // Broadcast via Redis pub/sub
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $u = $stmt->fetch();
        $redis->publish('sse_channel', json_encode([
            'type' => 'pixel',
            'x' => $x, 'y' => $y, 'color' => $color,
            'username' => $u['username'],
            'cx' => $chunk_x, 'cy' => $chunk_y,
            'chunk_version' => $redis->get("chunk_v:{$chunk_x}:{$chunk_y}")
        ]));

        return ['ok' => true, 'new_balance' => $new_balance, 'chunk_version' => ...];

    } catch (Exception $e) {
        $pdo->rollBack();
        log_error($e);
        return ['ok' => false, 'error' => 'server_error'];
    } finally {
        // Release lock only if we still own it (compare token)
        $current = $redis->get($lock_key);
        if ($current === $lock_token) {
            $redis->del($lock_key);
        }
    }
}
```

### 11.3 Handling Concurrent Balance Deduction

The `FOR UPDATE` on the user's balance row ensures no two concurrent transactions can both see a balance of 5 and both deduct 1, resulting in incorrect final balance. MySQL serializes these within the transaction.

### 11.4 Client-Side Conflict Handling

```javascript
async function purchasePixel(x, y, color) {
  showOptimisticPixel(x, y, color); // Show pending state

  const response = await fetch("/api/grid/buy.php", {
    method: "POST",
    body: JSON.stringify({ x, y, color }),
    headers: {
      "X-CSRF-Token": getCsrfToken(),
      "Content-Type": "application/json",
    },
  });
  const data = await response.json();

  if (data.ok) {
    confirmPixel(x, y, color);
    updateBalance(data.data.new_balance);
  } else if (data.error === "concurrent_conflict") {
    revertPixel(x, y);
    showToast("Someone else just bought that pixel! Try another.", "warning");
    // Refresh the specific chunk
    refreshChunk(Math.floor(x / 64), Math.floor(y / 64));
  } else if (data.error === "insufficient_pxl") {
    revertPixel(x, y);
    showToast("Not enough PXL! Play the game to earn more.", "error");
  } else if (data.error === "rate_limited") {
    revertPixel(x, y);
    showToast(`Slow down! Try again in ${data.retry_after}s.`, "warning");
  }
}
```

---

## 12. FILE & FOLDER STRUCTURE

```
/var/www/pixelforge/                    ← Web root parent
├── public/                             ← Nginx serves this directory ONLY
│   ├── index.php                       ← Landing page (login/register)
│   ├── game.php                        ← Game page (auth required)
│   ├── canvas.php                      ← Canvas page (public)
│   ├── profile.php                     ← User profile page
│   ├── leaderboard.php                 ← Leaderboard page
│   ├── verify.php                      ← Email verification redirect
│   ├── assets/
│   │   ├── css/
│   │   │   ├── main.css                ← Global styles, variables, layout
│   │   │   ├── game.css                ← Game-specific styles
│   │   │   └── canvas.css              ← Canvas viewer styles
│   │   ├── js/
│   │   │   ├── api.js                  ← API client (fetch wrapper, CSRF, error handling)
│   │   │   ├── auth.js                 ← Login/register/logout logic
│   │   │   ├── game/
│   │   │   │   ├── engine.js           ← Core game loop, physics, collision
│   │   │   │   ├── renderer.js         ← Canvas sprite/scene rendering
│   │   │   │   ├── prng.js             ← Seeded PRNG (mulberry32 algorithm)
│   │   │   │   ├── obstacles.js        ← Obstacle generation and logic
│   │   │   │   ├── collectibles.js     ← Shard and power-up logic
│   │   │   │   ├── audio.js            ← Web Audio API management
│   │   │   │   ├── hud.js              ← HUD rendering and updates
│   │   │   │   └── game-main.js        ← Entry point, session management
│   │   │   ├── canvas/
│   │   │   │   ├── grid-renderer.js    ← Chunk loading, canvas paint, zoom/pan
│   │   │   │   ├── chunk-cache.js      ← LRU chunk cache management
│   │   │   │   ├── sse-client.js       ← SSE connection management
│   │   │   │   ├── pixel-buyer.js      ← Purchase flow, optimistic updates
│   │   │   │   ├── mini-map.js         ← Mini-map overlay
│   │   │   │   └── canvas-main.js      ← Entry point, coordinate management
│   │   │   ├── ui.js                   ← Toast notifications, modals, tooltips
│   │   │   └── utils.js                ← Shared utilities
│   │   ├── fonts/
│   │   │   ├── SpaceGrotesk-Variable.woff2    ← (Actually use a different font - see UI spec)
│   │   │   └── JetBrainsMono-Regular.woff2    ← Monospace for coords/scores
│   │   ├── sounds/
│   │   │   ├── jump.ogg
│   │   │   ├── collect.ogg
│   │   │   ├── hit.ogg
│   │   │   ├── powerup.ogg
│   │   │   ├── death.ogg
│   │   │   ├── levelup.ogg
│   │   │   ├── bgm1.ogg
│   │   │   ├── bgm2.ogg
│   │   │   └── bgm3.ogg
│   │   └── sprites/
│   │       ├── pxlr-sheet.png          ← Player sprite sheet
│   │       └── obstacles.png           ← Obstacle sprite sheet
│   └── api/
│       ├── auth/
│       │   ├── login.php
│       │   ├── register.php
│       │   ├── logout.php
│       │   ├── verify.php
│       │   ├── forgot-password.php
│       │   └── reset-password.php
│       ├── game/
│       │   ├── start.php
│       │   ├── checkpoint.php
│       │   └── submit.php
│       ├── grid/
│       │   ├── chunk.php
│       │   ├── buy.php
│       │   ├── pixel-info.php
│       │   └── updates.php             ← SSE endpoint
│       ├── user/
│       │   ├── me.php
│       │   ├── profile.php
│       │   ├── achievements.php
│       │   └── claim-achievement.php
│       └── leaderboard.php
├── includes/                           ← NOT web accessible (Nginx deny)
│   ├── bootstrap.php                   ← Require all includes, session start
│   ├── config.php                      ← DB/Redis creds (from env vars)
│   ├── db.php                          ← PDO singleton
│   ├── redis.php                       ← Redis singleton
│   ├── session.php                     ← Custom session handler
│   ├── auth.php                        ← Auth helpers (require_auth, get_user)
│   ├── security.php                    ← Headers, CSRF, input helpers
│   ├── rate_limit.php                  ← Rate limit functions
│   ├── validate.php                    ← Input validation functions
│   ├── response.php                    ← JSON response helpers
│   ├── game_validator.php              ← Anti-cheat validation
│   ├── pxl.php                         ← PXL credit/debit functions
│   ├── achievement.php                 ← Achievement check/grant functions
│   └── logger.php                      ← Error/audit logging
├── cron/
│   ├── reset_grid.php                  ← Grid reset (runs Sunday 00:00 UTC)
│   ├── cleanup_sessions.php            ← Remove old game sessions (daily)
│   └── cleanup_login_attempts.php      ← Purge old login attempts (daily)
├── admin/
│   ├── index.php                       ← Admin dashboard (separate auth)
│   ├── users.php                       ← User management
│   ├── grid.php                        ← Grid overview, manual reset
│   └── flagged.php                     ← Flagged game sessions
├── logs/                               ← Server logs (not web accessible)
│   ├── errors.log
│   └── audit.log
└── .env                                ← Environment variables (not in web root)
```

---

## 13. FRONTEND ARCHITECTURE

### 13.1 No Build Step

All JavaScript is written as standard ES6 modules loaded with `type="module"`. No bundlers, no transpilers, no npm.

```html
<script type="module" src="/assets/js/canvas/canvas-main.js"></script>
```

### 13.2 Module Pattern

Each JS file exports named functions/classes. Dependencies imported at top:

```javascript
// canvas/canvas-main.js
import { GridRenderer } from "./grid-renderer.js";
import { ChunkCache } from "./chunk-cache.js";
import { SSEClient } from "./sse-client.js";
import { PixelBuyer } from "./pixel-buyer.js";
import { MiniMap } from "./mini-map.js";
import { ApiClient } from "../api.js";
import { showToast } from "../ui.js";
```

### 13.3 State Management

No frameworks. Use a simple module-level state object with getters/setters:

```javascript
// canvas/canvas-main.js
const state = {
  viewX: 0, // Current view left edge in grid coords
  viewY: 0, // Current view top edge in grid coords
  zoom: 4, // Current zoom level (1,2,4,8,16)
  selectedColor: "#000000",
  userBalance: 0,
  isDragging: false,
  purchaseMode: false, // true = click to buy, false = click to pan
  pendingPixels: new Map(), // key: "x,y", value: {color, status}
};
```

### 13.4 API Client (api.js)

```javascript
// Fetch wrapper handling CSRF, error handling, JSON parsing
class ApiClient {
  constructor() {
    this.csrfToken = document.querySelector('meta[name="csrf-token"]').content;
  }

  async post(url, data) {
    const res = await fetch(url, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-CSRF-Token": this.csrfToken,
      },
      body: JSON.stringify(data),
    });
    if (!res.ok && res.status !== 422 && res.status !== 429) {
      throw new Error(`HTTP ${res.status}`);
    }
    return res.json();
  }

  async get(url) {
    const res = await fetch(url, { credentials: "same-origin" });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  async getBinary(url) {
    const res = await fetch(url, { credentials: "same-origin" });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return {
      data: new Uint8Array(await res.arrayBuffer()),
      version: parseInt(res.headers.get("X-Chunk-Version") || "0"),
    };
  }
}
```

### 13.5 Game Engine Architecture

```javascript
// game/engine.js — Core loop

class GameEngine {
  constructor(canvas, seed) {
    this.canvas = canvas;
    this.ctx = canvas.getContext("2d");
    this.prng = new SeededPRNG(seed); // From prng.js
    this.renderer = new GameRenderer(canvas);
    this.obstacles = new ObstacleManager(this.prng);
    this.collectibles = new CollectibleManager(this.prng);
    this.audio = new AudioManager();
    this.hud = new HUD();

    this.state = {
      running: false,
      paused: false,
      score: 0,
      lives: 3,
      combo: 0,
      speedBPS: 5.0,
      speedTier: 1,
      elapsedMs: 0,
      lastCheckpointMs: 0,
      activePoweup: null,
      powerupExpiresAt: null,
      pxlr: { x: 100, y: GROUND_Y, vy: 0, state: "running", isSliding: false },
    };
  }

  start() {
    /* ... */
  }

  gameLoop(timestamp) {
    if (!this.state.running || this.state.paused) return;

    const dt = Math.min((timestamp - this.lastTimestamp) / 1000, 0.05); // Delta time, cap at 50ms
    this.lastTimestamp = timestamp;
    this.state.elapsedMs += dt * 1000;

    this.update(dt);
    this.render();
    this.checkAchievements();
    this.maybeSendCheckpoint();

    this.animFrame = requestAnimationFrame(this.gameLoop.bind(this));
  }

  update(dt) {
    this.updatePlayer(dt);
    this.updateObstacles(dt);
    this.updateCollectibles(dt);
    this.checkCollisions();
    this.updateSpeed();
    this.updatePowerup(dt);
    this.hud.update(this.state);
  }

  // Physics constants:
  // GROUND_Y = canvas.height - 60 (ground level)
  // GRAVITY = 2800 px/s²
  // JUMP_VELOCITY = -900 px/s
  // DOUBLE_JUMP_VELOCITY = -750 px/s
}
```

### 13.6 PRNG (Seeded, Deterministic)

Use the **Mulberry32** algorithm — fast, good quality, fully deterministic from seed:

```javascript
// prng.js
class SeededPRNG {
  constructor(seed) {
    this.state = seed >>> 0;
  }

  next() {
    this.state |= 0;
    this.state = (this.state + 0x6d2b79f5) | 0;
    let z = Math.imul(this.state ^ (this.state >>> 15), 1 | this.state);
    z = (z + Math.imul(z ^ (z >>> 7), 61 | z)) ^ z;
    return ((z ^ (z >>> 14)) >>> 0) / 4294967296;
  }

  nextInt(min, max) {
    return Math.floor(this.next() * (max - min + 1)) + min;
  }

  nextBool(probability = 0.5) {
    return this.next() < probability;
  }

  pick(array) {
    return array[Math.floor(this.next() * array.length)];
  }

  weightedPick(items, weights) {
    const totalWeight = weights.reduce((a, b) => a + b, 0);
    let r = this.next() * totalWeight;
    for (let i = 0; i < items.length; i++) {
      r -= weights[i];
      if (r <= 0) return items[i];
    }
    return items[items.length - 1];
  }
}
```

---

## 14. UI/UX DESIGN SPECIFICATION

### 14.1 Design Language: "Digital Atelier"

The visual style is clean, editorial, and precise — inspired by professional creative tools and digital art studios. Think Figma meets retro terminal. Not flashy, but confidently designed.

**Aesthetic Direction:** Dark sidebar navigation with clean white content area. Monospace accents for technical data (coordinates, scores, balances). Crisp typography, no gradients on content areas, strategic use of the primary accent color. The UI should feel like a professional tool, not a game site.

### 14.2 Typography

- **Display / Navigation:** `Outfit` (Google Fonts) — weights 400, 500, 600, 700
- **Monospace (scores, coords, PXL amounts, code):** `JetBrains Mono` — weights 400, 600
- **Body / Prose:** `Outfit` 400

```css
@import url("https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap");
```

### 14.3 Color System (CSS Custom Properties)

```css
:root {
  /* Core */
  --bg-primary: #f7f7f8; /* Page background */
  --bg-secondary: #ffffff; /* Card backgrounds */
  --bg-sidebar: #111318; /* Sidebar background */
  --bg-sidebar-hover: #1c2029; /* Sidebar item hover */

  /* Accent */
  --accent: #5b4fff; /* Primary accent - electric violet */
  --accent-hover: #4a3fe6;
  --accent-light: #eeedff; /* Accent tint for backgrounds */

  /* Text */
  --text-primary: #111318; /* Main text */
  --text-secondary: #6b7280; /* Secondary text */
  --text-sidebar: #e5e7eb; /* Sidebar text */
  --text-sidebar-muted: #9ca3af; /* Sidebar muted */

  /* Semantic */
  --color-success: #10b981;
  --color-warning: #f59e0b;
  --color-error: #ef4444;
  --color-info: #3b82f6;

  /* PXL Currency color */
  --color-pxl: #f59e0b; /* Amber - currency feel */

  /* Borders */
  --border-color: #e5e7eb;
  --border-radius-sm: 6px;
  --border-radius-md: 10px;
  --border-radius-lg: 16px;

  /* Shadows */
  --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.07), 0 1px 2px rgba(0, 0, 0, 0.05);
  --shadow-md:
    0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
  --shadow-lg:
    0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);

  /* Layout */
  --sidebar-width: 220px;
  --header-height: 60px;
  --content-max-width: 1200px;
}
```

### 14.4 Page Layout

All pages use the same layout shell:

```html
<div class="app-shell">
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="brand-logo">PF</div>
      <div class="brand-text">
        <span class="brand-name">PixelForge</span>
        <span class="brand-tagline">Paint the World</span>
      </div>
    </div>
    <nav class="sidebar-nav">
      <a href="/canvas.php" class="nav-item">
        <span class="nav-icon">⬜</span>
        <span class="nav-label">The Forge</span>
      </a>
      <a href="/game.php" class="nav-item">
        <span class="nav-icon">▶</span>
        <span class="nav-label">Pixel Dash</span>
      </a>
      <a href="/leaderboard.php" class="nav-item">
        <span class="nav-icon">◈</span>
        <span class="nav-label">Leaderboard</span>
      </a>
      <a href="/profile.php" class="nav-item">
        <span class="nav-icon">◎</span>
        <span class="nav-label">Profile</span>
      </a>
    </nav>
    <div class="sidebar-footer">
      <div class="balance-display">
        <span class="balance-icon">◆</span>
        <span class="balance-amount mono"
          ><?= h($user['pxl_balance'] ?? 0) ?>
          PXL</span
        >
      </div>
      <div class="user-tag">@<?= h($user['username'] ?? 'guest') ?></div>
    </div>
  </aside>
  <main class="main-content">
    <header class="top-bar">
      <!-- Page-specific header content -->
    </header>
    <div class="content-area">
      <!-- Page content -->
    </div>
  </main>
</div>
```

### 14.5 Sidebar Styling

```css
.sidebar {
  width: var(--sidebar-width);
  background: var(--bg-sidebar);
  height: 100vh;
  position: fixed;
  left: 0;
  top: 0;
  display: flex;
  flex-direction: column;
  padding: 0;
  z-index: 100;
}

.sidebar-brand {
  padding: 24px 20px 20px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
  display: flex;
  align-items: center;
  gap: 12px;
}

.brand-logo {
  width: 36px;
  height: 36px;
  background: var(--accent);
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: "JetBrains Mono", monospace;
  font-weight: 600;
  color: white;
  font-size: 13px;
}

.brand-name {
  color: white;
  font-weight: 600;
  font-size: 15px;
  display: block;
}
.brand-tagline {
  color: var(--text-sidebar-muted);
  font-size: 11px;
  display: block;
}

.sidebar-nav {
  flex: 1;
  padding: 16px 10px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  border-radius: 8px;
  color: var(--text-sidebar-muted);
  text-decoration: none;
  font-size: 14px;
  font-weight: 500;
  transition:
    background 0.15s,
    color 0.15s;
}

.nav-item:hover,
.nav-item.active {
  background: var(--bg-sidebar-hover);
  color: var(--text-sidebar);
}

.nav-item.active {
  color: white;
}
.nav-icon {
  font-size: 16px;
  width: 20px;
  text-align: center;
}

.sidebar-footer {
  padding: 16px 20px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.balance-display {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 4px;
}

.balance-icon {
  color: var(--color-pxl);
  font-size: 14px;
}
.balance-amount {
  color: var(--color-pxl);
  font-size: 15px;
  font-weight: 600;
}
.user-tag {
  color: var(--text-sidebar-muted);
  font-size: 12px;
}

.main-content {
  margin-left: var(--sidebar-width);
  min-height: 100vh;
}
```

### 14.6 Cards

```css
.card {
  background: var(--bg-secondary);
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius-md);
  padding: 24px;
  box-shadow: var(--shadow-sm);
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
  padding-bottom: 16px;
  border-bottom: 1px solid var(--border-color);
}

.card-title {
  font-size: 16px;
  font-weight: 600;
  color: var(--text-primary);
}
.card-subtitle {
  font-size: 13px;
  color: var(--text-secondary);
  margin-top: 2px;
}
```

### 14.7 Buttons

```css
.btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 9px 18px;
  border-radius: var(--border-radius-sm);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  border: none;
  transition:
    background 0.15s,
    transform 0.1s,
    box-shadow 0.15s;
  font-family: inherit;
  text-decoration: none;
}

.btn-primary {
  background: var(--accent);
  color: white;
}
.btn-primary:hover {
  background: var(--accent-hover);
  box-shadow: 0 0 0 3px var(--accent-light);
}
.btn-primary:active {
  transform: scale(0.98);
}

.btn-secondary {
  background: var(--bg-primary);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}
.btn-secondary:hover {
  background: var(--border-color);
}

.btn-danger {
  background: var(--color-error);
  color: white;
}
.btn-sm {
  padding: 6px 12px;
  font-size: 13px;
}
.btn-lg {
  padding: 12px 24px;
  font-size: 16px;
}
```

### 14.8 Form Inputs

```css
.input-field {
  width: 100%;
  padding: 9px 12px;
  border: 1.5px solid var(--border-color);
  border-radius: var(--border-radius-sm);
  font-size: 14px;
  color: var(--text-primary);
  background: var(--bg-secondary);
  transition:
    border-color 0.15s,
    box-shadow 0.15s;
  font-family: inherit;
  box-sizing: border-box;
}

.input-field:focus {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-light);
}

.input-field.error {
  border-color: var(--color-error);
}

.form-group {
  margin-bottom: 16px;
}
.form-label {
  display: block;
  font-size: 13px;
  font-weight: 500;
  color: var(--text-primary);
  margin-bottom: 6px;
}
.form-error {
  font-size: 12px;
  color: var(--color-error);
  margin-top: 4px;
}
```

### 14.9 Toast Notifications

```css
.toast-container {
  position: fixed;
  bottom: 24px;
  right: 24px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  z-index: 9999;
  pointer-events: none;
}

.toast {
  background: var(--bg-sidebar);
  color: var(--text-sidebar);
  padding: 12px 16px;
  border-radius: var(--border-radius-sm);
  font-size: 14px;
  box-shadow: var(--shadow-lg);
  pointer-events: all;
  display: flex;
  align-items: center;
  gap: 10px;
  max-width: 320px;
  animation: toast-in 0.25s ease;
  border-left: 3px solid var(--color-info);
}

.toast.success {
  border-left-color: var(--color-success);
}
.toast.error {
  border-left-color: var(--color-error);
}
.toast.warning {
  border-left-color: var(--color-warning);
}

@keyframes toast-in {
  from {
    transform: translateX(100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}
```

### 14.10 Canvas Page Layout

The canvas page is full-height with no scrollbar. Toolbar floats above canvas.

```
┌─────────────────────────────────────────────────────────────┐
│ [SIDEBAR] │ [CANVAS TOOLBAR]                                 │
│           │ ┌─────────────────────────────────────────────┐ │
│           │ │                                             │ │
│           │ │         CANVAS (fills remaining space)      │ │
│           │ │                                             │ │
│           │ │                         [MINI-MAP]          │ │
│           │ └─────────────────────────────────────────────┘ │
│           │ [STATUS BAR: coords, zoom, online users]         │
└─────────────────────────────────────────────────────────────┘
```

**Canvas Toolbar** (floating, above canvas):

- Color palette (32 swatches)
- Custom color hex input
- Selected color preview
- Zoom controls (+/-)
- Mode toggle (Pan | Paint)
- Coordinate search (Go to x,y)

### 14.11 Game Page Layout

Game canvas centered in content area with HUD overlay, responsive.

### 14.12 Responsive Design

- Sidebar collapses to icon-only on viewport < 768px
- Canvas: full screen on mobile, toolbar becomes bottom drawer
- Game: canvas scales to viewport width maintaining aspect ratio

---

## 15. PHP BACKEND ARCHITECTURE

### 15.1 bootstrap.php

Every API endpoint starts with:

```php
<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
```

`bootstrap.php` does (in order):

1. Set error reporting (log all, display none in production)
2. Set PHP error log path
3. Load `.env` file (parse manually, no library)
4. Define constants from env
5. Set security-related php.ini settings
6. Start custom Redis-backed session
7. Send all security headers
8. Set up global exception handler (log + return 500 JSON)

### 15.2 Response Helpers (response.php)

```php
function respond_success(array $data = [], int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function respond_error(string $error, string $message = '', int $code = 400, array $extra = []): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['ok' => false, 'error' => $error, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function require_method(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== strtoupper($method)) {
        respond_error('method_not_allowed', 'Method not allowed', 405);
    }
}

function require_auth(): array {
    if (empty($_SESSION['user_id'])) {
        respond_error('unauthenticated', 'Login required', 401);
    }
    // Refresh session data from Redis/DB
    return get_current_user_data();
}

function require_verified(): array {
    $user = require_auth();
    if (!$user['email_verified']) {
        respond_error('email_not_verified', 'Please verify your email first', 403);
    }
    return $user;
}

function get_json_body(): array {
    $body = file_get_contents('php://input');
    $data = json_decode($body, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        respond_error('invalid_json', 'Invalid JSON body', 400);
    }
    return $data ?? [];
}
```

### 15.3 Database Connection (db.php)

```php
<?php
// Singleton PDO
function get_db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
            PDO::ATTR_PERSISTENT => false, // No persistent connections (use PHP-FPM pooling)
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}
```

### 15.4 PXL Management (pxl.php)

```php
function credit_pxl(PDO $pdo, int $user_id, int $amount, string $type, string $ref = '', string $desc = ''): int {
    // Must be called within a transaction
    $stmt = $pdo->prepare("UPDATE users SET pxl_balance = pxl_balance + ?, total_pxl_earned = total_pxl_earned + ? WHERE id = ?");
    $stmt->execute([$amount, $amount, $user_id]);

    $stmt = $pdo->prepare("SELECT pxl_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $new_balance = $stmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO pxl_transactions (user_id, amount, type, reference_id, balance_after, description) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$user_id, $amount, $type, $ref, $new_balance, $desc]);

    return (int)$new_balance;
}

function debit_pxl(PDO $pdo, int $user_id, int $amount, string $type, string $ref = '', string $desc = ''): int {
    // Must be called within a transaction, after checking balance
    $stmt = $pdo->prepare("UPDATE users SET pxl_balance = pxl_balance - ?, total_pxl_spent = total_pxl_spent + ? WHERE id = ? AND pxl_balance >= ?");
    $stmt->execute([$amount, $amount, $user_id, $amount]);

    if ($stmt->rowCount() === 0) throw new RuntimeException('Insufficient balance');

    $stmt = $pdo->prepare("SELECT pxl_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $new_balance = $stmt->fetchColumn();

    $stmt = $pdo->prepare("INSERT INTO pxl_transactions (user_id, amount, type, reference_id, balance_after, description) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$user_id, -$amount, $type, $ref, $new_balance, $desc]);

    return (int)$new_balance;
}
```

### 15.5 Achievement System (achievement.php)

```php
function check_and_grant_achievements(PDO $pdo, int $user_id, string $context, array $context_data = []): array {
    $unlocked = [];

    // Load user stats needed for checking
    $stmt = $pdo->prepare("
        SELECT u.*,
            COUNT(DISTINCT ph.id) as total_pixels,
            MAX(s.score) as best_score,
            MAX(s.max_speed_tier) as best_speed_tier
        FROM users u
        LEFT JOIN pixel_history ph ON ph.user_id = u.id
        LEFT JOIN scores s ON s.user_id = u.id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();

    $to_check = match($context) {
        'game_submit' => ['first_game', 'speed_tier_3', 'speed_tier_5', 'speed_tier_7',
                          'score_500', 'score_2000', 'score_5000', 'score_10000',
                          'combo_15', 'combo_35', 'rainbow_5', 'bomb_used', 'total_earned_100'],
        'pixel_buy'   => ['first_pixel', 'pixels_50', 'pixels_250', 'pixels_1000'],
        'login'       => ['streak_3', 'streak_7', 'streak_30'],
        default       => []
    };

    // Get already earned achievements
    $stmt = $pdo->prepare("SELECT achievement_id FROM user_achievements WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $earned_ids = array_column($stmt->fetchAll(), 'achievement_id');

    // Get achievement definitions
    $stmt = $pdo->prepare("SELECT * FROM achievements WHERE key_name IN (" . implode(',', array_fill(0, count($to_check), '?')) . ")");
    $stmt->execute($to_check);
    $achievements = $stmt->fetchAll();

    foreach ($achievements as $ach) {
        if (in_array($ach['id'], $earned_ids)) continue; // Already earned

        $earned = match($ach['key_name']) {
            'first_game'     => true, // If we're in game_submit context, first game done
            'speed_tier_3'   => ($context_data['max_speed_tier'] ?? 0) >= 3,
            'speed_tier_5'   => ($context_data['max_speed_tier'] ?? 0) >= 5,
            'speed_tier_7'   => ($context_data['max_speed_tier'] ?? 0) >= 7,
            'score_500'      => ($context_data['final_score'] ?? 0) >= 500,
            'score_2000'     => ($context_data['final_score'] ?? 0) >= 2000,
            'score_5000'     => ($context_data['final_score'] ?? 0) >= 5000,
            'score_10000'    => ($context_data['final_score'] ?? 0) >= 10000,
            'combo_15'       => ($context_data['max_combo'] ?? 0) >= 15,
            'combo_35'       => ($context_data['max_combo'] ?? 0) >= 35,
            'rainbow_5'      => ($context_data['prisms_collected'] ?? 0) >= 5,
            'bomb_used'      => ($context_data['bomb_used'] ?? false) === true,
            'first_pixel'    => (int)$stats['total_pixels'] >= 1,
            'pixels_50'      => (int)$stats['total_pixels'] >= 50,
            'pixels_250'     => (int)$stats['total_pixels'] >= 250,
            'pixels_1000'    => (int)$stats['total_pixels'] >= 1000,
            'total_earned_100'=> (int)$stats['total_pxl_earned'] >= 100,
            'streak_3'       => (int)$stats['login_streak'] >= 3,
            'streak_7'       => (int)$stats['login_streak'] >= 7,
            'streak_30'      => (int)$stats['login_streak'] >= 30,
            default          => false
        };

        if ($earned) {
            $pdo->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?, ?)")
                ->execute([$user_id, $ach['id']]);
            // Note: PXL is credited when user CLAIMS the achievement via UI, not auto-credited
            $unlocked[] = ['key' => $ach['key_name'], 'title' => $ach['title'], 'pxl' => $ach['pxl_reward']];
        }
    }

    return $unlocked;
}
```

---

## 16. REDIS ARCHITECTURE

### 16.1 Key Namespace Summary

```
# Chunk cache
chunk:{cx}:{cy}          → Binary string, 12288 bytes (packed RGB), TTL 300s
chunk_v:{cx}:{cy}        → Integer version counter

# Game sessions
game_active:{user_id}    → session_id string, TTL 7200s

# Rate limiting
rl:{action}:{identifier} → Sorted set (sliding window), TTL = window_seconds

# Session storage (custom handler)
sess:{session_id}        → Serialized session data, TTL 86400s

# SSE pub/sub
sse_channel              → Pub/Sub channel (no persistence)

# Leaderboard cache
lb:daily                 → JSON, TTL 60s
lb:weekly                → JSON, TTL 300s
lb:alltime               → JSON, TTL 600s

# Login lockout
login_fail:{ip}          → Sorted set, TTL 900s

# Pixel locks
pixel_lock:{x}:{y}       → Token string, TTL 5000ms (5 seconds)

# Daily bonus tracking
daily_bonus:{user_id}:{date}  → "1", TTL 86400s
daily_game:{user_id}:{date}   → "1", TTL 86400s (first game submitted today)
```

### 16.2 Redis Connection (redis.php)

```php
function get_redis(): Redis {
    static $redis = null;
    if ($redis === null) {
        $redis = new Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT, 2.0); // 2s timeout
        if (REDIS_PASS) $redis->auth(REDIS_PASS);
        $redis->select(REDIS_DB); // Use DB 0 for app, DB 1 for sessions
    }
    return $redis;
}
```

### 16.3 Chunk Cache Building

```php
function build_chunk_cache(int $cx, int $cy): string {
    $pdo = get_db();
    $x_min = $cx * 64;
    $x_max = $x_min + 63;
    $y_min = $cy * 64;
    $y_max = $y_min + 63;

    $stmt = $pdo->prepare("SELECT x, y, color FROM pixels WHERE x BETWEEN ? AND ? AND y BETWEEN ? AND ? AND grid_session_id = (SELECT id FROM grid_sessions WHERE is_current=1)");
    $stmt->execute([$x_min, $x_max, $y_min, $y_max]);
    $rows = $stmt->fetchAll();

    // Build 12288-byte binary (64*64*3, default white = 0xFF 0xFF 0xFF)
    $buffer = str_repeat("\xFF\xFF\xFF", 64 * 64);

    foreach ($rows as $row) {
        $lx = $row['x'] - $x_min;
        $ly = $row['y'] - $y_min;
        $offset = ($ly * 64 + $lx) * 3;
        $color = $row['color']; // "#RRGGBB"
        $buffer[$offset]   = chr(hexdec(substr($color, 1, 2)));
        $buffer[$offset+1] = chr(hexdec(substr($color, 3, 2)));
        $buffer[$offset+2] = chr(hexdec(substr($color, 5, 2)));
    }

    $redis = get_redis();
    $redis->setex("chunk:{$cx}:{$cy}", 300, $buffer);
    return $buffer;
}
```

---

## 17. CRON JOBS & SCHEDULED TASKS

### 17.1 Crontab Entries

```cron
# Grid reset — Every Sunday at 00:00 UTC
0 0 * * 0 php /var/www/pixelforge/cron/reset_grid.php >> /var/log/pixelforge/cron.log 2>&1

# Clean old game sessions — Daily at 03:00 UTC
0 3 * * * php /var/www/pixelforge/cron/cleanup_sessions.php >> /var/log/pixelforge/cron.log 2>&1

# Clean login attempts older than 24 hours — Daily at 04:00 UTC
0 4 * * * php /var/www/pixelforge/cron/cleanup_login_attempts.php >> /var/log/pixelforge/cron.log 2>&1
```

### 17.2 Grid Reset Script (reset_grid.php)

```php
// FULL ALGORITHM:
// 1. Get current grid_session_id
// 2. Take snapshot: Query all pixels in current session, render to PNG using GD or Imagick
//    - Create 2048x2048 image, set each pixel color, save as PNG to /var/www/pixelforge/snapshots/session_{id}.png
// 3. Update grid_sessions: SET is_current=0, ended_at=NOW(), snapshot_filename=..., total_pixels_painted=COUNT, unique_painters=COUNT(DISTINCT owner_id)
// 4. INSERT new grid_sessions row (is_current=1)
// 5. DELETE FROM pixels (truncate current state — all pixels reset to white/unowned)
// 6. FLUSH all chunk caches in Redis: del all chunk:{*} keys
// 7. Reset chunk versions: SET chunk_v:{cx}:{cy} = 0 for all chunks
// 8. Broadcast SSE event: {"type":"grid_reset","new_session_id":N}
// 9. Log success
```

### 17.3 Session Cleanup (cleanup_sessions.php)

```php
// DELETE FROM game_sessions WHERE started_at < DATE_SUB(NOW(), INTERVAL 30 DAY) AND ended_at IS NOT NULL
// DELETE FROM game_sessions WHERE started_at < DATE_SUB(NOW(), INTERVAL 6 HOUR) AND ended_at IS NULL
// (Mark invalidated: score could never be submitted for sessions > 6h old)
```

---

## 18. CONFIGURATION & ENVIRONMENT

### 18.1 .env File

```env
# Application
APP_ENV=production
APP_SECRET=64-char-random-string-here
GAME_HMAC_KEY=another-64-char-random-string

# Database
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=pixelforge
DB_USER=pixelforge_user
DB_PASS=strong-random-password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASS=
REDIS_DB=0
REDIS_SESSION_DB=1

# Email (for verification)
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=noreply@pixelforge.example
SMTP_PASS=smtp-password
SMTP_FROM=noreply@pixelforge.example
SMTP_FROM_NAME=PixelForge

# Admin
ADMIN_USERNAME=admin
ADMIN_PASSWORD_HASH=bcrypt-hash-of-admin-password

# Grid
GRID_RESET_DAY=0    # 0=Sunday
GRID_PIXEL_COST=1
```

### 18.2 config.php

```php
<?php
// Load .env
$env_file = dirname(__DIR__) . '/.env';
if (file_exists($env_file)) {
    foreach (file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$key, $val] = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($val);
    }
}

define('APP_ENV',      $_ENV['APP_ENV'] ?? 'production');
define('APP_SECRET',   $_ENV['APP_SECRET']);
define('GAME_HMAC_KEY',$_ENV['GAME_HMAC_KEY']);
define('DB_HOST',      $_ENV['DB_HOST'] ?? '127.0.0.1');
define('DB_PORT',      $_ENV['DB_PORT'] ?? 3306);
define('DB_NAME',      $_ENV['DB_NAME']);
define('DB_USER',      $_ENV['DB_USER']);
define('DB_PASS',      $_ENV['DB_PASS']);
define('REDIS_HOST',   $_ENV['REDIS_HOST'] ?? '127.0.0.1');
define('REDIS_PORT',   (int)($_ENV['REDIS_PORT'] ?? 6379));
define('REDIS_PASS',   $_ENV['REDIS_PASS'] ?? '');
define('REDIS_DB',     (int)($_ENV['REDIS_DB'] ?? 0));
define('REDIS_SESSION_DB', (int)($_ENV['REDIS_SESSION_DB'] ?? 1));

// Security
define('MAX_SCORE_PER_SECOND_HARD', 200);
define('MAX_SCORE_PER_SECOND_SUSTAINED', 80);
define('GRID_SIZE', 2048);
define('CHUNK_SIZE', 64);
define('PIXEL_COST_PXL', 1);
define('PXL_PER_200_SCORE', 1); // 200 score = 1 PXL

if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    ini_set('error_log', '/var/log/pixelforge/errors.log');
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}
```

### 18.3 Nginx Configuration

```nginx
server {
    listen 443 ssl http2;
    server_name pixelforge.example.com;
    root /var/www/pixelforge/public;
    index index.php;

    # SSL (use Let's Encrypt)
    ssl_certificate /etc/letsencrypt/live/pixelforge.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/pixelforge.example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;
    add_header X-Frame-Options "DENY" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Hide nginx version
    server_tokens off;

    # Block access to includes/
    location /includes/ { deny all; return 404; }
    location /cron/     { deny all; return 404; }
    location /logs/     { deny all; return 404; }
    location /.env      { deny all; return 404; }
    location /admin/    {
        # Restrict admin to specific IPs
        allow 203.0.113.0/24; # Your IP range
        deny all;
        try_files $uri $uri/ /admin/index.php?$query_string;
    }

    # SSE: disable buffering
    location /api/grid/updates.php {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 3600;       # Long-lived connection
        fastcgi_buffering off;           # Critical for SSE
        proxy_buffering off;
        add_header X-Accel-Buffering no;
        add_header Cache-Control no-cache;
    }

    # PHP files
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 30;
    }

    # Static files caching
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|woff2|ogg)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Gzip
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml;
    gzip_min_length 1000;
}

# HTTP → HTTPS redirect
server {
    listen 80;
    server_name pixelforge.example.com;
    return 301 https://$server_name$request_uri;
}
```

---

## 19. COMPLETE IMPLEMENTATION DETAILS

### 19.1 Login Page (index.php)

The landing page is a split-view: left side has the app branding and tagline, right side has the login form with toggle to register form. Fully responsive.

Features:

- Animated pixel art canvas in the left panel (decorative mini-canvas showing random colors being painted)
- Toggle between Login and Register without page reload
- Show/hide password toggle
- Inline form validation (JS + server-side)
- CSRF token embedded as meta tag AND hidden form field
- After login: redirect to `/canvas.php`
- Guest can visit `/canvas.php` directly (read-only mode, no purchase)

### 19.2 Canvas Page (canvas.php)

The main event. Full layout as described in 14.10.

**Toolbar components:**

- Color palette: 32 swatches in a scrollable row, click to select
- Custom hex input: `#` + 6 char input, auto-formats, live preview
- Selected color swatch (large, shows current choice)
- Zoom in/out buttons + current zoom display
- Mode toggle: [🖐 Pan] [✏️ Paint] — styled as segmented control
- Coordinate search: two inputs for X and Y + "Go" button

**Canvas behaviors:**

- On hover (in Paint mode): show 1px highlight on hovered pixel, show coordinates in status bar
- On hover (pixel info): after 500ms hover, fetch pixel-info and show tooltip with owner name and purchase time
- On click (Paint mode): show confirmation popover → confirm → purchase
- On right-click: context menu "View info" / "Copy coords"
- Keyboard shortcut: `Space` to toggle Pan/Paint mode, `+`/`-` for zoom, `G` for Go-to dialog

**Mini-map:**

- Bottom-right, 200×200px canvas
- Shows all chunk data at 1:10 scale (each pixel = 3.2 grid pixels)
- Red rectangle shows current viewport
- Click/drag to navigate
- Updates in real-time from SSE events

**Status bar:**

- `X: 500  Y: 300` (monospace, updates on mousemove)
- `Zoom: 4×`
- `Online: 142` (polled from `/api/stats/online.php` every 30s)
- `Resets in: 4d 12h 3m` (countdown to next Sunday 00:00 UTC, calculated client-side)

### 19.3 Game Page (game.php)

**Before game starts:**

- Stats panel: your best score, today's rank, PXL balance
- "PLAY" button (large, prominent)
- Instructions (expandable accordion)
- Leaderboard preview (top 5)

**Game area:**

- Canvas exactly 800×300px, centered, with pixel-border style frame
- Scales down on mobile maintaining ratio
- HUD overlay: lives, score, combo, PXL balance (read-only, shows current balance)
- Pause overlay: semi-transparent dark overlay with "PAUSED" text and "RESUME" / "QUIT" buttons

**After game:**

- Game-over overlay slides up from bottom
- Score display with counting animation (counter goes 0 → final score over 1.5 seconds)
- "PXL EARNED: +N" displays after API responds
- Achievement popups slide in from right one at a time (if any)
- Confetti animation if personal best
- Leaderboard rank: "You are #42 today"

### 19.4 Profile Page (profile.php)

Sections:

1. **User Card**: avatar (pixel-art generated from username hash), username, join date, login streak badge
2. **Stats Row**: Total PXL Earned / Total Pixels Painted / Best Score / Games Played (4 stat cards in a row)
3. **Achievements Grid**: All achievements shown as cards; earned = full color with earned date; unearned = grayscale with description
4. **Recent Pixels**: Gallery showing last 20 pixel purchases (x, y, color swatch, date)
5. **Score History**: Last 10 games in a table
6. **PXL Transaction History**: Last 30 transactions in a timeline

### 19.5 Leaderboard Page (leaderboard.php)

Tabs: **Daily** | **Weekly** | **All-Time**

Table columns: Rank | Player | Score | PXL Earned | Duration | Date

Top 3 have gold/silver/bronze row highlighting.
Your own row (if logged in and in top 100) is highlighted in accent.

Pagination: 20 per page.

### 19.6 Email Templates

Use PHP to generate HTML emails. No external library needed.

**Verification email:**

- Subject: "Verify your PixelForge account"
- Body: Brief welcome, large "VERIFY EMAIL" button linking to `/verify.php?token={token}`
- Plain text fallback
- Branded with PixelForge colors

**Password reset email:**

- Subject: "Reset your PixelForge password"
- Body: Reset button + expiry warning (1 hour)

**Send via PHP's `mail()` or direct SMTP socket. If using SMTP, implement a minimal SMTP class without external libraries.**

### 19.7 Error Pages

Custom 404, 403, 500 error pages matching site design (styled as PHP files in public/).

### 19.8 Online Users Count

Implement via Redis:

- On session start: `ZADD online_users {current_timestamp} {user_id}`
- Every 60 seconds client-side: `GET /api/stats/online.php` which runs `ZCOUNT online_users {now-120} {now}` (users active in last 2 minutes)
- On page load/every-60s on server: `ZADD online_users {timestamp} {user_id}` (upsert via score update)
- Count includes anonymous viewers (use session ID as key, not user ID)

### 19.9 Grid Reset Announcement

5 minutes before reset (detected client-side via countdown), show a dismissible banner:
"⚠️ The Forge resets in 5 minutes! Save your masterpiece by screenshotting it."

At reset: SSE event `{"type":"grid_reset"}` causes all clients to:

1. Clear chunk cache
2. Re-render canvas (all white)
3. Show toast: "🎨 The Forge has been reset! A new canvas awaits."
4. Update sidebar PXL balance (unchanged)

### 19.10 Accessibility

- All interactive elements have `aria-label` attributes
- Color palette: each swatch has `aria-label="Color #RRGGBB"`
- Game canvas: `aria-label="Pixel Dash game"` with role="application"
- Grid canvas: `aria-label="PixelForge canvas"` with role="application"
- Keyboard navigation works throughout the site
- Focus rings visible on all interactive elements (`outline: 2px solid var(--accent)`)
- Font sizes minimum 13px
- Color contrast ratios meet WCAG AA

### 19.11 Performance Targets

- Time to First Byte: < 200ms
- Page Load (index): < 1 second
- Chunk fetch: < 100ms (Redis cache hit), < 500ms (DB miss)
- Game start API: < 200ms
- Pixel purchase API: < 300ms (including DB transaction + Redis ops)
- SSE latency: < 500ms from purchase to broadcast
- Canvas render at 4× zoom (100 chunks): < 100ms cold, < 16ms warm (60fps)

---

## IMPLEMENTATION ORDER (Recommended)

1. **Database & Migrations** — Set up MySQL, run schema exactly as specified
2. **includes/** — All helper files (bootstrap, db, redis, security, response)
3. **Auth System** — register, login, logout, email verify, session management
4. **Basic HTML Shell** — Layout, sidebar, CSS variables, fonts
5. **Canvas Grid Viewer** (read-only first) — Chunk loading, rendering, pan/zoom, SSE receive
6. **Game Engine** — PRNG, physics, obstacles, collectibles, rendering, audio
7. **Game API** — start, checkpoint, submit, anti-cheat validation
8. **PXL System** — credit/debit functions, transaction logging
9. **Pixel Purchase** — buy.php, conflict resolution, SSE broadcast
10. **Achievement System** — check/grant/claim
11. **Leaderboard** — queries, caching, pagination
12. **Profile Page** — stats, achievements, history
13. **Admin Panel** — user management, grid reset, flagged sessions
14. **Cron Scripts** — grid reset, cleanup
15. **Security Hardening** — audit all headers, rate limits, CSRF, XSS
16. **Responsive & Mobile** — CSS media queries, touch controls
17. **Performance Testing** — Load test, optimize slow queries, tune Redis TTLs

---

## CRITICAL REMINDERS FOR AGENT

1. **Every single PHP output to HTML must use `h()` / `htmlspecialchars()`**
2. **Every single SQL query must use PDO prepared statements**
3. **CSRF token must be verified on every state-changing POST endpoint**
4. **Rate limits must be checked before any business logic**
5. **Game score validation is mandatory — never trust client-submitted scores**
6. **Redis distributed lock is mandatory for pixel purchase — no exceptions**
7. **MySQL transaction wraps the entire pixel purchase operation**
8. **Session must be regenerated on login and privilege change**
9. **Passwords hashed with bcrypt cost 12 — never MD5 or SHA1**
10. **All API responses: JSON only, correct Content-Type, no PHP errors echoed**
11. **SSE endpoint: disable nginx buffering, set appropriate PHP timeouts**
12. **Chunk binary data: always exactly 12,288 bytes per chunk**
13. **Color validation: always regex validate before storing in DB**
14. **Email verification: users cannot purchase pixels without verified email**
15. **The `.env` file and `includes/` directory must never be web-accessible**

---

_End of PixelForge System Specification_
_Total: ~8,500 words of detailed technical specification_
_Version: 1.0 | Build Target: Production_
