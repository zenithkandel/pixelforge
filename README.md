# PixelForge - Full Stack Implementation

A browser-based platform combining an 800×800 communal pixel canvas (The Forge) with an endless runner arcade game (PIXEL DASH) where players earn in-game currency (PXL) to paint pixels.

## Project Structure

```
pixelforge/
├── includes/           # Backend libraries
│   ├── bootstrap.php   # Initialize all includes
│   ├── config.php      # Load environment config
│   ├── db.php          # Database singleton
│   ├── redis.php       # Redis singleton
│   ├── session.php     # Session handler (Redis-backed)
│   ├── security.php    # Security headers & CSRF
│   ├── response.php    # JSON response helpers
│   ├── validate.php    # Input validation
│   ├── rate_limit.php  # Rate limiting
│   ├── auth.php        # Auth helpers
│   ├── logger.php      # Logging
│   ├── game_validator.php # Anti-cheat
│   ├── pxl.php         # PXL economy
│   └── achievement.php # Achievements
├── api/
│   ├── auth/
│   │   ├── register.php        # User registration
│   │   ├── login.php           # User login
│   │   ├── logout.php          # User logout
│   │   ├── verify.php          # Email verification
│   │   ├── forgot-password.php # Password reset request
│   │   ├── reset-password.php  # Password reset
│   │   └── me.php              # Get current user
│   ├── grid/
│   │   ├── chunk.php      # Get chunk binary data
│   │   ├── buy.php        # Purchase pixel
│   │   ├── pixel-info.php # Get pixel owner info
│   │   └── updates.php    # SSE for real-time updates
│   ├── game/
│   │   ├── start.php      # Start game session
│   │   ├── checkpoint.php # Record game checkpoint
│   │   └── submit.php     # Submit final score
│   ├── user/
│   │   ├── profile.php             # Get user profile
│   │   ├── achievements.php        # Get achievements
│   │   └── claim-achievement.php   # Claim achievement
│   └── leaderboard.php # Get leaderboard
├── assets/
│   ├── css/
│   │   └── main.css    # Main stylesheet
│   └── js/
│       ├── utils.js    # Helper functions
│       ├── ui.js       # UI utilities (toast, modal)
│       ├── api.js      # API client class
│       ├── index.js    # Login/register page
│       ├── canvas.js   # Canvas viewer
│       ├── profile.js  # Profile page
│       ├── leaderboard.js # Leaderboard page
│       ├── canvas/
│       │   ├── chunk-cache.js  # LRU cache for chunks
│       │   └── grid-renderer.js # Advanced canvas renderer
│       └── game/
│           ├── prng.js       # Seeded random number generator
│           ├── engine.js     # Game engine core
│           ├── renderer.js   # Canvas renderer
│           ├── obstacles.js  # Obstacle generation
│           ├── collectibles.js # Shard & power-up logic
│           ├── audio.js      # Web Audio API
│           ├── hud.js        # HUD rendering
│           └── game-main.js  # Game entry point
├── cron/
│   ├── reset_grid.php              # Reset canvas every 7 days
│   ├── cleanup_sessions.php        # Clean old game sessions
│   └── cleanup_login_attempts.php  # Clean old login attempts
├── public/  # Public files (images, etc)
├── logs/    # Application logs
├── index.php       # Landing page (login/register)
├── canvas.php      # The Forge (canvas viewer)
├── game.php        # PIXEL DASH game
├── profile.php     # User profile
├── leaderboard.php # Global leaderboard
├── verify.php      # Email verification redirect
├── setup_db.php    # Database initialization
├── .env            # Environment variables
├── core.md         # Project overview
└── plan.md         # Implementation plan
```

## Database Schema (12 tables)

- **grid_sessions**: Canvas sessions (current + history)
- **users**: User accounts
- **pxl_transactions**: PXL ledger (append-only)
- **pixels**: Current pixel state (800×800)
- **chunks**: Chunk version tracking (for caching)
- **game_sessions**: Game session records
- **scores**: Leaderboard data
- **pixel_history**: Permanent pixel purchase record
- **achievements**: Achievement definitions (20 total)
- **user_achievements**: User-achievement junction
- **login_attempts**: Security logging
- **admins**: Admin users

## Core Features

