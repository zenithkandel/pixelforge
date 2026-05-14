# PixelForge - Complete Feature Summary

## Overview
PixelForge is a web-based game platform with two main components:
1. **The Forge** - A collaborative 800x800 pixel canvas where users can paint pixels for PXL currency
2. **PIXEL DASH** - An endless runner arcade game where users earn PXL by collecting shards and surviving

---

## Project Structure

```
pixelforge/
├── .env                          # Environment configuration
├── setup.sql                     # Database schema & seed data
├── INSTRUCTIONS.md               # Setup & running instructions
├── public/                       # Web root
│   ├── index.php                 # Landing page (login/register)
│   ├── canvas.php                # The Forge (pixel canvas)
│   ├── game.php                  # PIXEL DASH game
│   ├── profile.php               # User profile & achievements
│   ├── leaderboard.php           # Game leaderboards
│   ├── verify.php                # Email verification redirect
│   ├── api/                      # API endpoints
│   │   ├── auth/                 # Authentication APIs
│   │   ├── game/                 # Game APIs
│   │   ├── grid/                 # Canvas/grid APIs
│   │   ├── user/                 # User APIs
│   │   └── leaderboard.php       # Leaderboard API
│   ├── assets/
│   │   ├── css/                  # Stylesheets
│   │   └── js/                   # JavaScript files
│   └── .htaccess                # Apache configuration
├── includes/                     # PHP includes
│   ├── config.php               # Configuration & constants
│   ├── bootstrap.php             # Request bootstrap & helpers
│   ├── logger.php               # Logging functions
│   ├── pxl.php                  # PXL economy functions
│   ├── game_validator.php       # Game anti-cheat validation
├── cron/                        # Scheduled tasks
│   ├── reset_grid.php           # Weekly grid reset
│   ├── cleanup_sessions.php     # Clean expired sessions
│   └── cleanup_login_attempts.php # Clean old login attempts
└── logs/                        # Application logs
    ├── errors.log               # Error logs
    ├── audit.log                # Audit logs
    └── game.log                 # Game logs
```

---

## Database Schema (17 Tables)

### 1. `users` - User accounts
- `id` (PK), `username`, `email`, `password_hash`, `pxl_balance`, `total_pxl_earned`, `total_pxl_spent`, `login_streak`, `last_login_date`, `email_verified`, `is_banned`, `created_at`

### 2. `sessions` - User sessions
- `id` (PK), `user_id` (FK), `session_token`, `ip_address`, `user_agent`, `created_at`, `expires_at`

### 3. `login_attempts` - Login rate limiting
- `id` (PK), `ip_address`, `username`, `attempts`, `locked_until`

### 4. `grid_sessions` - Canvas sessions
- `id` (PK), `user_id` (FK), `ip_address`, `started_at`, `last_activity`

### 5. `chunks` - Canvas chunks (32x32 pixels each)
- `id` (PK), `cx`, `cy`, `data` (JSON), `updated_at`

### 6. `pixels` - Individual pixel purchases
- `id` (PK), `user_id` (FK), `x`, `y`, `color`, `purchase_price`, `purchased_at`

### 7. `pixel_history` - Pixel change log
- `id` (PK), `pixel_id` (FK), `user_id` (FK), `old_color`, `new_color`, `changed_at`

### 8. `pxl_transactions` - PXL currency transactions
- `id` (PK), `user_id` (FK), `amount`, `type` (earned/spent), `description`, `created_at`

### 9. `achievements` - Achievement definitions
- `id` (PK), `name`, `description`, `pxl_reward`, `type`, `requirement`, `icon`

### 10. `user_achievements` - User earned achievements
- `id` (PK), `user_id` (FK), `achievement_id` (FK), `claimed_at`

### 11. `game_sessions` - Game session records
- `id` (PK), `user_id` (FK), `prng_seed`, `started_at`, `last_checkpoint_at`, `ended_at`, `final_score`, `duration_seconds`, `pxl_earned`, `lives_at_end`, `max_speed_tier`, `checkpoints_json`, `is_valid`, `invalidation_reason`, `ip_address`

### 12. `scores` - High scores
- `id` (PK), `user_id` (FK), `game_session_id` (FK), `score`, `pxl_earned`, `duration_seconds`, `max_speed_tier`, `created_at`

### 13-17. Other tables (admins, etc.)

---

## API Endpoints

### Authentication (`/api/auth/`)
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `register.php` | POST | No | Register new user |
| `login.php` | POST | No | User login |
| `logout.php` | POST | Yes | User logout |
| `me.php` | GET | Yes | Get current user info |

### Game (`/api/game/`)
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `start.php` | POST | Yes | Start new game session |
| `checkpoint.php` | POST | Yes | Save checkpoint (anti-cheat) |
| `submit.php` | POST | Yes | Submit final score |

### Grid/Canvas (`/api/grid/`)
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `chunk.php` | GET | No | Get chunk data (32x32 pixels) |
| `buy.php` | POST | Yes | Purchase pixel (1 PXL) |
| `pixel-info.php` | GET | No | Get pixel ownership info |
| `updates.php` | GET | No | SSE real-time updates |

