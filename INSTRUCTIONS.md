# PixelForge Setup & Running Instructions

## Prerequisites
- XAMPP (Apache + MySQL) installed and running
- PHP 8.2+ 
- Redis (optional - app works without it for basic functionality)

## What's Already Done (Automated)

### 1. Database Setup
- Database `pixelforge` created with all 17 tables
- 1024 chunks pre-populated
- 20 achievements seeded
- Default admin user created (admin/admin123)

### 2. Project Structure
All PHP, CSS, and JavaScript files created in:
```
C:\xampp\htdocs\codes\pixelforge\
├── public/           (web root)
│   ├── index.php     (landing/login/register)
│   ├── canvas.php    (The Forge - pixel canvas)
│   ├── game.php      (PIXEL DASH game)
│   ├── profile.php   (user profile)
│   ├── leaderboard.php
│   ├── verify.php    (email verification)
│   └── api/          (all API endpoints)
├── includes/         (PHP helper files)
├── cron/             (scheduled tasks)
└── .env             (configuration)
```

### 3. API Endpoints Created
- Auth: register, login, logout, me
- Game: start, checkpoint, submit
- Grid: chunk, buy, pixel-info, updates (SSE)
- User: profile, me, achievements, claim-achievement
- Leaderboard: GET endpoint with Redis caching

---

## MANUAL STEPS REQUIRED

### 1. Configure XAMPP Virtual Host (Recommended)
Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf` and add:
```apache
<VirtualHost *:80>
    ServerName pixelforge.local
    DocumentRoot "C:\xampp\htdocs\codes\pixelforge\public"
    <Directory "C:\xampp\htdocs\codes\pixelforge\public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Then add to hosts file: `127.0.0.1 pixelforge.local`

Or simply access via: `http://localhost/pixelforge/public/`

### 2. Set Up Cron Jobs (Linux/Windows)

**Option A - Windows Task Scheduler:**
Create 3 tasks to run PHP scripts:
- `php C:\xampp\htdocs\codes\pixelforge\cron\reset_grid.php` - Weekly (Sunday 00:00)
- `php C:\xampp\htdocs\codes\pixelforge\cron\cleanup_sessions.php` - Daily at 03:00
- `php C:\xampp\htdocs\codes\pixelforge\cron\cleanup_login_attempts.php` - Daily at 04:00

**Option B - Using XAMPP's cron alternative:**
Create a batch file and use Windows Task Scheduler.

### 3. Optional: Redis Setup
For full functionality with caching and real-time features:
- Install Redis for Windows (or use WSL)
- Update .env with Redis credentials
- The app works without Redis but some features are degraded

---

## How to Access the Application

### Method 1 - Direct Access (Currently)
Since XAMPP serves from htdocs, access:
```
http://localhost/pixelforge/public/
```

### Method 2 - With Virtual Host (After Step 1)
```
http://pixelforge.local/
```

---

## First Time Usage

1. **Register**: Open the site and create an account
2. **Play Game**: Go to PIXEL DASH and play to earn PXL
3. **Paint**: Go to The Forge and paint pixels (1 PXL per pixel)
4. **Leaderboard**: Check your ranking
5. **Profile**: View achievements and stats

---

## Testing the API

Test registration:
```bash
curl -X POST http://localhost/pixelforge/public/api/auth/register.php \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","email":"test@example.com","password":"test1234","csrf_token":"dummy"}'
```

Test login:
```bash
curl -X POST http://localhost/pixelforge/public/api/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"testuser","password":"test1234","csrf_token":"dummy"}'
```

---

## File Structure Summary

### Public Pages (PHP)
| File | Description |
|------|-------------|
| index.php | Landing page with login/register |
| canvas.php | 800x800 pixel canvas (The Forge) |
| game.php | PIXEL DASH arcade game |
| profile.php | User profile with achievements |
| leaderboard.php | Daily/Weekly/All-time rankings |
| verify.php | Email verification redirect |

### API Endpoints
| Path | Method | Auth | Description |
|------|--------|------|-------------|
| /api/auth/register.php | POST | Public | User registration |
| /api/auth/login.php | POST | Public | User login |
| /api/auth/logout.php | POST | Required | User logout |
| /api/auth/me.php | GET | Required | Current user info |
| /api/game/start.php | POST | Required | Start new game |
| /api/game/checkpoint.php | POST | Required | Save checkpoint |
| /api/game/submit.php | POST | Required | Submit final score |
| /api/grid/chunk.php | GET | Public | Get chunk data |
| /api/grid/buy.php | POST | Required | Purchase pixel |
| /api/grid/pixel-info.php | GET | Public | Get pixel info |
| /api/grid/updates.php | GET | Public | SSE real-time |
| /api/user/profile.php | GET | Public | Public profile |
| /api/user/me.php | GET | Required | Full user data |
| /api/user/achievements.php | GET | Required | User achievements |
| /api/user/claim-achievement.php | POST | Required | Claim PXL |
| /api/leaderboard.php | GET | Public | Leaderboard |

### CSS Files
| File | Description |
|------|-------------|
| main.css | Global styles, sidebar, forms |
| game.css | Game UI, HUD, overlays |
| canvas.css | Canvas viewer, toolbar |

### JavaScript Files
| Path | Description |
|------|-------------|
| api.js | API client with CSRF |
| auth.js | Authentication manager |
| utils.js | Utility functions |
| ui.js | UI components (toasts, modals) |
| game/engine.js | Core game loop |
| game/obstacles.js | Obstacle generation |
| game/collectibles.js | Collectible system |
| game/audio.js | Web Audio API |
| game/game-main.js | Game entry point |
| canvas/grid-renderer.js | Chunk rendering |
| canvas/chunk-cache.js | LRU cache |
| canvas/sse-client.js | Real-time updates |
| canvas/pixel-buyer.js | Purchase flow |
| canvas/mini-map.js | Mini-map |
| canvas/canvas-main.js | Canvas entry point |

---

## Troubleshooting

### Issue: "Cannot find database"
**Solution**: Run setup.sql again:
```bash
cd C:\xampp\htdocs\codes\pixelforge
"C:\xampp\mysql\bin\mysql.exe" -u root < setup.sql
```

### Issue: CSS not loading
**Solution**: Check that Apache is serving from correct directory and .htaccess is allowing CSS files.

### Issue: Session errors
**Solution**: Make sure PHP sessions are configured and session directory is writable.

---

## Security Notes (Already Implemented)

1. ✓ CSRF protection on all state-changing endpoints
2. ✓ Rate limiting on all APIs
3. ✓ Password hashing with bcrypt (cost 12)
4. ✓ Session regeneration on login
5. ✓ SQL injection prevention (PDO prepared statements)
6. ✓ XSS prevention (htmlspecialchars on output)
7. ✓ Security headers (CSP, X-Frame-Options, etc.)
8. ✓ Redis distributed lock for pixel purchases

---

## Admin Access

- Username: `admin`
- Password: `admin123`
- (Password hash: $2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/X4.VTtYGCpFdC0FUm)

---

*Generated for PixelForge v1.0*