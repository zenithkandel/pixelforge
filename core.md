# Full Agent Prompt — Flappy Bird Pixel Canvas Game

**Stack: PHP (no frameworks), HTML, CSS, Vanilla JS, MySQL**

---

## PROJECT OVERVIEW

Build a full web application with four interconnected systems:

1. A Flappy Bird arcade game that earns in-game currency
2. A collaborative 100×100 live pixel canvas
3. A player progression system (XP, levels, achievements, leaderboards, profiles)
4. A secure admin panel

---

## FILE & FOLDER STRUCTURE

```
/index.php                  → Public canvas view (read-only, no login required)
/game.php                   → Flappy Bird game (login required)
/canvas.php                 → Interactive canvas (login required to draw)
/login.php                  → Login page
/register.php               → Registration page
/logout.php                 → Logout handler
/profile.php                → Public profile page (/profile.php?user=username)
/leaderboard.php            → Leaderboard page (public)

/admin/
  index.php                 → Admin dashboard
  canvas.php                → Canvas management
  users.php                 → User management
  logs.php                  → Admin action logs

/api/
  save_score.php            → Validate and save Flappy Bird score
  place_pixel.php           → Place a pixel on the canvas
  get_canvas.php            → Return all placed pixels (JSON)
  get_canvas_snapshot.php   → Return canvas state at a given time
  get_territory.php         → Return pixel ownership map (JSON)
  admin_action.php          → Handle admin canvas/user actions

/includes/
  db.php                    → PDO connection singleton
  auth.php                  → Session/auth helpers
  csrf.php                  → CSRF token generation & validation
  xp.php                    → XP/level calculation helpers
  achievements.php          → Achievement check & award logic

/assets/
  css/
    style.css               → Global dark theme styles
    game.css
    canvas.css
    admin.css
  js/
    game.js                 → Flappy Bird game engine
    canvas.js               → Canvas render, interaction, polling
    territory.js            → Territory overlay logic
    achievements.js         → Toast notification system
```

---

## DATABASE SCHEMA

### `users`

