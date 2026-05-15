# PixelForge Node.js Edition

A communal pixel canvas + arcade game platform built with Node.js, Express, and MySQL.

![PixelForge](https://img.shields.io/badge/version-2.0.0-blue) ![Node](https://img.shields.io/badge/node-20%2B-green) ![License](https://img.shields.io/badge/license-MIT-purple)

## Features

- **PIXEL DASH** - Fast-paced arcade game with procedurally generated obstacles
- **The Forge** - 800x800 collaborative pixel canvas
- **PXL Currency** - Earn PXL by playing games, spend on canvas pixels
- **Weekly Themes** - Competitive themed pixel art events
- **Hidden Gems** - Random bonus pixels spawn hourly
- **Real-time Updates** - Server-Sent Events for live canvas collaboration
- **Achievement System** - Unlock achievements and earn bonus PXL
- **Power Hour Events** - Periodic free pixel events

## Tech Stack

- **Backend**: Node.js 20+, Express.js
- **Database**: MySQL 8.0+
- **Authentication**: JWT with refresh tokens
- **Real-time**: Server-Sent Events (SSE)
- **Frontend**: Vanilla HTML5, CSS3, JavaScript ES6 modules
- **Canvas**: HTML5 Canvas API

## Quick Start

### Prerequisites

- Node.js 20 or higher
- MySQL 8.0 or higher
- SMTP server for email verification (optional but recommended)

### Installation

1. Clone or download the project

2. Navigate to the project directory:
   ```bash
   cd pixelforge-node
   ```

3. Install dependencies:
   ```bash
   npm install
   ```

4. Copy the environment file:
   ```bash
   cp .env.example .env
   ```

5. Edit `.env` with your configuration:
   ```env
   DB_HOST=localhost
   DB_PORT=3306
   DB_USER=your_mysql_user
   DB_PASS=your_mysql_password
   DB_NAME=pixelforge
   
   SMTP_HOST=smtp.example.com
   SMTP_PORT=587
   SMTP_USER=your@email.com
   SMTP_PASS=your_password
   SMTP_FROM=noreply@example.com
   SMTP_FROM_NAME=PixelForge
   
   PORT=3000
   ```

6. Create the MySQL database:
   ```sql
   CREATE DATABASE pixelforge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

7. Start the server:
   ```bash
   npm start
   ```

8. On first run, the server will:
   - Auto-generate JWT secrets
   - Create all database tables
   - Seed achievements
   - Create admin account
   - Display admin credentials in console

## Project Structure

```
pixelforge-node/
├── public/                    # Static files served by Express
│   ├── index.html            # Homepage
│   ├── game.html             # PIXEL DASH game
│   ├── canvas.html           # The Forge canvas
│   ├── leaderboard.html      # Leaderboards
│   ├── login.html            # Login page
│   ├── register.html         # Registration page
│   ├── profile.html          # User profile
│   └── assets/
│       ├── css/             # Stylesheets
│       └── js/              # JavaScript modules
│           ├── game/        # Game engine
│           └── canvas/      # Canvas renderer
├── src/
│   ├── app.js               # Express app setup
│   ├── server.js            # Entry point
│   ├── config.js            # Configuration
│   ├── database.js          # MySQL connection pool
│   ├── migrations/
│   │   └── 001_initial.js   # Database schema
│   ├── middleware/
│   │   ├── auth.js          # JWT authentication
│   │   ├── rateLimiter.js   # Rate limiting
│   │   ├── csrf.js          # CSRF protection
│   │   └── validate.js      # Input validation
│   ├── routes/
│   │   ├── auth.js          # Auth endpoints
│   │   ├── game.js          # Game endpoints
│   │   ├── grid.js          # Canvas endpoints
│   │   ├── user.js          # User endpoints
│   │   ├── leaderboard.js   # Leaderboard endpoints
│   │   └── admin.js         # Admin endpoints
│   ├── services/
│   │   ├── sseManager.js    # SSE connections
│   │   ├── chunkService.js  # Chunk management
│   │   ├── pxlService.js    # PXL economy
│   │   ├── gameValidator.js # Anti-cheat
│   │   ├── achievementService.js
│   │   ├── emailService.js  # Email sending
│   │   └── scheduling.js    # Cron jobs
│   └── utils/
│       ├── logger.js        # Winston logger
│       └── response.js      # Response helpers
├── tasks/                    # Standalone scripts
├── package.json
├── .env.example
└── README.md
```

## API Endpoints

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login user
- `POST /api/auth/refresh` - Refresh access token
- `POST /api/auth/logout` - Logout user
- `GET /api/auth/verify-email` - Verify email address
- `POST /api/auth/forgot-password` - Request password reset
- `POST /api/auth/reset-password` - Reset password

### Game (PIXEL DASH)
- `POST /api/game/start` - Start game session
- `POST /api/game/checkpoint` - Save checkpoint
- `POST /api/game/submit` - Submit score
- `GET /api/game/stats` - Get player stats

### Canvas (The Forge)
- `GET /api/grid/chunk/:cx/:cy` - Get chunk binary data
- `POST /api/grid/buy` - Purchase a pixel
- `GET /api/grid/pixel-info/:x/:y` - Get pixel info
- `GET /api/grid/updates` - SSE endpoint for live updates
- `GET /api/grid/session` - Get current session info
- `GET /api/grid/gems` - Get gem locations
- `GET /api/grid/leaderboard/week` - Weekly leaderboard

### User
- `GET /api/user/me` - Get current user profile
- `GET /api/user/:username` - Get user profile
- `GET /api/user/transactions/history` - Transaction history
- `GET /api/user/achievements/list` - All achievements

### Leaderboard
- `GET /api/leaderboard/score` - High score leaderboard
- `GET /api/leaderboard/pxl` - PXL balance leaderboard
- `GET /api/leaderboard/weekly-pixels` - Weekly pixel leaderboard
- `GET /api/leaderboard/achievements` - Achievement leaderboard

### Admin (requires admin role)
- `GET /api/admin/users` - List users
- `PATCH /api/admin/users/:id` - Update user
- `DELETE /api/admin/users/:id` - Delete user
- `POST /api/admin/theme` - Set weekly theme
- `GET /api/admin/stats` - Platform statistics
- `POST /api/admin/reset-grid` - Reset canvas

## Security Features

- **JWT Authentication** - Short-lived access tokens (15min), refresh tokens in httpOnly cookies
- **CSRF Protection** - Double Submit Cookie pattern
- **Rate Limiting** - Per-endpoint rate limits via MySQL
- **Input Validation** - All user input sanitized and validated
- **Parameterised Queries** - SQL injection prevention
- **Password Hashing** - bcrypt with 12 rounds
- **Security Headers** - Helmet middleware with CSP, HSTS
- **Game Anti-Cheat** - HMAC verification for scores

## Scheduled Tasks

- **Weekly Reset** (Sunday 00:00 UTC) - Reset canvas, announce new theme
- **Gem Spawn** (Hourly) - Spawn 5 hidden gems
- **Cleanup** (Daily 03:00 UTC) - Clean rate limits, expired sessions
- **Power Hour** (Random) - Free pixel event

## Configuration

All configuration is done via `.env` file:

| Variable | Description | Required |
|----------|-------------|----------|
| `DB_HOST` | MySQL host | Yes |
| `DB_PORT` | MySQL port | Yes |
| `DB_USER` | MySQL user | Yes |
| `DB_PASS` | MySQL password | Yes |
| `DB_NAME` | Database name | Yes |
| `SMTP_HOST` | SMTP server | Recommended |
| `SMTP_PORT` | SMTP port | Recommended |
| `SMTP_USER` | SMTP username | Recommended |
| `SMTP_PASS` | SMTP password | Recommended |
| `PORT` | Server port | No (default: 3000) |

Secrets (JWT_SECRET, REFRESH_TOKEN_SECRET, CSRF_SECRET, GAME_HMAC_SECRET) are auto-generated on first run.

## Development

Run in development mode:
```bash
npm run dev
```

The server will automatically:
1. Check for `.env` file and create from `.env.example` if missing
2. Generate application secrets if not present
3. Create database tables if they don't exist
4. Seed initial data (achievements, admin account)
5. Start listening on configured port

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## License

MIT License - See LICENSE file for details.

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## Credits

PixelForge is inspired by r/place and similar collaborative canvas projects. The PIXEL DASH game features the Mulberry32 PRNG algorithm.