### User (`/api/user/`)
| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `profile.php` | GET | No | Public user profile |
| `me.php` | GET | Yes | Full user data |
| `achievements.php` | GET | Yes | User achievements |
| `claim-achievement.php` | POST | Yes | Claim PXL reward |

### Leaderboard (`/api/leaderboard.php`)
| Parameter | Description |
|-----------|-------------|
| GET | Returns daily/weekly/alltime leaderboard |
| `type` | daily, weekly, or alltime |
| `limit` | Number of results (default 20) |
| `page` | Page number |

---

## PHP Files

### Core Includes (`/includes/`)

#### `config.php`
- Loads `.env` file
- Defines constants: APP_ENV, APP_SECRET, GAME_HMAC_KEY, DB_*, REDIS_*, SMTP_*, ADMIN_*, GRID_*

#### `bootstrap.php`
- Session management with secure config
- Security headers (CSP, X-Frame-Options, etc.)
- Rate limiting functions (`check_rate_limit`)
- JSON body parsing (`get_json_body`)
- Authentication helpers (`is_authenticated`, `require_auth`)
- Response helpers (`respond_success`, `respond_error`)
- Database connection (`get_db`)
- Redis connection (`get_redis`)

#### `logger.php`
- `log_audit()` - Audit logging
- `log_security()` - Security event logging
- `log_error()` - Error logging
- `get_client_ip()` - Get client IP address

#### `pxl.php`
- `pxl_earn()` - Add PXL to user balance with transaction logging
- `pxl_spend()` - Deduct PXL from balance
- `pxl_transfer()` - Transfer PXL between users

#### `game_validator.php`
- `start_game_session()` - Create game session with seed
- `verify_game_hmac()` - Verify game end HMAC
- `verify_checkpoint_hmac()` - Verify checkpoint HMAC
- `derive_client_key()` - Derive HMAC key from session
- `validate_score_plausibility()` - Check score validity

### Pages (`/public/`)

#### `index.php` - Landing Page
- Login form with username/password
- Registration form with username/email/password
- CSRF token validation
- AJAX authentication

#### `canvas.php` - The Forge
- 800x800 pixel canvas (25x25 chunks of 32x32)
- View pixel info on click
- Buy pixels (1 PXL each)
- Real-time SSE updates
- Mini-map

#### `game.php` - PIXEL DASH
- Game lobby with stats (best score, PXL balance, games played)
- Tutorial modal with game guide
- Canvas-based game rendering
- HUD with lives, score, combo, PXL
- Pause/Resume/Quit controls

#### `profile.php` - User Profile
- User stats (join date, total PXL earned/spent, login streak)
- Achievement display with claimable rewards
- Purchase history
- Edit profile (future)

#### `leaderboard.php` - Leaderboards
- Tab navigation (Daily/Weekly/All-Time)
- Top players with ranks
- User's own rank highlighted
- Pagination

#### `verify.php` - Email Verification
- Redirect page for email verification links

---

## JavaScript Files

### Game (`/assets/js/game/`)

#### `game-main.js`
- Game state management (lobby, playing, paused, gameover)
- Start game, quit to lobby
- HUD updates
- Leaderboard fetching
- Tutorial modal handling
- Audio toggle

#### `engine.js` - Core Game Engine
- Canvas rendering (300x400 game area)
- Player physics (gravity, velocity, jumping, sliding)
- Game loop with delta time
- Checkpoint system (every 10 seconds)
- Score submission
- HMAC computation for anti-cheat

#### `obstacles.js` - Obstacle System
- Ground obstacles: glitch_block, double_stack, spike, crawl_barrier, triple_stack, combo_block
- Aerial obstacles: beam, high_beam, double_beam
- Special obstacles: glitch_zone, quantum_block, data_storm
- Spawn logic based on speed tier

#### `collectibles.js` - Collectible System
- Shards: gray (1), red (5), blue (5), green (10), rainbow (50)
- Power cells with powerups
- Chain spawning
- Collision detection

#### `audio.js` - Audio System
- Web Audio API for sound effects
- Jump, collect, hit, game over sounds

### Canvas (`/assets/js/canvas/`)

#### `canvas-main.js`
- Canvas initialization
- Mouse/touch event handling
- Pixel purchase flow

#### `grid-renderer.js`
- Chunk-based rendering
- Pixel drawing with color picker

#### `chunk-cache.js`
- LRU cache for chunks
- Fetch and cache management

#### `sse-client.js`
- Server-Sent Events for real-time updates
- Connection management

#### `pixel-buyer.js`
- Pixel purchase modal
- Price calculation
- API call to buy.php

#### `mini-map.js`
- Mini-map rendering
- Click to navigate

### Core (`/assets/js/`)

#### `api.js`
- `apiFetch()` - Centralized API caller
- CSRF token handling
- Error handling

#### `auth.js`
- Authentication state management
- Login/logout/register helpers
- Session persistence

#### `utils.js`
- Utilities (formatting, validation)
- Constants