```sql
CREATE TABLE users (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(30) NOT NULL UNIQUE,
  email           VARCHAR(100) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  balance         INT NOT NULL DEFAULT 0,
  xp              INT NOT NULL DEFAULT 0,
  level           INT NOT NULL DEFAULT 1,
  role            ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  streak_days     INT NOT NULL DEFAULT 0,
  last_login_date DATE DEFAULT NULL,
  avatar_color    VARCHAR(7) NOT NULL DEFAULT '#7c3aed',
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### `pixels`

```sql
CREATE TABLE pixels (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  x           INT NOT NULL,
  y           INT NOT NULL,
  color       VARCHAR(7) NOT NULL,
  owner_id    INT DEFAULT NULL,
  placed_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at  TIMESTAMP DEFAULT NULL,
  UNIQUE KEY uq_pixel (x, y),
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
);
```

- `expires_at` = placed_at + 14 days. A cron job (or check on canvas load) marks pixels as reclaimable when `NOW() > expires_at`.
- Pixels with `owner_id = NULL` are unclaimed (default white).
- Dimming state (7–14 day warning) is computed in real-time from `placed_at`, not stored.

### `score_log`

```sql
CREATE TABLE score_log (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  score           INT NOT NULL,
  multiplier      DECIMAL(3,1) NOT NULL DEFAULT 1.0,
  currency_earned INT NOT NULL,
  xp_earned       INT NOT NULL,
  played_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### `game_tokens`

```sql
CREATE TABLE game_tokens (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  token      VARCHAR(64) NOT NULL UNIQUE,
  used       TINYINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### `achievements`

```sql
CREATE TABLE achievements (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(50) NOT NULL UNIQUE,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  icon        VARCHAR(50) NOT NULL,
  reward      INT NOT NULL DEFAULT 0
);
```

### `user_achievements`

```sql
CREATE TABLE user_achievements (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  achievement_id INT NOT NULL,
  earned_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_achievement (user_id, achievement_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
);
```

### `login_attempts`

```sql
CREATE TABLE login_attempts (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### `pixel_placements`

```sql
CREATE TABLE pixel_placements (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  placed_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

Used for rate limiting (max 10 placements per user per minute).

### `canvas_snapshots`

```sql
CREATE TABLE canvas_snapshots (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  snapshot     LONGTEXT NOT NULL,
  captured_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

Store hourly JSON snapshots of the canvas for the profile mini-canvas and history features.

### `admin_log`

```sql
CREATE TABLE admin_log (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  admin_id    INT NOT NULL,
  action      VARCHAR(100) NOT NULL,
  target_type VARCHAR(50) DEFAULT NULL,
  target_id   INT DEFAULT NULL,
  details     TEXT DEFAULT NULL,
  performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## AUTHENTICATION SYSTEM

### Registration (`/register.php`)

- Fields: `username` (3–30 chars, alphanumeric + underscore only), `email` (valid format), `password` (min 8 chars, must include at least one number and one letter), `confirm password`.
- On success: hash with `password_hash($pass, PASSWORD_BCRYPT)`. Start with `balance = 0`, `xp = 0`, `level = 1`. Assign a random `avatar_color` from a preset palette of 10 colors.
- Show inline validation errors per field (not a full-page refresh).

### Login (`/login.php`)

- Login with username OR email + password.
- Rate limit: max 5 failed attempts per IP in 15 minutes (check `login_attempts` table, delete records older than 15 min on each check). Show countdown timer if locked.
- On success: `session_regenerate_id(true)`. Store `$_SESSION['user_id']`, `$_SESSION['role']`, `$_SESSION['username']`.
- Update `last_login_date`. If it's a new calendar day vs yesterday, increment `streak_days`. If gap > 1 day, reset `streak_days = 1`. Award daily streak bonus currency:
  - Day 1: +10, Day 2: +20, Day 3: +35, Day 5: +60, Day 7: +150, Day 14: +400, Day 30+: +800
  - Award streak bonus silently on login; surface it as a toast on the next page load.

### Session Security

- `session_start()` on every PHP page.
- `session_regenerate_id(true)` on login and privilege escalation.
- All protected pages check `$_SESSION['user_id']` on every request — not just on first load.
- Admin pages additionally re-query `role` from the DB on every request.
- On logout: `session_unset()`, `session_destroy()`, redirect to `/index.php`.

---

## XP & LEVEL SYSTEM

### XP Sources

| Action                    | XP Earned     |
| ------------------------- | ------------- |
| Complete a game           | score × 1 XP  |
| Place a pixel (unclaimed) | 5 XP          |
| Repaint own pixel         | 1 XP          |
| Earn an achievement       | 20 XP         |
| Daily login streak bonus  | 10 XP per day |

### Level Formula

```
Level = floor(1 + sqrt(xp / 50))
```

- Level 1: 0 XP
- Level 2: 50 XP
- Level 5: 800 XP
- Level 10: 4050 XP

### Level Perks (cosmetic only, no pay-to-win)

| Level | Perk                                    |
| ----- | --------------------------------------- |
| 3     | Unlock 5 extra bird skin colors         |
| 5     | Unlock animated pixel placement sparkle |
| 10    | Gold username badge on canvas tooltip   |
| 20    | Custom avatar border on profile page    |
| 50    | Legend badge — permanent canvas tooltip |

Show a level-up toast whenever the level increases. Include a subtle animated XP progress bar in the navbar.

---

## ACHIEVEMENTS SYSTEM

Seed the `achievements` table with at least the following on first install:

| Slug           | Name                | Trigger                            | Reward |
| -------------- | ------------------- | ---------------------------------- | ------ |
| first_flight   | First Flight        | Complete first game                | 10     |
| score_10       | Getting Somewhere   | Score 10 in one run                | 20     |
| score_50       | Flap Master         | Score 50 in one run                | 75     |
| score_100      | Sky Ruler           | Score 100 in one run               | 200    |
| first_pixel    | Mark Your Territory | Place first pixel                  | 15     |
| pixel_5        | Growing Empire      | Own 5 pixels                       | 30     |
| pixel_25       | Canvas Veteran      | Own 25 pixels                      | 100    |
| pixel_100      | Canvas Legend       | Own 100 pixels                     | 500    |
| streak_3       | On a Roll           | 3-day login streak                 | 50     |
| streak_7       | Dedicated           | 7-day login streak                 | 150    |
| streak_30      | Obsessed            | 30-day login streak                | 1000   |
| level_5        | Rising Star         | Reach level 5                      | 50     |
| level_10       | Veteran             | Reach level 10                     | 150    |
| level_20       | Elite               | Reach level 20                     | 500    |
| multiplier_3x  | Combo King          | Reach 3x multiplier in one run     | 100    |
| broke_the_bank | Broke the Bank      | Spend 500 currency total on pixels | 200    |

### Achievement Check Flow

- After every game saved: check score achievements, streak achievements, level achievements.
- After every pixel placed: check pixel-count achievements.
- Achievement checks run server-side in `includes/achievements.php`.
- Any newly earned achievements are returned in the API response JSON as `"new_achievements": [{slug, name, icon, reward}]`.
- The front-end reads this array and fires a toast for each one (sequential, 3s apart).

### Achievement Toast UI

- Appears bottom-right, slides in from right.
- Shows achievement icon, name, description, and `+{reward} currency` in gold.
- Auto-dismisses after 4 seconds. Stackable (queue multiple).
- Non-blocking — doesn't interrupt gameplay.

---

## FLAPPY BIRD GAME (`/game.php` + `assets/js/game.js`)

### Game Engine (HTML5 Canvas)

- Render on a `<canvas>` element, 480×640px.
- Bird: smooth gravity + flap physics. Click, tap, or spacebar to flap.
- Pipes: scroll left. Gap between top/bottom pipe = 150px at start.
- Score: increments by 1 each time bird clears a pipe pair.

### Dynamic Difficulty Curve

Every 15 pipes cleared, trigger a difficulty tier increase:
| Tier | Pipe speed | Gap size | Notes |
|------|-----------|----------|---------------------------------|
| 1 | 2.5 px/f | 150px | Beginner |
| 2 | 3.0 px/f | 140px | Slightly faster |
| 3 | 3.5 px/f | 130px | Noticeably harder |
| 4 | 4.0 px/f | 120px | Expert territory |
| 5+ | 4.5 px/f | 115px | Capped — never gets easier |

Show a brief on-screen flash ("Speed up!") when difficulty increases. Never reduce difficulty once increased.

### Score Multiplier System

- Every 10 pipes passed without dying increases the multiplier:
  - Pipes 1–9: ×1.0
  - Pipes 10–19: ×1.5
  - Pipes 20–29: ×2.0
  - Pipes 30–39: ×2.5
  - Pipes 40+: ×3.0
- Display current multiplier prominently in-game (top-right, large text, gold color when above ×1.0).
- Flash the multiplier badge when it increases.
- `Currency earned = score × multiplier × 2` (base: 1 point = 2 currency).
- The multiplier value is sent to the server with the score. Server independently recalculates and validates it from the score — if they don't match, reject the submission.

### Mid-Game Power-Ups

Power-ups spawn as collectible glowing orbs between pipe pairs. Spawn rate: one power-up every 8–12 pipes (random). Only one active power-up at a time.

| Power-up | Icon color | Effect                                              | Duration   |
| -------- | ---------- | --------------------------------------------------- | ---------- |
| Shield   | Blue       | Survive the next pipe collision without dying       | One-time   |
| Slow-Mo  | Purple     | Pipes slow to 50% speed                             | 5 seconds  |
| Magnet   | Gold       | Automatically collects nearby coin orbs (see below) | 8 seconds  |
| ×2 Coin  | Green      | All coins worth double for the duration             | 10 seconds |

Additionally, small **coin orbs** (worth 5 currency each) spawn randomly in the flight path at a rate of 2–4 per pipe gap. These are separate from power-ups. The Magnet power-up auto-collects any coin within 120px radius.

Coin collection is tracked server-side: include `coins_collected` in the score payload.

### Anti-Cheat

1. When `game.php` loads, generate a `game_token` (64 chars, `bin2hex(random_bytes(32))`). Store in `$_SESSION['game_token']` and also insert into `game_tokens` table with `used = 0`.
2. Embed the token in a hidden `<input>` in the page.
3. On game over, JS sends a POST to `/api/save_score.php` with: `game_token`, `score` (int), `multiplier` (float, 1 step per 10 pipes), `coins_collected` (int, max 50 per session), CSRF token.
4. Server validates:
   - CSRF token matches session
   - `game_token` exists in DB, belongs to this user, `used = 0`
   - `score` is int ≥ 0 and ≤ 500 (hard cap)
   - `multiplier` matches what the score would produce (score ÷ 10 = tier, cap at ×3.0)
   - `coins_collected` ≤ 50
   - Token `created_at` is within the last 10 minutes
5. If valid: mark token `used = 1`, update balance, save to `score_log`.
6. Rate limit: max 1 unresolved token per user at a time. Generating a new game destroys the previous token.

### Game Over Screen (overlay, not new page)

Show over the game canvas:

- Final score (large, animated count-up)
- Multiplier used
- Currency earned (animated count-up, gold)
- Coins collected (if any)
- XP earned
- Personal best (if beaten, show confetti animation)
- "Play Again" button (generates new token, resets game)
- "Go to Canvas" button
- Achievement toasts fire after 500ms delay (so they don't overlap the score reveal)

### In-Game HUD

- Top-left: current score (white, large)
- Top-right: multiplier badge (hidden at ×1.0, shows gold when above)
- Active power-up shown with timer bar (bottom-center)
- Pause button (top-right corner, ESC also works)

### Leaderboard (below game canvas)

Three tabs: **All-time** | **This week** | **Today**
Columns: rank, avatar, username, level badge, score, currency earned, date.
Your own row is always highlighted even if outside top 10.
Limit: top 10 shown. "Your rank: #42" shown below if not in top 10.

---

## LIVE PIXEL CANVAS

### Canvas Rendering (`canvas.php`, `index.php`, `assets/js/canvas.js`)

- Grid: 100×100 pixels. Each cell = 8×8 browser pixels = 800×800px base size.
- Render using HTML5 Canvas element.
- **Zoom**: scroll wheel, range 1×–6×. Zoom is centered on cursor position.
- **Pan**: click-and-drag (cursor changes to grab/grabbing). Panning is clamped so canvas never fully leaves viewport.
- Unclaimed pixels: `#1a1a1a` (near-black, dark theme) with subtle `#2a2a2a` gridlines every 10 cells.
- Claimed pixels: show their stored hex color.
- **Pixel decay**: pixels with `placed_at` between 7–14 days ago are rendered at 70% opacity (dimmed). Past 14 days, they revert to unclaimed.

### Real-Time Polling

- Poll `/api/get_canvas.php` every 5 seconds.
- API returns JSON: `{pixels: [{x, y, color, owner_id, username, placed_at, expires_at}], timestamp}`.
- Front-end diffs against local state: only re-draw changed cells.
- Show a subtle "live" pulse indicator top-right of canvas (green dot, breathing animation).

### Placing a Pixel (logged-in users on `canvas.php` only)

On click (not drag):

1. Identify the grid cell from canvas coordinates (account for zoom/pan offset).
2. Show a floating **Pixel Panel** (fixed to right of canvas on desktop, below on mobile):
   - Coordinates: `(x, y)`
   - Owner: "Unclaimed" or username + level badge + placed_at time
   - Decay status: if owned by you and expiring in < 3 days, show warning
   - Color picker: native `<input type="color">` + hex input field (synced)
   - Cost display: "5 currency" (or "Free — your pixel" if already owned by you)
   - Your balance: shown with live update
   - "Place Pixel" button (disabled if insufficient balance or pixel owned by another)
   - Error messages inline, never `alert()`

3. POST to `/api/place_pixel.php`: `{x, y, color, csrf_token}`.
4. Server validates:
   - Session valid
   - CSRF token valid
   - `x`, `y` are ints between 0–99
   - `color` matches regex `^#[0-9A-Fa-f]{6}$`
   - Pixel is unclaimed OR owned by this user (re-painting own pixel: free)
   - User has ≥ 5 balance (if unclaimed pixel)
   - Rate limit: ≤ 10 placements per user per 60 seconds (check `pixel_placements` table)
   - Race condition: use `INSERT ... ON DUPLICATE KEY UPDATE` guarded by owner check; return error if a different user claimed it between client check and insert
5. On success: deduct 5 currency (if unclaimed), update pixel, award 5 XP, record in `pixel_placements`, check achievements, return `{success: true, new_balance, new_xp, new_level, new_achievements}`.
6. Immediately update the local canvas state without waiting for next poll.

### Hover Tooltip

- On mouseover (desktop) / long-press (mobile), show a tooltip near cursor:
  - If unclaimed: "Unclaimed — 5 currency to claim"
  - If owned by you: "Your pixel — free to repaint (X days left)"
  - If owned by another: "Owned by [username] · Level [N] · [X days ago]"
- Decay warning: if expires in < 3 days: "⚠ Expires in X days"

### Territory Overlay (`assets/js/territory.js`)

A toggle button ("Territory view" / "Art view") switches between two render modes:

- **Art view**: pixels shown in their actual placed color (default).
- **Territory view**: each pixel is colored by the owner's `avatar_color`. Unclaimed pixels are dark gray. This makes ownership patterns immediately visible across the whole canvas.
  In territory view, a small legend appears showing the top 5 pixel owners with their color swatch and username.

### Canvas Full State

When all 10,000 pixels are claimed and none have decayed:

- Show a banner: "Canvas is full — pixels will open up as others expire. Check back soon!"
- Disable the "Place Pixel" button.
- Still show hover tooltips and territory overlay.

### Public View (`index.php`)

- Same canvas render and polling, no click-to-place.
- Show a sticky banner: "Log in to draw on the canvas →"
- Territory overlay toggle still works.
- No pixel panel.

---

## PIXEL DECAY SYSTEM

- When a pixel is placed, set `expires_at = placed_at + INTERVAL 14 DAY`.
- **Repainting** (owner updates own pixel): reset `placed_at = NOW()` and `expires_at = NOW() + 14 DAYS`.
- The canvas polling API filters out expired pixels from the response (treats them as unclaimed).
- A daily cleanup: in `/api/get_canvas.php`, delete rows where `expires_at < NOW()` as a side effect (or via a separate cron job).
- Visual decay stages:
  - 0–7 days: full opacity
  - 7–14 days: 70% opacity + subtle shimmer CSS animation
  - 14+ days: removed from DB, shown as unclaimed
- If the user re-paints before expiry, the decay resets fully.

---

## LEADERBOARD (`/leaderboard.php`)

Three independent leaderboards, displayed as tabs:

### Tab 1: Top Scores

Columns: Rank, Player (avatar + username + level), Score, Multiplier Used, Currency Earned, Date
Filters: All-time / This Week / Today

### Tab 2: Most Pixels Owned

Columns: Rank, Player, Pixels Owned, Pixels Placed All-Time, Joined Date
Query: `SELECT owner_id, COUNT(*) as pixel_count FROM pixels WHERE owner_id IS NOT NULL GROUP BY owner_id ORDER BY pixel_count DESC`

### Tab 3: Most XP

Columns: Rank, Player, Level, Total XP, Achievements Count
Shows overall progression leaders.

- All tabs show top 50.
- Logged-in user's own row is highlighted with a subtle border accent, regardless of position.
- Clicking a username links to their public profile.

---

## PUBLIC PROFILE PAGE (`/profile.php?user=username`)

Layout (single-column, centered, max-width 700px):

### Header

- Large avatar circle (letter initial + `avatar_color` background)
- Username (large) + level badge (e.g. "Level 12")
- Achievement badges (grid of earned icons, grayed out if locked)
- Stats row: Total Score (all-time best), Pixels Owned, XP, Member Since

### Mini Canvas

- 200×200px canvas showing ONLY this user's owned pixels highlighted in their `avatar_color`.
- All other pixels are dark/transparent.
- Shows their territory at a glance.

### Achievement Showcase

- Grid of all achievement slots (earned = colored, unearned = gray + locked icon).
- Hover tooltip shows achievement name, description, and earned date (or "Locked").

### Recent Activity

- Last 10 score submissions: date, score, multiplier, currency earned.
- Last 10 pixels placed: coordinate, color swatch, date.

### Privacy

- Profile is public — anyone can view it (no login required).
- Email is never shown.

---

## ADMIN PANEL (`/admin/`)

### Access Control

- Middleware check on every admin page: re-query `role` from DB using session `user_id`.
- If `role !== 'admin'`: return HTTP 403, show a plain error page. Do not redirect to login (leaks that `/admin/` exists).
- Admin login uses the same login system — no separate credentials.

### Admin Dashboard (`/admin/index.php`)

Overview cards:

- Total users, total pixels placed, canvas fill % (claimed / 10,000), total currency in circulation, active users today.
- Recent admin log (last 20 actions, paginated).

### Canvas Management (`/admin/canvas.php`)

- Full canvas render (same as canvas.php).
- **Single erase**: click a pixel → confirm dialog → delete from DB.
- **Multi-select mode**: toggle button activates selection mode. Click pixels to select (red outline). "Erase Selected (N)" button bulk-deletes after confirmation.
- **Area select**: click-and-drag to select a rectangular region.
- **Reset Entire Canvas**: button → modal with text field. User must type `RESET` exactly to confirm. Truncates `pixels` table. Log: `{action: 'canvas_reset', details: 'Full canvas reset'}`.
- **Fill Canvas**: choose a color → fills all UNCLAIMED pixels only (does not overwrite existing claimed pixels). Confirm required.
- Admin canvas actions do NOT deduct currency or XP (admin actions are free and bypass economy).

### User Management (`/admin/users.php`)

- Paginated table (25 per page): avatar, username, email, level, balance, pixels owned, role, streak, joined.
- Search: filter by username or email (live JS filter, no page reload).
- Per-user action row (expand on click):
  - **Edit Balance**: input (integer ≥ 0, absolute value). Saves to DB. Logs action.
  - **Add/Subtract Balance**: delta input (e.g. +100 or -50) as an alternative. Saves to DB. Logs action.
  - **Change Role**: toggle user ↔ admin. Cannot demote yourself if you are the only admin.
  - **Delete User**: confirm dialog ("This will remove the user and release their pixels"). On confirm: delete user, set `owner_id = NULL` on their pixels (keep colors, reset `expires_at = NOW()`).
  - **View Profile**: link to `/profile.php?user=username`.
  - **Pixel Count**: shown in table column, click to highlight their pixels on the admin canvas.
  - **Reset Streak**: set `streak_days = 0`.
- Guard: admin cannot delete or demote themselves.

### Admin Log (`/admin/logs.php`)

- Full paginated log of all admin actions.
- Columns: timestamp, admin username, action type, target (user/pixel), details.
- Filter by action type and date range.

---

## SECURITY REQUIREMENTS

### Database

- All queries use PDO with prepared statements and bound parameters. No string interpolation in SQL ever.
- Use a single PDO connection via `includes/db.php` (singleton pattern).
- Database user has minimal privileges: SELECT, INSERT, UPDATE, DELETE only on the app database. No DROP, CREATE, etc.

### Input/Output

- All user-supplied output escaped with `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
- JSON responses use `json_encode()` (safe by default).
- All color inputs validated server-side with regex `^#[0-9A-Fa-f]{6}$`.
- All integer inputs cast with `(int)` and range-checked server-side.

### CSRF Protection

- Generate a token on session start: `$_SESSION['csrf_token'] = bin2hex(random_bytes(32))`.
- Embed in every form as `<input type="hidden" name="csrf_token" value="...">`.
- AJAX requests send it in the POST body (`csrf_token` field).
- Every POST endpoint validates: `hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])`. Fail = 403.

### Rate Limiting

| Endpoint           | Limit                                    |
| ------------------ | ---------------------------------------- |
| Login              | 5 failed attempts per IP per 15 minutes  |
| `/api/place_pixel` | 10 placements per user per 60 seconds    |
| `/api/save_score`  | 1 submission per game token (single-use) |
| Register           | 3 registrations per IP per hour          |

### Session Security

- `session.cookie_httponly = 1`
- `session.cookie_samesite = Strict`
- `session.use_strict_mode = 1`
- Set via `ini_set()` or `php.ini`.
- Session lifetime: 2 hours of inactivity.

### HTTP Security Headers

Set on every response (in a shared `includes/headers.php` included at top of every page):

```php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
```

### Admin Extra Security

- Admin role is re-checked from DB on every admin page load (not from session).
- Admin actions are idempotent and logged before execution.
- No admin action is triggered by a GET request — all mutations are POST only.

---

## UI / UX DESIGN

### Global Theme

- Background: `#0a0a0a`
- Surface (cards, panels): `#111111`
- Border: `#222222`
- Primary accent: `#7c3aed` (purple)
- Secondary accent: `#a855f7` (lighter purple)
- Gold (currency, multiplier): `#f59e0b`
- Text primary: `#f5f5f5`
- Text secondary: `#9ca3af`
- Danger: `#ef4444`
- Success: `#22c55e`
- Font: system-ui stack (`-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif`)

### Navigation Bar (all pages)

- Left: logo + app name ("PixelFlap")
- Center (desktop): Home Canvas | Play Game | Leaderboard
- Right: if logged out: Login | Register. If logged in: XP progress bar (mini, 120px wide) + Level badge + Balance (💰 gold) + Username (links to profile) + Logout.
- On mobile: hamburger menu collapses center + right items.
- Balance updates via fetch after game and pixel placement — no page reload.

### Micro-interactions & Feedback

- All buttons have hover scale (1.02) and active scale (0.97) transitions.
- Balance counter animates up/down when it changes (CSS counter animation or JS number tween).
- XP bar fills smoothly on level/xp gain.
- Level-up: full-screen brief flash + toast ("Level up! You're now level N").
- Pixel placement: brief ripple animation on the placed pixel.
- Insufficient balance: shake animation on the "Place Pixel" button + red flash.
- Game over: score counts up from 0 over 1.2 seconds.
- All error messages appear inline (red text below the relevant input/action). Never `alert()` or `confirm()` except for destructive admin actions.
- Loading states on all async actions (spinner on button, disabled state).

### Canvas UX

- Zoom level indicator shown in canvas corner (e.g. "2×").
- Minimap in bottom-right corner: 100×100 scaled-down canvas, shows current viewport rectangle. Clicking minimap pans to that area.
- Color picker panel slides in from right (desktop) or up from bottom (mobile).
- "My Pixels" button: highlights all pixels you own with a white outline overlay.

### Responsive Design

- All pages fully usable on mobile (minimum 375px width).
- Canvas on mobile: touch to select pixel (single tap), pinch-to-zoom, two-finger pan.
- Game on mobile: tap to flap.
- Admin panel: collapses to single-column on mobile with collapsible sidebar.

### Accessibility

- All interactive elements have focus styles.
- Canvas tooltip accessible via `aria-live` region.
- Color contrast ratio ≥ 4.5:1 for all text.
- Images and icons have `alt` or `aria-label`.

---

## EDGE CASES & VALIDATION

| Scenario                                         | Handling                                                                                                                             |
| ------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------ |
| Two users claim same pixel simultaneously        | DB UNIQUE KEY on (x, y) causes second insert to fail; return `{error: "Pixel just claimed by another user — refresh and try again"}` |
| User submits score with expired/used token       | Return `{error: "Invalid or expired game session. Please start a new game."}` with HTTP 400                                          |
| Canvas is 100% claimed and no pixels expiring    | Show banner; disable Place button; still allow territory view and hover                                                              |
| User tries to overwrite another's valid pixel    | Server rejects; return `{error: "This pixel is owned by [username]"}` with HTTP 403                                                  |
| Admin deletes a user who is currently logged in  | Their session becomes invalid on next request (user_id no longer in DB); redirect to login                                           |
| Admin is the only admin and tries to demote self | Block with error: "You are the only admin. Promote another user first."                                                              |
| Invalid color submitted                          | Regex validate server-side; return `{error: "Invalid color format"}` with HTTP 400                                                   |
| Score exceeds hard cap (500)                     | Return `{error: "Score rejected — exceeds maximum allowed value"}` with HTTP 400                                                     |
| Pixel decay race: expired pixel claimed same ms  | DB handles via UNIQUE KEY; whoever inserts first wins                                                                                |
| Achievement already earned, triggered again      | `INSERT IGNORE` into `user_achievements` — silently skip duplicates                                                                  |
| XP overflow / level formula edge cases           | Cap level at 100 display-wise; XP continues accumulating                                                                             |

---

## INSTALL & SETUP NOTES FOR THE AGENT

1. Provide a single `/install.php` script that:
   - Creates all tables (with `CREATE TABLE IF NOT EXISTS`)
   - Seeds the `achievements` table
   - Creates a default admin account: username `admin`, email `admin@pixelflap.local`, password `Admin1234!` (force-change on first login)
   - Deletes itself after successful run
2. Provide a `/config.php` file at root with DB credentials (host, name, user, pass) and app settings (app name, base URL).
3. All DB calls import `/includes/db.php` which reads from `/config.php`.
4. Include a `README.md` with setup steps: create DB, configure `/config.php`, run `/install.php`, set up cron for decay cleanup.

```

```