### Authentication (Phase 3)

- ✅ User registration with email verification
- ✅ Login with IP-based rate limiting
- ✅ Password reset via email token
- ✅ Session management (Redis-backed, 24h timeout)
- ✅ CSRF protection

### Canvas Grid (Phase 5)

- ✅ 800×800 pixel canvas
- ✅ 64×64 chunk system for efficient loading
- ✅ Binary chunk storage (RGB format)
- ✅ Redis chunk caching (300s TTL)
- ✅ Pixel purchase (1 PXL per pixel)
- ✅ Real-time SSE updates
- ✅ LRU chunk cache (max 200 chunks)
- ✅ Virtual scrolling & zoom (1×, 2×, 4×, 8×, 16×)

### Game Engine (Phase 6)

- ✅ Seeded PRNG (Mulberry32)
- ✅ 8 speed tiers with procedural generation
- ✅ Obstacle variety (ground & aerial types)
- ✅ Collectible system (5 shard types)
- ✅ Power-up system (5 types)
- ✅ Combo multiplier (up to 4×)
- ✅ 3-life system with mercy invincibility
- ✅ Anti-cheat validation (server-side score verification)
- ✅ Session HMAC authentication

### PXL Economy (Phase 7)

- ✅ Score-to-PXL conversion (200 score = 1 PXL)
- ✅ Daily first game bonus (2× PXL)
- ✅ Daily high score bonus (+5 PXL)
- ✅ Combo tier bonuses
- ✅ Login streak bonuses (up to 50 PXL at 30-day streak)
- ✅ Transaction logging (append-only)

### Achievements (Phase 8)

- ✅ 20 achievement definitions
- ✅ Categories: game, canvas, overall, streak
- ✅ PXL rewards (user must claim via UI)
- ✅ Automatic granting on milestones

### Leaderboard (Phase 9)

- ✅ Three views: Daily, Weekly, All-Time
- ✅ Caching with appropriate TTLs (60s, 300s, 600s)
- ✅ Pagination support
- ✅ User current rank display

### User Profile (Phase 10)

- ✅ User stats display
- ✅ Achievement showcase
- ✅ Login streak tracking
- ✅ Recent activity tabs

### Maintenance (Phase 11)

- ✅ Canvas reset cron (every 7 days, Sunday 00:00 UTC)
- ✅ Session cleanup cron (old sessions > 30 days)
- ✅ Login attempts cleanup cron (old attempts > 7 days)

## API Endpoints Summary

### Authentication (7 endpoints)

```
POST   /api/auth/register.php
POST   /api/auth/login.php
POST   /api/auth/logout.php
GET    /api/auth/verify.php?token=...
POST   /api/auth/forgot-password.php
POST   /api/auth/reset-password.php
GET    /api/auth/me.php
```

### Grid (4 endpoints)

```
GET    /api/grid/chunk.php?cx=X&cy=Y
POST   /api/grid/buy.php
GET    /api/grid/pixel-info.php?x=X&y=Y
GET    /api/grid/updates.php (SSE)
```

### Game (3 endpoints)

```
POST   /api/game/start.php
POST   /api/game/checkpoint.php
POST   /api/game/submit.php
```

### User (4 endpoints)

```
GET    /api/user/profile.php?username=...
GET    /api/user/achievements.php
POST   /api/user/claim-achievement.php
GET    /api/leaderboard.php?type=daily|weekly|alltime&page=1
```

## Setup Instructions

### 1. Prerequisites

- PHP 7.4+
- MySQL 5.7+
- Redis 5.0+
- Nginx (or Apache with mod_rewrite)

### 2. Configuration

```bash
# Copy and edit .env file
cp .env.example .env
# Edit with your database and Redis credentials
```

### 3. Database Setup

```bash
php setup_db.php
```

### 4. Nginx Configuration