#### `ui.js`
- Toast notifications
- Modal handling
- Loading states

---

## CSS Files

### `main.css` - Global Styles
- CSS variables for theming
- Global reset and typography
- App shell layout (sidebar + main content)
- Forms, buttons, inputs
- Toast notifications
- Modals

### `game.css` - Game Styles
- Lobby layout
- Game canvas wrapper
- HUD (heads-up display)
- Pause overlay
- Game over overlay
- Leaderboard preview
- Tutorial modal

### `canvas.css` - Canvas Styles
- Canvas viewer container
- Toolbar (color picker, tools)
- Pixel info modal
- Mini-map
- Zoom controls

---

## Game Mechanics

### PIXEL DASH

#### Controls
- **Jump**: Space / W / Arrow Up
- **Double Jump**: Space again while airborne
- **Slide**: S / Arrow Down
- **Pause**: P / Escape

#### Scoring System
- Each shard adds to score
- Score tiers: 0-100 (tier 1), 100-250 (tier 2), 250-500 (tier 3)
- Higher tier = faster speed = more points
- Combo multiplier increases with consecutive collections
- PXL Earned = Score ÷ 10 (minimum 1 PXL)

#### Lives System
- Start with 3 lives
- Lose 1 life on obstacle collision
- Collect "Extra Life" powerup for +1 life
- Game over when all lives lost

#### Checkpoint System
- Auto-save checkpoint every 10 seconds
- HMAC-signed for anti-cheat
- Allows resuming after disconnect

#### Anti-Cheat
- Server-issued PRNG seed
- HMAC signature on checkpoints and final score
- Plausibility checks (max score per second)

### The Forge (Canvas)

#### Pixel Purchase
- Cost: 1 PXL per pixel
- Redis distributed lock prevents race conditions
- MySQL transaction ensures consistency
- Purchase recorded in `pixels` and `pxl_transactions`

#### Grid Reset
- Weekly reset (configurable via GRID_RESET_DAY)
- All pixels cleared, users get refunds
- New grid ready for painting

---

## Cron Jobs

### `reset_grid.php`
- Executed weekly (Sunday midnight)
- Clears all pixel purchases
- Refunds PXL to users
- Resets chunks to empty

### `cleanup_sessions.php`
- Runs daily at 3 AM
- Removes expired sessions
- Cleans up old grid sessions

### `cleanup_login_attempts.php`
- Runs daily at 4 AM
- Removes old login attempt records
- Unlocks locked accounts

---

## Security Features

1. **CSRF Protection** - All state-changing endpoints validate CSRF token
2. **Rate Limiting** - Per-IP, per-user rate limits on sensitive endpoints
3. **Password Hashing** - bcrypt with cost 12
4. **Session Security** - Regenerate on login, secure flags
5. **SQL Injection** - PDO prepared statements
6. **XSS Prevention** - Output encoding with `h()` function
7. **Security Headers** - CSP, X-Frame-Options, X-Content-Type-Options
8. **Anti-Cheat** - HMAC signatures, server-side validation
9. **Distributed Lock** - Redis lock for pixel purchases
10. **Transactions** - MySQL transactions for PXL operations

---

## Redis Features

- **Session caching** - Faster session lookups
- **Game session tracking** - Active game validation
- **Leaderboard caching** - 60-600 second cache
- **Rate limiting** - Distributed rate limit counters

---

## Achievements (20 seeded)

| Name | Type | Requirement | PXL Reward |
|------|------|-------------|-------------|
| First Steps | game | Play first game | 5 |
| Speed Demon | game | Reach speed tier 3 | 10 |
| Collector | game | Collect 100 shards | 15 |
| Survivor | game | Play 10 games | 20 |
| High Scorer | game | Score 500+ | 25 |
| Combo Master | game | Get 10x combo | 30 |
| Pixel Pioneer | canvas | Buy first pixel | 5 |
| Artist | canvas | Buy 100 pixels | 50 |
| Collection | canvas | Own 500 pixels | 100 |
| Daily Login | login | 7 day streak | 10 |
| Weekly Warrior | login | 30 day streak | 50 |
| Verified | email | Verify email | 5 |

---

## Dependencies

- **PHP 8.0+** with PDO MySQL
- **MySQL 8.0+**
- **Redis** (optional - falls back gracefully)
- **Apache** with mod_headers

---

## Environment Variables (.env)

```
APP_ENV=development
APP_SECRET=<64-char-secret>
GAME_HMAC_KEY=<64-char-key>
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=pixelforge
DB_USER=root
DB_PASS=
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASS=
GRID_RESET_DAY=0 (Sunday)
GRID_PIXEL_COST=1
```

---

## Version History

- **v1.0** - Initial release
  - User authentication (register, login, logout)
  - PIXEL DASH endless runner game
  - The Forge collaborative canvas
  - PXL economy system
  - Achievements
  - Leaderboards
  - User profiles
  - Cron jobs for maintenance
  - Full API with anti-cheat
  - Tutorial system

---

*Last Updated: May 2026*