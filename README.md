# PixelFlap Setup Guide

## Requirements
- PHP 7.4+
- MySQL 5.7+ or MariaDB 10.3+
- Apache with mod_rewrite enabled

## Setup

### 1. Create MySQL Database
```sql
CREATE DATABASE pixelflap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pixelflap_user'@'localhost' IDENTIFIED BY 'your_password_here';
GRANT SELECT, INSERT, UPDATE, DELETE ON pixelflap.* TO 'pixelflap_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Configure
Edit `config.php` with your database credentials:
- `DB_HOST` — usually `localhost`
- `DB_NAME` — `pixelflap`
- `DB_USER` — `pixelflap_user`
- `DB_PASS` — your chosen password
- `BASE_URL` — your site URL (e.g. `http://localhost`)
- `ADMIN_DEFAULT_PASS` — admin password (change immediately after install)

### 3. Install
Visit `/install.php` in your browser once. The script:
- Creates all database tables
- Seeds achievement definitions
- Creates default admin user (username: `admin`)
- Self-deletes after successful installation

### 4. Set Permissions
```bash
chmod 755 logs/
chown www-data:www-data logs/
```
The web server user must have write access to `logs/`.

### 5. Change Admin Password
Log in as `admin` with the password set in `config.php`. Change it immediately.

### 6. Optional: Cron for Pixel Decay
Pixel decay cleanup triggers automatically on canvas fetch. For guaranteed hourly cleanup:
```
0 * * * * curl -s http://yourdomain.com/api/get_canvas.php > /dev/null
```

## Logs

View live logs:
```bash
tail -f logs/event.log
```

Filter logs:
```bash
grep "\[SECURITY\]" logs/event.log
grep "\[ERROR\]" logs/event.log
grep "\[user:42(" logs/event.log
```

Log format: `[timestamp] [LEVEL] [CATEGORY] [user:id(name)] [ip:ip] [METHOD /uri] message | {context}`

## Security

- Logs directory is protected from public access via `.htaccess`
- Includes directory is blocked from direct access
- All passwords hashed with bcrypt
- CSRF protection on all POST requests
- Rate limiting on login, registration, and pixel placement
- Anti-cheat token system for game scores