```nginx
server {
    listen 80;
    server_name pixelforge.local;

    root /var/www/pixelforge;
    index index.php;

    # Block direct access to includes and cron
    location ~ ^/(includes|cron|logs|\.env)/ {
        deny all;
    }

    # PHP-FPM
    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        include fastcgi_params;
    }

    # SSE endpoint (disable buffering)
    location = /api/grid/updates.php {
        proxy_buffering off;
        fastcgi_pass 127.0.0.1:9000;
    }

    # Static files caching
    location ~* \.(css|js|jpg|png|gif|ico)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Security headers
    add_header X-Content-Type-Options nosniff;
    add_header X-Frame-Options DENY;
    add_header X-XSS-Protection "1; mode=block";
    add_header Referrer-Policy strict-origin-when-cross-origin;
    add_header Permissions-Policy "geolocation=(), microphone=(), camera=()";
}
```

### 5. Cron Jobs

```bash
# Reset canvas every Sunday at 00:00 UTC
0 0 * * 0 php /var/www/pixelforge/cron/reset_grid.php

# Daily maintenance (00:05 UTC)
5 0 * * * php /var/www/pixelforge/cron/cleanup_sessions.php
10 0 * * * php /var/www/pixelforge/cron/cleanup_login_attempts.php
```

### 6. Redis Setup

```bash
# Configure Redis (usually default port 6379)
redis-cli config set maxmemory 512mb
redis-cli config set maxmemory-policy allkeys-lru
```

## Game Mechanics Summary

### Speed Progression

- Tier 1: 0-300 score (base speed)
- Tier 2: 300-800 score (1.2× speed)
- Tier 3: 800-1800 score (1.4× speed, new obstacles unlock)
- Tier 4: 1800-3500 score (1.6× speed)
- Tier 5: 3500-6000 score (1.8× speed)
- Tier 6: 6000+ score (2× speed)
- Tier 7: Special tier (2.2× speed, max difficulty)

### Combo System

- Collect consecutive shards without missing
- Multipliers: 1×, 1.5×, 2×, 3×, 4× (MAX)
- Visual tier effects at each multiplier

### Power-Ups

- **SHIELD** (30%): Absorb one hit
- **MAGNET** (25%): Auto-collect shards within 120px radius
- **TIMEWARP** (20%): Slow time (40% duration)
- **SCORE SURGE** (15%): 3× score multiplier
- **EXTRA LIFE** (7%): Restore 1 life
- **PIXEL BOMB** (3%): Destroy nearby obstacles into shards

## Security Measures

- ✅ Bcrypt password hashing (cost 12)
- ✅ CSRF tokens on all state-changing requests
- ✅ Rate limiting (IP-based sliding window)
- ✅ Input validation on all endpoints
- ✅ SQL prepared statements (PDO)
- ✅ HTTPS-only headers
- ✅ Game session HMAC validation
- ✅ Server-side score plausibility checks
- ✅ Login attempt tracking with account lockout
- ✅ Email verification requirement
- ✅ Session timeout (24 hours)
- ✅ Game session timeout (2 hours)

## Performance Optimizations

- ✅ Redis session storage (eliminates file I/O)
- ✅ Binary chunk storage (12 KB per chunk vs JSON)
- ✅ Redis chunk caching (300s TTL)
- ✅ Database query optimization with indexes
- ✅ LRU chunk cache on client (max 200 chunks)
- ✅ Virtual scrolling (render visible pixels only)
- ✅ Canvas rendering optimization (image-rendering: pixelated)
- ✅ Lazy chunk loading
- ✅ Database connection pooling (PDO)

## Testing Checklist

- [ ] User registration and email verification
- [ ] Login/logout flow
- [ ] Password reset
- [ ] Canvas navigation and zoom
- [ ] Pixel purchasing and PXL deduction
- [ ] Game start, gameplay, score submission
- [ ] Leaderboard display and pagination
- [ ] Achievement earning and claiming
- [ ] Daily streak bonuses
- [ ] Rate limiting functionality
- [ ] CSRF protection
- [ ] Anti-cheat validation

## Future Enhancements

- Social features (follow users, comments on pixels)
- Trading/marketplace system
- Seasonal events with special achievements
- Mobile app (React Native)
- WebSocket for real-time collab
- Replay system for top games
- Analytics dashboard
- Admin moderation tools
- In-game shop with cosmetics

## Support & Debugging

Check logs:

```bash
tail -f logs/error.log
tail -f logs/audit.log
```

Test API endpoints:

```bash
curl -X POST http://localhost/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username_or_email":"test","password":"pass123"}'
```

## License

MIT License - See LICENSE file for details
