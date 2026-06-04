# PixelForge

**Play. Earn. Create.**

A collaborative pixel art canvas powered by a Match-3 puzzle game. Players earn gems by playing an addictive match-3 game with power-ups and boosters, then use those gems to place pixels on a shared 200x200 canvas. Real-time updates via WebSocket make pixels appear instantly.

## Features

- **Match-3 Gem Forge** — Swap and match gems, create special gems, chain combos, use boosters
- **5 Boosters** — Hammer, Shuffle, +5 Moves, Color Burst, Lightning
- **4 Special Gems** — Rocket, Bomb, Color Blast, Nova
- **Collaborative Canvas** — 200x200 pixel grid, draw with thousands of players
- **Real-Time Updates** — WebSocket-powered instant pixel updates
- **Achievement System** — 16 achievements across game, pixel, and social categories
- **XP & Leveling** — Earn XP, level up, unlock rewards
- **Leaderboard** — Compete for top scores

## Requirements

- PHP 8.0 or higher
- MySQL 8.0 or higher
- Apache with mod_rewrite (XAMPP works great)
- Composer (for WebSocket server)
- Modern web browser

## Installation

### 1. Clone or Download

Place the project in your web server's document root:
```
C:\xampp\htdocs\pixelforge\
```

### 2. Create Database

Open phpMyAdmin (http://localhost/phpmyadmin) and:
1. Create a new database named `pixelforge`
2. Import `database/schema.sql`

Or via command line:
```bash
mysql -u root -p pixelforge < database/schema.sql
```

### 3. Configure

Edit `api/config.php` with your database credentials:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pixelforge');
define('DB_USER', 'root');
define('DB_PASS', '');
define('BASE_URL', 'http://localhost/pixelforge');
```

### 4. Install WebSocket Dependencies (Optional)

For real-time pixel updates:
```bash
cd api/websocket
composer require cboden/ratchet
```

### 5. Start the WebSocket Server (Optional)

```bash
cd api/websocket
php server.php
```

Or on Windows, double-click `start.bat`.

The WebSocket server runs on port 8080.

### 6. Set Permissions

Make sure the `logs/` directory is writable:
```bash
chmod 755 logs/
```

## Usage

1. Open http://localhost/pixelforge/ in your browser
2. Register a new account (you start with 10 gems)
3. Play the Match-3 game to earn more gems
4. Go to the Canvas and place pixels to create art

## Game Guide

### Match-3 Basics
- Swap adjacent gems to create lines of 3+
- Match 4+ to create special gems
- Chain combos for multiplier bonuses
- 30 moves per game

### Special Gems
| Match Pattern | Gem | Effect |
|---------------|-----|--------|
| 4 in a row | Rocket | Clears row or column |
| 5 in a row | Bomb | Explodes 3x3 area |
| L/T shape | Color Blast | Destroys all of one color |
| 6+ in a row | Nova | Clears 5x5 area |

### Boosters
| Booster | Cost | Effect |
|---------|------|--------|
| Hammer | 50 gems | Destroy one gem |
| Shuffle | 30 gems | Rearrange board |
| +5 Moves | 40 gems | Add 5 moves |
| Color Burst | 60 gems | Clear all of one color |
| Lightning | 80 gems | Clear row + column |

## Project Structure

```
pixelforge/
├── index.html              # Landing page with login/register
├── game.html               # Match-3 game page
├── canvas.html             # Collaborative pixel canvas
├── leaderboard.html        # Player rankings
├── profile.html            # User profile
├── api/
│   ├── config.php          # Configuration
│   ├── auth.php            # Authentication API
│   ├── game.php            # Game score/booster API
│   ├── pixels.php          # Pixel placement API
│   ├── canvas.php          # Canvas state endpoint
│   └── websocket/          # WebSocket server
├── includes/
│   ├── db.php              # Database connection
│   ├── auth.php            # Auth helpers
│   └── csrf.php            # CSRF protection
├── assets/
│   ├── css/                # Stylesheets
│   └── js/                 # JavaScript modules
└── database/
    └── schema.sql          # Database schema
```

## API Endpoints

### Authentication
- `POST /api/auth.php?action=login` — Login
- `POST /api/auth.php?action=register` — Register
- `GET /api/auth.php?action=me` — Get current user
- `GET /api/auth.php?action=logout` — Logout

### Game
- `GET /api/game.php?action=start_game` — Start new game
- `POST /api/game.php?action=submit_score` — Submit score
- `GET /api/game.php?action=get_boosters` — Get booster inventory
- `POST /api/game.php?action=buy_booster` — Buy booster
- `POST /api/game.php?action=use_booster` — Use booster

### Canvas
- `POST /api/pixels.php?action=place` — Place pixel
- `GET /api/pixels.php?action=get_all` — Get all pixels
- `GET /api/canvas.php` — Get canvas state

## WebSocket Protocol

Connect to `ws://localhost:8080`:

```javascript
const ws = new WebSocket('ws://localhost:8080');

// Authenticate
ws.send(JSON.stringify({ type: 'auth', user_id: 1 }));

// Place pixel
ws.send(JSON.stringify({ type: 'place_pixel', x: 10, y: 20, color: '#ff0000' }));

// Listen for updates
ws.onmessage = (e) => {
    const data = JSON.parse(e.data);
    if (data.type === 'pixel_placed') {
        // Update canvas
    }
};
```

## Troubleshooting

**Database connection failed**
- Check `api/config.php` credentials
- Make sure MySQL is running
- Verify database `pixelforge` exists

**WebSocket not connecting**
- Check if port 8080 is available
- Make sure Ratchet is installed: `composer require cboden/ratchet`
- Check firewall settings

**Canvas not loading**
- Check browser console for errors
- Verify API endpoints are accessible
- Check `.htaccess` rules

## License

MIT License. Built with passion for pixel art.

---

Built with HTML, CSS, JavaScript, PHP, MySQL, and Ratchet WebSocket.
