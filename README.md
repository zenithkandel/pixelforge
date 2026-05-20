# PixelFlap - Flappy Bird & Pixel Canvas Game

A full-stack web application featuring a Flappy Bird arcade game with in-game currency, a collaborative 100×100 live pixel canvas, player progression (XP, levels, achievements, leaderboards), and a secure admin panel.

## Stack

- **Backend**: PHP (no frameworks), MySQL
- **Frontend**: HTML, CSS, Vanilla JavaScript
- **Database**: MySQL with PDO

## Setup Instructions

### 1. Database Setup

Create a MySQL database named `pixelforge`:

```sql
CREATE DATABASE pixelforge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Configure Database Credentials

Edit `config.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pixelforge');
define('DB_USER', 'root');
define('DB_PASS', '');
```

### 3. Run Installation

Navigate to `http://localhost/pixelforge/install.php` in your browser and click "Install" to:
- Create all database tables
- Seed achievements
- Create the default admin account

### 4. Admin Credentials

- Username: `admin`
- Password: `Admin1234!`

## Features

### Flappy Bird Game
- Smooth physics with gravity and flap mechanics
- Dynamic difficulty scaling
- Score multiplier system (up to 3x)
- Power-ups (Shield, Slow-Mo, Magnet, Double Coins)
- Coin collection
- Anti-cheat protection with game tokens

### Pixel Canvas
- 100×100 grid (10,000 pixels)
- Zoom (1×–6×) and pan controls
- Real-time polling (5-second intervals)
- Pixel decay system (14-day expiry)
- Territory view overlay

### Player Progression
- XP system with level formula: `Level = floor(1 + sqrt(xp / 50))`
- 15 achievements with rewards
- Daily login streak bonuses
- Profile pages with mini-canvas

### Leaderboard
- Top Scores (All-time, This Week, Today)
- Most Pixels Owned
- Most XP

### Admin Panel
- Dashboard with stats
- Canvas management (single/multi erase, area select, reset, fill)
- User management (edit balance, change role, delete, reset streak)
- Admin action logs

## File Structure

```
/                    → Public canvas (index.php)
/game.php            → Flappy Bird game
/canvas.php          → Interactive canvas (draw)
/login.php           → Login page
/register.php        → Registration page
/logout.php          → Logout handler
/profile.php         → Public profile
/leaderboard.php     → Leaderboard

/admin/
  index.php          → Admin dashboard
  canvas.php         → Canvas management
  users.php          → User management
  logs.php           → Admin logs

/api/
  save_score.php     → Save game score
  place_pixel.php    → Place a pixel
  get_canvas.php     → Get canvas state
  get_territory.php  → Get territory data
  admin_action.php   → Admin actions

/includes/
  db.php             → PDO connection
  auth.php           → Authentication
  csrf.php           → CSRF protection
  headers.php        → Security headers
  xp.php             → XP/level helpers
  achievements.php   → Achievement system

/assets/
  css/               → Stylesheets
  js/                → JavaScript files
```

## Security Features

- PDO prepared statements (no SQL injection)
- CSRF protection on all forms
- Session security (HttpOnly, Strict, regenerate on login)
- Rate limiting on login, registration, pixel placement
- Admin role verified from DB on every request
- Input validation and sanitization

## Cron Job (Optional)

For automatic pixel decay cleanup, add a cron job:

```crontab
0 0 * * * php /path/to/your/webroot/api/get_canvas.php
```

Or rely on the on-demand cleanup in `get_canvas.php`.

## License

MIT License