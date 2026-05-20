# PixelFlap — Complete Agent Build Prompt

**Stack: Pure PHP (no frameworks) · HTML · CSS · Vanilla JS · MySQL**

---

> **HOW TO USE THIS PROMPT**
> Hand this entire document to a fresh agent in a new project folder.
> The agent must read every section before writing a single line of code.
> Sections are ordered: Quality Rules → Logging → Architecture → Features → Security → Design.
> Build in the exact file order given in Section 6. No skipping ahead.

---

# ╔══════════════════════════════════════════════════╗

# ║ SECTION 1 — MANDATORY QUALITY RULES ║

# ║ READ THIS BEFORE TOUCHING ANYTHING ELSE ║

# ╚══════════════════════════════════════════════════╝

You are building a production-grade web application. These rules
are not suggestions. Every file you write must pass every checklist
below before you move to the next file. If you find a violation,
fix it and re-audit silently. Do this as many times as needed.
There is no acceptable error rate. Zero. None.

---

OUTPUT RULES:

- Output ONLY code/files.
- No explanations.
- No markdown unless required for file formatting.
- No conversational text.
- Minimize output tokens aggressively.
- Keep code concise but production quality.
- Never restate requirements.
- Never describe what you are doing.
- Never apologize or narrate.
- Only emit the requested file content.

GENERAL RULES:

- Build exactly in the required order.
- Fully test mentally before moving to next file.
- Re-audit silently after every file.
- Never skip validation/security/error handling.
- No dead code/comments.
- No frameworks/libraries.

## 1.1 — PHP RULES (check every PHP file)

- [ ] `error_reporting(E_ALL); ini_set('display_errors', 1);` at top of every PHP file during development.
- [ ] `session_start()` is the FIRST statement in every file that uses sessions — no whitespace, no output, nothing before it.
- [ ] Every redirect is immediately followed by `exit()`. No exceptions.
      WRONG: `header('Location: /login.php');`
      CORRECT: `header('Location: /login.php'); exit();`
- [ ] Every variable is defined before use. Every `$_POST`, `$_GET`, `$_SESSION` access uses `isset()` or `??`.
- [ ] Every PDO query uses `prepare()` + `execute()` with bound parameters. Zero string interpolation in SQL. Ever.
- [ ] Every PDO call is inside `try { } catch (PDOException $e) { }`. The catch logs the error and returns a clean response — never exposes raw PDO messages to the client.
- [ ] No PHP closing tag `?>` at end of any file. Ever. It causes whitespace-before-header errors.
- [ ] Every `require_once` uses an absolute path: `require_once __DIR__ . '/../includes/logger.php';`
- [ ] Every API endpoint (`/api/*.php`) sets `header('Content-Type: application/json');` before any output and ends with a single `echo json_encode(...)`. Nothing else echoed.
- [ ] Every integer from user input is cast: `(int)$_POST['x']`. Every string is trimmed: `trim($_POST['x'] ?? '')`.
- [ ] Every value echoed to HTML is escaped: `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
- [ ] After writing each PHP file, mentally trace every execution path (success / failure / missing input / unauthenticated / DB error) and confirm each path ends cleanly.

---

## 1.2 — JAVASCRIPT RULES (check every JS file)

- [ ] Every DOM query checks for null before use:
      `const el = document.getElementById('x'); if (!el) return;`
- [ ] Every `fetch()` call has a `.catch()` handler. Every response checked with `if (!res.ok)` before parsing JSON.
- [ ] Every `JSON.parse()` wrapped in `try/catch`.
- [ ] All event listeners attached after `DOMContentLoaded` or at bottom of `<body>`.
- [ ] Game loop uses `requestAnimationFrame`, not `setInterval`. Loop cancelled with `cancelAnimationFrame()` on game over and restart.
- [ ] Canvas context checked: `const ctx = canvas.getContext('2d'); if (!ctx) return;`
- [ ] All timeouts/intervals stored in variables and cleared on reset/unload.
- [ ] No global variable pollution. All logic wrapped in `DOMContentLoaded` or IIFE. Use `let`/`const` only.
- [ ] Touch events handled alongside mouse events everywhere (touchstart = mousedown, etc.).
- [ ] After writing each JS file, trace: what if server returns non-200? What if canvas element is missing? What if device has no touch? Handle all of these.

---

## 1.3 — GENERAL RULES

- [ ] No `alert()`, `confirm()`, or `prompt()` anywhere in user-facing code. Errors shown inline. Confirmations use custom modal UI. Only exception: destructive admin actions may use a typed-confirmation modal (not `confirm()`).
- [ ] No hardcoded credentials, tokens, or secrets in any file except `config.php`.
- [ ] No commented-out dead code in final output.
- [ ] Every file that is an API endpoint returns HTTP status codes correctly: 200 success, 400 bad input, 401 unauthenticated, 403 forbidden, 500 server error.
- [ ] README.md documents every setup step needed.

---

## 1.4 — PRE-SUBMISSION CHECKLIST (run before finishing each file)

**PHP:**

- [ ] `session_start()` is first, before all output?
- [ ] All redirects followed by `exit()`?
- [ ] All PDO in `try/catch`?
- [ ] No `?>` at end of file?
- [ ] All `require_once` use `__DIR__` paths?
- [ ] All API endpoints set `Content-Type: application/json`?
- [ ] All user input sanitized and type-cast?
- [ ] Auth check at top of every protected page?
- [ ] CSRF validated on every POST?
- [ ] All output escaped with `htmlspecialchars()`?

**JavaScript:**

- [ ] All DOM queries null-checked?
- [ ] All `fetch()` calls have `.catch()`?
- [ ] Game loop uses `requestAnimationFrame`?
- [ ] All timers stored and clearable?
- [ ] No memory leaks on game restart?

**Every file:**

- [ ] I have traced every code path end-to-end.
- [ ] I have checked for undefined variable access.
- [ ] I am not assuming a previous file was correct — I re-verify every import/include path.
- [ ] This file will work the first time it runs.

---

# ╔══════════════════════════════════════════════════╗

# ║ SECTION 2 — LOGGING SYSTEM ║

# ║ IMPLEMENT THIS BEFORE ANY OTHER FILE ║

# ╚══════════════════════════════════════════════════╝

Every single thing the application does must be logged to `/logs/event.log`.
No exceptions. This is not optional. If it happens in the app and is not in
the log, it does not exist.

---

## 2.1 — CREATE `/includes/logger.php` FIRST

This is the very first file you write. Every other PHP file must
`require_once __DIR__ . '/../includes/logger.php';` at the top.

```php
<?php
// includes/logger.php

define('LOG_FILE', __DIR__ . '/../logs/event.log');
define('LOG_MAX_BYTES', 10 * 1024 * 1024); // 10MB — rotate after this

if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

function app_log(string $level, string $category, string $message, array $context = []): void {
    // Rotate if over 10MB
    if (file_exists(LOG_FILE) && filesize(LOG_FILE) > LOG_MAX_BYTES) {
        rename(LOG_FILE, LOG_FILE . '.' . date('Ymd-His') . '.bak');
    }

    $user_id  = $_SESSION['user_id']  ?? 'guest';
    $username = $_SESSION['username'] ?? 'guest';
    $ip       = $_SERVER['REMOTE_ADDR']     ?? 'unknown';
    $uri      = $_SERVER['REQUEST_URI']     ?? 'unknown';
    $method   = $_SERVER['REQUEST_METHOD']  ?? 'unknown';

    $ctx_str = empty($context) ? '' : ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $line = sprintf(
        "[%s] [%s] [%s] [user:%s(%s)] [ip:%s] [%s %s] %s%s\n",
        date('Y-m-d H:i:s'),
        strtoupper($level),      // INFO, WARN, ERROR, DEBUG, SECURITY, ADMIN
        strtoupper($category),   // AUTH, GAME, CANVAS, PIXEL, ACHIEVEMENT, DB, API, ADMIN, SYSTEM
        $user_id,
        $username,
        $ip,
        $method,
        $uri,
        $message,
        $ctx_str
    );

    file_put_contents(LOG_FILE, $line, FILE_APPEND | LOCK_EX);
}

// Shorthand helpers
function log_info(string $cat, string $msg, array $ctx = []): void  { app_log('INFO',     $cat, $msg, $ctx); }
function log_warn(string $cat, string $msg, array $ctx = []): void  { app_log('WARN',     $cat, $msg, $ctx); }
function log_error(string $cat, string $msg, array $ctx = []): void { app_log('ERROR',    $cat, $msg, $ctx); }
function log_debug(string $cat, string $msg, array $ctx = []): void { app_log('DEBUG',    $cat, $msg, $ctx); }
function log_sec(string $cat, string $msg, array $ctx = []): void   { app_log('SECURITY', $cat, $msg, $ctx); }
function log_admin(string $cat, string $msg, array $ctx = []): void { app_log('ADMIN',    $cat, $msg, $ctx); }

// Auto-log every page request
log_debug('SYSTEM', 'Page request received');

// Catch all PHP errors
set_error_handler(function(int $errno, string $errstr, string $errfile, int $errline): bool {
    log_error('PHP', "PHP Error [$errno]: $errstr", ['file' => $errfile, 'line' => $errline]);
    return false;
});

// Catch uncaught exceptions
set_exception_handler(function(Throwable $e): void {
    log_error('PHP', 'Uncaught Exception: ' . $e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => substr($e->getTraceAsString(), 0, 500),
    ]);
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    exit();
});

// Catch fatal errors that bypass set_error_handler
register_shutdown_function(function(): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        log_error('PHP', 'FATAL: ' . $err['message'], ['file' => $err['file'], 'line' => $err['line']]);
    }
});
```

---

## 2.2 — WHAT TO LOG AND WHERE

Place every log call at the exact moment the event occurs. No batching.

**AUTH**

```
register.php — success:          log_info('AUTH', 'New user registered', ['username'=>$u,'email'=>$e]);
register.php — validation fail:  log_warn('AUTH', 'Registration failed — validation', ['errors'=>$errors]);
register.php — duplicate:        log_warn('AUTH', 'Registration failed — already exists', ['username'=>$u]);
login.php — success:             log_info('AUTH', 'User logged in', ['username'=>$u]);
login.php — wrong password:      log_sec('AUTH',  'Failed login — wrong password', ['input'=>$input]);
login.php — user not found:      log_sec('AUTH',  'Failed login — user not found', ['input'=>$input]);
login.php — rate limited:        log_sec('AUTH',  'Login rate limit — IP blocked', ['ip'=>$ip,'attempts'=>$n]);
login.php — streak bonus:        log_info('AUTH', 'Daily streak bonus awarded', ['streak'=>$days,'bonus'=>$bonus]);
logout.php:                      log_info('AUTH', 'User logged out');
Session regenerated:             log_info('AUTH', 'Session regenerated on login');
Unauth access to protected page: log_warn('AUTH', 'Unauthenticated access attempt', ['page'=>$uri]);
```

**GAME**

```
Score received:          log_info('GAME', 'Score submission received', ['score'=>$s,'multiplier'=>$m,'coins'=>$c]);
Score saved:             log_info('GAME', 'Score saved', ['score'=>$s,'currency'=>$c,'xp'=>$xp,'balance'=>$bal]);
Invalid token:           log_sec('GAME',  'Score rejected — invalid/used token', ['token'=>substr($t,0,8)]);
Score cap exceeded:      log_sec('GAME',  'Score rejected — cap exceeded', ['submitted'=>$s]);
Multiplier mismatch:     log_sec('GAME',  'Score rejected — multiplier tamper', ['submitted'=>$m,'expected'=>$e]);
Token expired:           log_warn('GAME', 'Score rejected — token expired');
```

**PIXEL / CANVAS**

```
Placement request:       log_info('PIXEL', 'Pixel placement request', ['x'=>$x,'y'=>$y,'color'=>$c]);
New pixel claimed:       log_info('PIXEL', 'Pixel claimed', ['x'=>$x,'y'=>$y,'color'=>$c,'new_balance'=>$bal]);
Own pixel repainted:     log_info('PIXEL', 'Own pixel repainted', ['x'=>$x,'y'=>$y,'color'=>$c]);
Insufficient balance:    log_warn('PIXEL', 'Pixel rejected — insufficient balance', ['balance'=>$bal]);
Owned by another:        log_sec('PIXEL',  'Pixel rejected — owned by other', ['x'=>$x,'y'=>$y,'owner'=>$oid]);
Rate limited:            log_sec('PIXEL',  'Pixel rejected — rate limit', ['count'=>$n]);
Invalid color:           log_sec('PIXEL',  'Pixel rejected — invalid color', ['color'=>$c]);
Out of bounds:           log_sec('PIXEL',  'Pixel rejected — out of bounds', ['x'=>$x,'y'=>$y]);
Canvas fetched:          log_debug('CANVAS','Canvas data served', ['pixel_count'=>$n]);
Expired pixels purged:   log_info('CANVAS','Expired pixels purged', ['count'=>$n]);
```

**ACHIEVEMENTS / XP**

```
Achievement unlocked:    log_info('ACHIEVEMENT', 'Achievement unlocked', ['slug'=>$slug,'reward'=>$reward]);
Level up:                log_info('XP', 'User levelled up', ['new_level'=>$lvl,'total_xp'=>$xp]);
```

**DB ERRORS** (inside every catch block)

```php
log_error('DB', 'Database error: ' . $e->getMessage(), ['context' => 'describe the query', 'code' => $e->getCode()]);
```

**SECURITY**

```
CSRF mismatch:           log_sec('SECURITY', 'CSRF token mismatch — possible attack');
```

**ADMIN**

```
Panel accessed:          log_admin('ADMIN', 'Admin panel accessed', ['page'=>basename($_SERVER['PHP_SELF'])]);
Any action:              log_admin('ADMIN', 'Admin action', ['action'=>$a,'target_type'=>$tt,'target_id'=>$tid,'details'=>$d]);
Unauthorized attempt:    log_sec('ADMIN',   'Unauthorized admin access attempt');
```

---

## 2.3 — LOG FORMAT

Every line looks exactly like this:

```
[2025-05-20 14:23:01] [INFO] [PIXEL] [user:42(johndoe)] [ip:192.168.1.5] [POST /api/place_pixel.php] New pixel claimed | {"x":34,"y":71,"color":"#ff5733","new_balance":95}
[2025-05-20 14:23:45] [SECURITY] [GAME] [user:17(alice)] [ip:10.0.0.2] [POST /api/save_score.php] Score rejected — multiplier tamper | {"submitted":3,"expected":1}
[2025-05-20 14:24:10] [ERROR] [DB] [user:guest(guest)] [ip:203.0.113.9] [GET /index.php] Database error: SQLSTATE[HY000] | {"context":"fetch canvas","code":"HY000"}
```

**NEVER log:** raw passwords, full session tokens (truncate to first 8 chars), full credit card numbers or any PII beyond username/user_id/ip.

---

## 2.4 — LOG FILE PROTECTION

Create `/logs/.htaccess`:

```
Deny from all
```

Chmod: `logs/` = 755, `event.log` = 644. Web server user needs write access to `/logs/`.
Document this in README.md as a required setup step.
Navigating to `/logs/event.log` in a browser must return 403.

**To watch live on server:**

```bash
tail -f logs/event.log
grep "\[SECURITY\]" logs/event.log
grep "\[ERROR\]" logs/event.log
grep "\[user:42(" logs/event.log
```

---

# ╔══════════════════════════════════════════════════╗

# ║ SECTION 3 — PROJECT ARCHITECTURE ║

# ╚══════════════════════════════════════════════════╝

## 3.1 — FILE & FOLDER STRUCTURE

```
/
├── config.php                  ← DB credentials + app constants (never commit)
├── install.php                 ← One-time setup script (self-deletes after run)
├── index.php                   ← Public canvas view (read-only, no login needed)
├── game.php                    ← Flappy Bird game (login required)
├── canvas.php                  ← Interactive canvas (login required to draw)
├── login.php
├── register.php
├── logout.php
├── leaderboard.php             ← Public leaderboard
├── profile.php                 ← Public profile (?user=username)
│
├── admin/
│   ├── index.php               ← Admin dashboard
│   ├── canvas.php              ← Canvas management
│   ├── users.php               ← User management
│   └── logs.php                ← Admin action log viewer
│
├── api/
│   ├── save_score.php          ← Validate + save game score
│   ├── place_pixel.php         ← Place pixel on canvas
│   ├── get_canvas.php          ← Return all pixels (JSON, polled every 5s)
│   ├── get_territory.php       ← Return ownership map (JSON)
│   └── admin_action.php        ← Admin canvas/user mutations
│
├── includes/
│   ├── logger.php              ← BUILT FIRST. Required by everything.
│   ├── config.php              ← Alt config location if preferred
│   ├── db.php                  ← PDO singleton
│   ├── auth.php                ← Session helpers, auth guards
│   ├── csrf.php                ← Token generation + validation
│   ├── headers.php             ← Security HTTP headers
│   ├── xp.php                  ← XP/level helpers
│   └── achievements.php        ← Achievement checker + awarder
│
├── assets/
│   ├── css/
│   │   ├── style.css           ← Global design system (built second)
│   │   ├── game.css
│   │   ├── canvas.css
│   │   └── admin.css
│   └── js/
│       ├── game.js             ← Full Flappy Bird engine
│       ├── canvas.js           ← Canvas render, zoom, pan, polling
│       ├── territory.js        ← Territory overlay
│       └── achievements.js     ← Toast queue system
│
└── logs/
    ├── .htaccess               ← Deny from all
    └── event.log               ← Auto-created by logger.php
```

---

## 3.2 — DATABASE SCHEMA

Run all of this in `install.php` using `CREATE TABLE IF NOT EXISTS`.

```sql
-- Users
CREATE TABLE IF NOT EXISTS users (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  username        VARCHAR(30)  NOT NULL UNIQUE,
  email           VARCHAR(100) NOT NULL UNIQUE,
  password_hash   VARCHAR(255) NOT NULL,
  balance         INT          NOT NULL DEFAULT 0,
  xp              INT          NOT NULL DEFAULT 0,
  level           INT          NOT NULL DEFAULT 1,
  role            ENUM('user','admin') NOT NULL DEFAULT 'user',
  streak_days     INT          NOT NULL DEFAULT 0,
  last_login_date DATE         DEFAULT NULL,
  avatar_color    VARCHAR(7)   NOT NULL DEFAULT '#7c3aed',
  created_at      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pixel canvas
CREATE TABLE IF NOT EXISTS pixels (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  x           INT         NOT NULL,
  y           INT         NOT NULL,
  color       VARCHAR(7)  NOT NULL,
  owner_id    INT         DEFAULT NULL,
  placed_at   TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  expires_at  TIMESTAMP   DEFAULT NULL,
  UNIQUE KEY uq_pixel (x, y),
  FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Game history
CREATE TABLE IF NOT EXISTS score_log (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT            NOT NULL,
  score           INT            NOT NULL,
  multiplier      DECIMAL(3,1)   NOT NULL DEFAULT 1.0,
  currency_earned INT            NOT NULL,
  xp_earned       INT            NOT NULL,
  played_at       TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Anti-cheat tokens (one per game session)
CREATE TABLE IF NOT EXISTS game_tokens (
  id         INT         AUTO_INCREMENT PRIMARY KEY,
  user_id    INT         NOT NULL,
  token      VARCHAR(64) NOT NULL UNIQUE,
  used       TINYINT     NOT NULL DEFAULT 0,
  created_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Achievement definitions
CREATE TABLE IF NOT EXISTS achievements (
  id          INT          AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(50)  NOT NULL UNIQUE,
  name        VARCHAR(100) NOT NULL,
  description VARCHAR(255) NOT NULL,
  icon        VARCHAR(50)  NOT NULL,
  reward      INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- User–achievement join
CREATE TABLE IF NOT EXISTS user_achievements (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  user_id        INT NOT NULL,
  achievement_id INT NOT NULL,
  earned_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_achievement (user_id, achievement_id),
  FOREIGN KEY (user_id)        REFERENCES users(id)        ON DELETE CASCADE,
  FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Login rate limiting
CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  ip_address   VARCHAR(45) NOT NULL,
  attempted_at TIMESTAMP   DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pixel placement rate limiting
CREATE TABLE IF NOT EXISTS pixel_placements (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  user_id   INT NOT NULL,
  placed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin action audit trail
CREATE TABLE IF NOT EXISTS admin_log (
  id           INT          AUTO_INCREMENT PRIMARY KEY,
  admin_id     INT          NOT NULL,
  action       VARCHAR(100) NOT NULL,
  target_type  VARCHAR(50)  DEFAULT NULL,
  target_id    INT          DEFAULT NULL,
  details      TEXT         DEFAULT NULL,
  performed_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Seed achievements in install.php:**

```sql
INSERT IGNORE INTO achievements (slug, name, description, icon, reward) VALUES
('first_flight',   'First Flight',       'Complete your first game',           '🐦', 10),
('score_10',       'Getting Somewhere',  'Score 10 in a single run',           '🎯', 20),
('score_50',       'Flap Master',        'Score 50 in a single run',           '⭐', 75),
('score_100',      'Sky Ruler',          'Score 100 in a single run',          '👑', 200),
('first_pixel',    'Mark Your Territory','Place your first pixel',             '🎨', 15),
('pixel_5',        'Growing Empire',     'Own 5 pixels at once',               '🏘️', 30),
('pixel_25',       'Canvas Veteran',     'Own 25 pixels at once',              '🖼️', 100),
('pixel_100',      'Canvas Legend',      'Own 100 pixels at once',             '🏆', 500),
('streak_3',       'On a Roll',          'Log in 3 days in a row',             '🔥', 50),
('streak_7',       'Dedicated',          'Log in 7 days in a row',             '💪', 150),
('streak_30',      'Obsessed',           'Log in 30 days in a row',            '🌟', 1000),
('level_5',        'Rising Star',        'Reach level 5',                      '📈', 50),
('level_10',       'Veteran',            'Reach level 10',                     '🎖️', 150),
('level_20',       'Elite',              'Reach level 20',                     '💎', 500),
('multiplier_3x',  'Combo King',         'Hit 3× multiplier in one run',       '🔥', 100),
('broke_the_bank', 'Broke the Bank',     'Spend 500 currency on pixels total', '💸', 200);
```

---

# ╔══════════════════════════════════════════════════╗

# ║ SECTION 4 — FEATURE SPECIFICATIONS ║

# ╚══════════════════════════════════════════════════╝

## 4.1 — AUTHENTICATION

### Registration (`/register.php`)

- Fields: `username` (3–30 chars, alphanumeric + underscore), `email` (valid format), `password` (min 8 chars, at least 1 letter + 1 number), `confirm_password`.
- Show inline per-field validation errors without full-page reload.
- On success: hash with `password_hash($pass, PASSWORD_BCRYPT)`. Start balance=0, xp=0, level=1. Assign random `avatar_color` from preset palette: `['#7c3aed','#db2777','#0891b2','#059669','#d97706','#dc2626','#7c3aed','#4f46e5','#0d9488','#65a30d']`.
- Rate limit: max 3 registrations per IP per hour.
- Log every outcome.

### Login (`/login.php`)

- Accept username OR email + password.
- Rate limit: 5 failed attempts per IP per 15 minutes. On lockout, show remaining cooldown in seconds (poll via JS or refresh).
- On success: `session_regenerate_id(true)`. Set `$_SESSION['user_id']`, `$_SESSION['role']`, `$_SESSION['username']`.
- Update `last_login_date`. If new calendar day vs yesterday → increment `streak_days`. If gap > 1 day → reset `streak_days = 1`. Award streak bonus:
  - Day 1: +10, Day 2: +20, Day 3: +35, Day 5: +60, Day 7: +150, Day 14: +400, Day 30+: +800 currency
  - Store bonus in session, surface as toast on next page.
- Log every outcome.

### Session Security

```php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', 7200); // 2 hours
```

- Every protected page: check `$_SESSION['user_id']` exists, then verify user still exists in DB.
- Every admin page: re-query `role` from DB on every request.
- Logout: `session_unset(); session_destroy(); session_start(); session_regenerate_id(true);`

---

## 4.2 — XP & LEVEL SYSTEM

### XP Sources

| Action                     | XP Gained     |
| -------------------------- | ------------- |
| Complete any game          | score × 1 XP  |
| Place unclaimed pixel      | 5 XP          |
| Repaint own pixel          | 1 XP          |
| Earn an achievement        | 20 XP         |
| Daily login (streak bonus) | 10 XP per day |

### Level Formula

```
level = floor(1 + sqrt(xp / 50))
xp_for_next_level = (level)^2 * 50
```

Cap display at level 100. XP continues accumulating beyond.

### Level Perks (cosmetic only)

| Level | Perk                                       |
| ----- | ------------------------------------------ |
| 3     | 5 extra bird skin color options            |
| 5     | Animated sparkle on pixel placement        |
| 10    | Gold username on canvas hover tooltip      |
| 20    | Custom avatar border on profile page       |
| 50    | "Legend" permanent badge on canvas tooltip |

Show level-up toast on every level gained. XP progress bar in navbar updates live.

---

## 4.3 — ACHIEVEMENT SYSTEM

### Check Flow

After every game saved → check score, streak, level, multiplier achievements.
After every pixel placed → check pixel-count achievements.
All checks run server-side in `includes/achievements.php`.

New achievements returned in every API response:

```json
{
  "success": true,
  "new_achievements": [
    { "slug": "score_50", "name": "Flap Master", "icon": "⭐", "reward": 75 }
  ]
}
```

### Toast UI (in `assets/js/achievements.js`)

- Position: fixed bottom-right, `bottom: 24px; right: 24px`.
- Slides in from right with spring easing: `cubic-bezier(0.34, 1.56, 0.64, 1)`.
- Shows: icon (large), achievement name (bold), description (small, muted), reward (`+75 💰` in gold).
- Auto-dismiss after 4 seconds. Queue multiple — show sequentially 500ms apart.
- Non-blocking — never pauses game or canvas.

---

## 4.4 — FLAPPY BIRD GAME

### Engine (`assets/js/game.js`)

- Render on `<canvas>` 480×640px.
- Physics: gravity = 0.35px/frame, flap velocity = -7px. Smooth arc.
- Controls: click, tap (touchstart), spacebar.
- Pipes scroll left. Score +1 each time bird clears a pipe pair.
- Pipe spawn interval: every 90 frames.

### Dynamic Difficulty

Trigger on every 15 pipes cleared. Never reduce once increased.
| Tier | Pipe Speed | Gap Height | Flash Message |
|------|-----------|------------|---------------|
| 1 | 2.5 px/f | 150px | — |
| 2 | 3.0 px/f | 140px | "Speeding up!"|
| 3 | 3.5 px/f | 130px | "Getting harder!"|
| 4 | 4.0 px/f | 120px | "Expert mode!"|
| 5+ | 4.5 px/f | 115px | "Max speed!" |

Flash message shown center-screen for 1.5 seconds on tier increase.

### Score Multiplier

Every 10 pipes passed without dying:
| Pipes Cleared | Multiplier |
|---------------|-----------|
| 0–9 | ×1.0 |
| 10–19 | ×1.5 |
| 20–29 | ×2.0 |
| 30–39 | ×2.5 |
| 40+ | ×3.0 |

Display multiplier top-right (hidden at ×1.0, gold + glowing when above). Flash + scale animation on increase.

Currency earned = `score × multiplier × 2` (base: 1 point = 2 currency).
Server independently recalculates multiplier from score. If they mismatch → reject + log security event.

### Power-Ups

Spawn as glowing orbs between pipe pairs. Rate: one every 8–12 pipes (random). Max one active power-up at a time.

| Power-Up | Color  | Effect                          | Duration |
| -------- | ------ | ------------------------------- | -------- |
| Shield   | Blue   | Survive next collision          | One-time |
| Slow-Mo  | Purple | Pipes at 50% speed              | 5 sec    |
| Magnet   | Gold   | Auto-collect coins within 120px | 8 sec    |
| ×2 Coin  | Green  | All coins worth double          | 10 sec   |

**Coin Orbs:** 2–4 small coin orbs spawn per pipe gap in the flight path, worth 5 currency each. Magnet auto-collects. Include `coins_collected` (max 50) in score payload.

### Anti-Cheat Flow

1. `game.php` loads → generate `$token = bin2hex(random_bytes(32))`. Store in `$_SESSION['game_token']` AND insert into `game_tokens` table (`used=0`). Embed in hidden input.
2. On game over, JS POSTs to `/api/save_score.php`: `{game_token, score, multiplier, coins_collected, csrf_token}`.
3. Server validates ALL of:
   - CSRF token matches session
   - Token exists in DB, belongs to `$_SESSION['user_id']`, `used=0`
   - Token `created_at` within last 10 minutes
   - `score` is int, 0–500
   - `multiplier` matches server-recalculated value from score
   - `coins_collected` is int, 0–50
4. If valid: mark token `used=1`, update balance, log to `score_log`, check achievements.
5. Rate limit: generating a new game invalidates the previous token immediately.
6. Reject with specific error messages and log security events for every failure mode.

### Game Over Screen (overlay, no page reload)

Animate in over the game canvas:

- Score: count up from 0 over 1.2s (easeOutExpo)
- Multiplier used
- Currency earned: count up, gold color, glow
- Coins collected
- XP earned
- Personal best — if beaten: confetti particle burst (canvas-based, 60 particles)
- "Play Again" button → generate new token, reset all game state
- "Go to Canvas" button → navigate to canvas.php
- Achievement toasts fire 600ms after score reveal ends

### In-Game HUD

- Top-left: current score (white, 28px, semibold)
- Top-right: multiplier badge (hidden at ×1.0; gold pill badge above ×1.0)
- Bottom-center: active power-up icon + countdown bar (thin progress bar, color-coded)
- ESC or pause button (top-right corner): freeze game loop

### Leaderboard (below game, same page)

Three tabs: **All-time | This Week | Today**
Columns: rank number, avatar circle, username, level badge, score, multiplier, currency earned, date.

- Rank #1: 🥇 gold text. Rank #2: silver. Rank #3: bronze.
- Your own row: purple left border + subtle purple background tint.
- Top 10 shown. Below table: "Your rank: #42" if not in top 10.

---

## 4.5 — LIVE PIXEL CANVAS

### Rendering

- Grid: 100×100 cells. Each cell = 8×8 browser pixels = 800×800px base.
- HTML5 Canvas element.
- Unclaimed cells: `#1a1a1a` with `#252535` gridlines every 10 cells.
- Claimed cells: their stored hex color.
- Zoom: scroll wheel, 1×–6×, centered on cursor. Pinch-to-zoom on mobile.
- Pan: click-drag (cursor = grab/grabbing). Clamped — canvas never fully leaves viewport. Two-finger pan on mobile.
- Zoom indicator: bottom-left corner ("2×").
- Minimap: bottom-right, 150×150px, shows full canvas scaled down + current viewport rectangle as white outline. Click minimap to pan there.

### Pixel Decay

- `expires_at = placed_at + 14 DAYS` set on every placement/repaint.
- 0–7 days: full opacity.
- 7–14 days: 70% opacity + subtle CSS shimmer animation (`animation: shimmer 2s ease-in-out infinite`).
- 14+ days: deleted from DB (cleaned during canvas fetch), shown as unclaimed.
- Owner repainting resets `placed_at` and `expires_at` fully.

### Real-Time Polling

- Fetch `/api/get_canvas.php` every 5 seconds.
- Response: `{ pixels: [{x, y, color, owner_id, username, level, placed_at, expires_at}], timestamp, total_claimed }`.
- Diff against local state — only redraw changed cells.
- "Live" pulse: green breathing dot top-right of canvas container.
- API side-effect: `DELETE FROM pixels WHERE expires_at < NOW()` (purge expired, log count).

### Placing a Pixel (canvas.php, logged in only)

On cell click (not drag):

1. Identify grid cell from canvas coords accounting for zoom/pan offset.
2. Show **Pixel Panel** (right side desktop / bottom mobile, slide-in animation):
   - Coordinates `(x, y)`
   - Owner info: "Unclaimed" or `[username] · Level N · placed X days ago`
   - Decay warning if your pixel expires in < 3 days: `⚠ Expires in X days — click to repaint`
   - Color picker: `<input type="color">` + hex text input (synced bidirectionally)
   - Cost: "5 💰" (unclaimed) or "Free — your pixel" (own pixel)
   - Current balance shown, updates live
   - "Place Pixel" button — disabled with shake + red flash if insufficient balance or pixel owned by another
   - Inline error messages only
3. POST to `/api/place_pixel.php`: `{x, y, color, csrf_token}`.
4. Server validates: session, CSRF, x/y range 0–99, color regex `^#[0-9A-Fa-f]{6}$`, ownership rules, balance ≥ 5 (if unclaimed), rate limit ≤ 10/min.
5. DB: `INSERT INTO pixels (x,y,color,owner_id,placed_at,expires_at) VALUES (?,?,?,?,NOW(),DATE_ADD(NOW(), INTERVAL 14 DAY)) ON DUPLICATE KEY UPDATE color=IF(owner_id=? OR owner_id IS NULL, VALUES(color), color), owner_id=IF(owner_id IS NULL, VALUES(owner_id), owner_id), placed_at=IF(owner_id=?, NOW(), placed_at), expires_at=IF(owner_id=?, DATE_ADD(NOW(), INTERVAL 14 DAY), expires_at)` — wrap in a transaction with a SELECT FOR UPDATE to handle race conditions cleanly.
6. On success: deduct 5 currency (if unclaimed), +5 XP (unclaimed) or +1 XP (repaint), insert into `pixel_placements`, check achievements, return `{success:true, new_balance, new_xp, new_level, new_achievements}`.
7. Immediately update local canvas state without waiting for next poll.

### Hover Tooltip

- Desktop: mousemove. Mobile: long-press (500ms).
- Show near cursor (repositioned to stay in viewport):
  - Unclaimed: "Unclaimed — 5 💰 to claim"
  - Your pixel: "Your pixel · free to repaint · ⚠ X days left" (warning if < 3 days)
  - Other's pixel: "Owned by [username] · Level N · placed X days ago"
- Tooltip: dark card, purple border, arrow pointing to cell.

### "My Pixels" Button

Toggle: highlights all pixels owned by the current user with a white 1px outline overlay. All other pixels dimmed to 50% opacity. Click again to toggle off.

### Territory Overlay (`assets/js/territory.js`)

Toggle button switches between two modes:

- **Art view** (default): pixels in their actual color.
- **Territory view**: each pixel colored by owner's `avatar_color`. Unclaimed = `#1a1a1a`. Makes ownership wars visible across the whole 100×100 grid.

In territory view, show a legend panel: top 5 pixel owners with their color swatch + username + pixel count.

### Canvas Full State

When all 10,000 pixels are claimed and none expiring:

- Banner: "Canvas is full — pixels expire after 14 days of inactivity. Check back soon!"
- Disable "Place Pixel" button.
- Hover tooltips and territory overlay still work.

### Public View (`/index.php`)

- Same render + polling. No click-to-place. No pixel panel.
- Sticky banner: "Log in to draw on the canvas →" (purple gradient, dismissable).
- Territory overlay still works.

---

## 4.6 — LEADERBOARD (`/leaderboard.php`)

Three tabs with smooth CSS transitions:

**Tab 1: Top Scores**
Columns: rank, avatar, username, level, score, multiplier, currency earned, date.
Filters: All-time / This Week / Today (query `played_at` from `score_log`).

**Tab 2: Most Pixels Owned**
Columns: rank, avatar, username, level, pixels currently owned, total all-time placed.
Query: `SELECT owner_id, COUNT(*) FROM pixels WHERE owner_id IS NOT NULL GROUP BY owner_id ORDER BY COUNT(*) DESC`.

**Tab 3: Most XP**
Columns: rank, avatar, username, level, total XP, achievement count.

All tabs: top 50. Logged-in user's row always highlighted with purple border regardless of position. Clicking username → `/profile.php?user=username`.

Rank #1: 🥇 gold. Rank #2: 🥈 silver `#c0c0c0`. Rank #3: 🥉 bronze `#cd7f32`.

---

## 4.7 — PUBLIC PROFILE (`/profile.php?user=username`)

Max-width 700px, centered. Public — no login required. Never show email.

**Header**

- Avatar circle: initial letter + `avatar_color` background, 80px.
- Username (large, bold) + level badge.
- Stats row: Best Score · Pixels Owned · Total XP · Member Since.

**Mini Canvas** (200×200px)

- Shows ONLY this user's pixels highlighted in their `avatar_color`.
- All other pixels: near-black transparent.
- Shows their territory at a glance.

**Achievement Showcase**

- Grid of all achievement slots. Earned = full color + icon. Unearned = gray + lock icon.
- Hover tooltip: name, description, earned date (or "Locked").

**Recent Activity**

- Last 10 games: date, score, multiplier, currency earned.
- Last 10 pixels placed: coordinate, color swatch, date placed.

---

## 4.8 — ADMIN PANEL (`/admin/`)

### Access Control

- Every admin page: re-query `role` from DB. If not admin → HTTP 403 plain page. Do not redirect to login.
- All mutations via POST only. No state changes on GET.

### Dashboard (`/admin/index.php`)

Stats cards: total users, total claimed pixels, canvas fill %, total currency in circulation, active users today (logged in in last 24h).
Recent admin log: last 20 actions, paginated.

### Canvas Management (`/admin/canvas.php`)

Full canvas render.

- **Single erase**: click pixel → custom confirm modal → DELETE from DB → log.
- **Multi-select**: toggle mode → click to select (red outline) → "Erase Selected (N)" → confirm → bulk DELETE → log.
- **Area select**: click-drag to select rectangle → confirm → bulk DELETE → log.
- **Reset Canvas**: button → modal requiring user to type `RESET` exactly → TRUNCATE pixels table → log `{action:'canvas_reset'}`.
- **Fill Unclaimed**: color picker → fill all `owner_id IS NULL` pixels with chosen color → log.
  Admin canvas actions bypass currency/XP economy entirely.

### User Management (`/admin/users.php`)

Paginated table (25/page). Columns: avatar, username, email, level, balance, pixels owned, role, streak, joined.
Live JS search by username or email.
Per-user expand row:

- **Set Balance**: absolute integer ≥ 0. Save + log.
- **Adjust Balance**: delta (e.g. +100 / -50). Save + log.
- **Change Role**: toggle user ↔ admin. Block if last admin.
- **Delete User**: confirm modal → DELETE user → SET pixels.owner_id = NULL → log.
- **Reset Streak**: set streak_days = 0 → log.
- **View Profile**: link.
  Guards: admin cannot delete or demote themselves. If only admin, block role change.

### Admin Log (`/admin/logs.php`)

Full paginated log of `admin_log` table. Filter by action type + date range.

---

# ╔══════════════════════════════════════════════════╗

# ║ SECTION 5 — SECURITY ║

# ╚══════════════════════════════════════════════════╝

### Database

- PDO with `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`, `PDO::ATTR_EMULATE_PREPARES => false`.
- All queries: `prepare()` + `execute()`. Zero string interpolation. Ever.
- DB user: SELECT, INSERT, UPDATE, DELETE only. No DDL privileges.

### CSRF

```php
// includes/csrf.php
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_verify(): void {
    $token = trim($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        log_sec('SECURITY', 'CSRF token mismatch');
        http_response_code(403);
        exit(json_encode(['error' => 'Invalid request token']));
    }
}
```

Every POST calls `csrf_verify()`. Every form includes `<input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">`.

### Rate Limits

| Endpoint               | Limit                         |
| ---------------------- | ----------------------------- |
| Login                  | 5 failed per IP per 15 min    |
| Register               | 3 per IP per hour             |
| `/api/place_pixel.php` | 10 per user per 60 seconds    |
| `/api/save_score.php`  | 1 per game token (single-use) |

### HTTP Security Headers (`includes/headers.php`, included everywhere)

```php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
```

---

# ╔══════════════════════════════════════════════════╗

# ║ SECTION 6 — DESIGN SYSTEM ║

# ╚══════════════════════════════════════════════════╝

This application must look **stunning and premium**. Not acceptable. Not "good enough".
It must feel like a polished commercial product people want to live in.

## 6.1 — CSS CUSTOM PROPERTIES (define in `:root`, use everywhere)

```css
:root {
  --bg-base: #080810;
  --bg-surface: #0f0f1a;
  --bg-elevated: #161625;
  --bg-card: #1a1a2e;
  --bg-input: #12121f;

  --border-subtle: rgba(255, 255, 255, 0.06);
  --border-default: rgba(255, 255, 255, 0.1);
  --border-strong: rgba(255, 255, 255, 0.18);

  --purple-dim: #3b0764;
  --purple-mid: #6d28d9;
  --purple-core: #7c3aed;
  --purple-bright: #a78bfa;
  --purple-glow: rgba(124, 58, 237, 0.35);

  --gold-dark: #78350f;
  --gold-mid: #d97706;
  --gold-bright: #fbbf24;
  --gold-glow: rgba(251, 191, 36, 0.3);

  --green: #22c55e;
  --red: #ef4444;
  --blue: #3b82f6;

  --text-primary: #f0f0ff;
  --text-secondary: #9090b0;
  --text-muted: #50506a;

  --space-xs: 4px;
  --space-sm: 8px;
  --space-md: 16px;
  --space-lg: 24px;
  --space-xl: 40px;
  --space-2xl: 64px;

  --radius-sm: 6px;
  --radius-md: 12px;
  --radius-lg: 18px;
  --radius-xl: 24px;
  --radius-pill: 999px;

  --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.5);
  --shadow-md: 0 4px 20px rgba(0, 0, 0, 0.6);
  --shadow-lg: 0 8px 40px rgba(0, 0, 0, 0.8);
  --glow-purple: 0 0 20px var(--purple-glow), 0 0 60px rgba(124, 58, 237, 0.15);
  --glow-gold: 0 0 16px var(--gold-glow);

  --font: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  --font-mono: "SF Mono", "Fira Code", monospace;
}
```

## 6.2 — COMPONENT STYLES

**Body & base:**

```css
body {
  background: var(--bg-base);
  color: var(--text-primary);
  font-family: var(--font);
  margin: 0;
}
* {
  box-sizing: border-box;
}
::-webkit-scrollbar {
  width: 6px;
}
::-webkit-scrollbar-track {
  background: var(--bg-base);
}
::-webkit-scrollbar-thumb {
  background: var(--border-strong);
  border-radius: 3px;
}
```

**Navbar (sticky frosted glass):**

```css
nav {
  background: rgba(8, 8, 16, 0.85);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid var(--border-subtle);
  position: sticky;
  top: 0;
  z-index: 1000;
  padding: 0 var(--space-xl);
  height: 60px;
  display: flex;
  align-items: center;
  justify-content: space-between;
}
```

**Primary button:**

```css
.btn-primary {
  background: linear-gradient(135deg, var(--purple-mid), var(--purple-core));
  color: #fff;
  border: none;
  padding: 12px 28px;
  border-radius: var(--radius-pill);
  font-weight: 600;
  font-size: 15px;
  cursor: pointer;
  box-shadow: var(--glow-purple);
  transition: all 0.2s ease;
}
.btn-primary:hover {
  transform: translateY(-2px);
  filter: brightness(1.12);
}
.btn-primary:active {
  transform: translateY(0) scale(0.97);
}
.btn-primary:focus {
  outline: 2px solid var(--purple-bright);
  outline-offset: 3px;
}
```

**Secondary button:**

```css
.btn-secondary {
  background: transparent;
  border: 1px solid var(--border-default);
  color: var(--text-primary);
  padding: 11px 24px;
  border-radius: var(--radius-pill);
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-secondary:hover {
  background: var(--bg-elevated);
  border-color: var(--border-strong);
}
```

**Danger button:**

```css
.btn-danger {
  background: linear-gradient(135deg, #b91c1c, var(--red));
  color: #fff;
  border: none;
  padding: 11px 24px;
  border-radius: var(--radius-pill);
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}
.btn-danger:hover {
  filter: brightness(1.1);
  transform: translateY(-1px);
}
```

**Cards:**

```css
.card {
  background: var(--bg-card);
  border: 1px solid var(--border-subtle);
  border-radius: var(--radius-lg);
  padding: var(--space-lg);
  box-shadow: var(--shadow-md);
}
```

**Input fields:**

```css
input[type="text"],
input[type="email"],
input[type="password"],
input[type="number"],
input[type="color"],
select,
textarea {
  background: var(--bg-input);
  border: 1px solid var(--border-default);
  border-radius: var(--radius-md);
  color: var(--text-primary);
  padding: 12px 16px;
  font-size: 15px;
  width: 100%;
  transition:
    border-color 0.2s,
    box-shadow 0.2s;
  outline: none;
}
input:focus,
select:focus,
textarea:focus {
  border-color: var(--purple-core);
  box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.2);
}
input.error {
  border-color: var(--red);
  box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2);
}
```

**Currency display (always gold):**

```css
.currency {
  color: var(--gold-bright);
  font-weight: 700;
  text-shadow: 0 0 12px var(--gold-glow);
}
```

**Level badge:**

```css
.level-badge {
  background: linear-gradient(135deg, var(--purple-dim), var(--purple-mid));
  color: var(--purple-bright);
  font-size: 11px;
  font-weight: 700;
  padding: 3px 10px;
  border-radius: var(--radius-pill);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}
```

**Tables:**

```css
table {
  border-collapse: collapse;
  width: 100%;
}
th {
  background: var(--bg-elevated);
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-muted);
  padding: 12px 16px;
  text-align: left;
}
td {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border-subtle);
}
tr:hover td {
  background: rgba(255, 255, 255, 0.02);
}
tr.highlighted td {
  background: rgba(124, 58, 237, 0.08);
  border-left: 3px solid var(--purple-core);
}
```

**Achievement toast:**

```css
.toast {
  position: fixed;
  bottom: 24px;
  right: 24px;
  z-index: 9999;
  background: var(--bg-elevated);
  border: 1px solid var(--purple-core);
  border-radius: var(--radius-lg);
  padding: 16px 20px;
  display: flex;
  gap: 14px;
  align-items: center;
  box-shadow: var(--glow-purple), var(--shadow-lg);
  animation: toastIn 0.4s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
  max-width: 320px;
}
@keyframes toastIn {
  from {
    transform: translateX(120%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}
@keyframes toastOut {
  from {
    transform: translateX(0);
    opacity: 1;
  }
  to {
    transform: translateX(120%);
    opacity: 0;
  }
}
```

**Game canvas wrapper:**

```css
.game-wrap {
  border: 1px solid var(--border-strong);
  border-radius: var(--radius-xl);
  box-shadow: var(--glow-purple), var(--shadow-lg);
  overflow: hidden;
  display: inline-block;
}
```

**XP progress bar (navbar):**

```css
.xp-bar-wrap {
  width: 120px;
  height: 6px;
  background: var(--bg-elevated);
  border-radius: var(--radius-pill);
  overflow: hidden;
}
.xp-bar-fill {
  height: 100%;
  background: linear-gradient(90deg, var(--purple-mid), var(--purple-bright));
  border-radius: var(--radius-pill);
  transition: width 0.6s ease;
  box-shadow: 0 0 8px var(--purple-glow);
}
```

**Empty states:**

```css
.empty-state {
  text-align: center;
  padding: var(--space-2xl) var(--space-xl);
  color: var(--text-muted);
}
.empty-state .empty-icon {
  font-size: 48px;
  margin-bottom: var(--space-md);
}
.empty-state h3 {
  color: var(--text-secondary);
  margin-bottom: var(--space-sm);
}
.empty-state p {
  margin-bottom: var(--space-lg);
}
```

**Micro-animations:**

```css
@keyframes shake {
  0%,
  100% {
    transform: translateX(0);
  }
  20% {
    transform: translateX(-8px);
  }
  40% {
    transform: translateX(8px);
  }
  60% {
    transform: translateX(-4px);
  }
  80% {
    transform: translateX(4px);
  }
}
@keyframes shimmer {
  0%,
  100% {
    opacity: 0.7;
  }
  50% {
    opacity: 0.4;
  }
}
@keyframes pulse-dot {
  0%,
  100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: 0.5;
    transform: scale(0.85);
  }
}
@keyframes levelup-flash {
  0% {
    background: rgba(124, 58, 237, 0);
  }
  30% {
    background: rgba(124, 58, 237, 0.15);
  }
  100% {
    background: rgba(124, 58, 237, 0);
  }
}
```

**Responsive (mobile ≥375px):**

```css
@media (max-width: 768px) {
  .nav-center,
  .nav-right-desktop {
    display: none;
  }
  .hamburger {
    display: flex;
  }
  .page-content {
    padding: var(--space-md);
  }
  .card {
    border-radius: var(--radius-md);
    padding: var(--space-md);
  }
  .btn-primary,
  .btn-secondary {
    width: 100%;
    text-align: center;
  }
  .pixel-panel {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  }
}
```

## 6.3 — UX RULES

- Balance counter: animate number tween when it changes (JS `requestAnimationFrame` counter from old → new value over 600ms).
- Level-up: full-screen `levelup-flash` animation + toast "Level up! You're now Level N 🎉".
- Pixel placement: ripple effect on placed cell (concentric circle expanding outward, fades in 400ms).
- Insufficient balance: `shake` animation on button + border flash red + inline error text.
- All loading states: button shows spinner + is disabled during async calls. Never let a user click twice.
- Page max-width: 1100px, centered. Sections: min 40px vertical padding.
- All transitions: `0.2s ease`. Never `0s` (feels broken) or `0.5s` (feels sluggish).
- Rank #1 in leaderboards: gold crown emoji + `color: var(--gold-bright)`.
- Empty states: never blank space — show icon + heading + subtext + CTA button.

---

# ╔══════════════════════════════════════════════════╗

# ║ SECTION 7 — BUILD ORDER ║

# ║ BUILD EXACTLY IN THIS ORDER. NO SKIPPING. ║

# ╚══════════════════════════════════════════════════╝

Test each step before starting the next. If a step has an
error, fix it completely before proceeding.

```
Step 01 — config.php                    (DB creds, APP_NAME, BASE_URL constants)
Step 02 — includes/logger.php           (THE LOG SYSTEM — built first, always)
Step 03 — includes/db.php              (PDO singleton, reads config.php)
Step 04 — includes/headers.php         (security HTTP headers)
Step 05 — includes/csrf.php            (token gen + validation)
Step 06 — includes/auth.php            (session guards, auth helpers)
Step 07 — includes/xp.php              (level formula, XP helpers)
Step 08 — includes/achievements.php    (checker + awarder logic)
Step 09 — install.php                  (CREATE all tables, seed achievements,
                                        create admin user, then self-delete)
Step 10 — assets/css/style.css         (full design system from Section 6)
Step 11 — includes/header.php          (shared HTML head + navbar)
Step 12 — includes/footer.php          (shared closing HTML)
Step 13 — login.php + register.php + logout.php
Step 14 — api/get_canvas.php           (returns JSON, no auth needed)
Step 15 — index.php                    (public read-only canvas)
Step 16 — assets/js/canvas.js          (render, zoom, pan, poll, territory)
Step 17 — assets/js/territory.js       (overlay toggle)
Step 18 — api/place_pixel.php          (auth required)
Step 19 — canvas.php                   (interactive canvas page)
Step 20 — api/save_score.php           (score validation + save)
Step 21 — game.php                     (game page shell)
Step 22 — assets/js/game.js            (full Flappy Bird engine)
Step 23 — assets/js/achievements.js    (toast queue)
Step 24 — leaderboard.php
Step 25 — profile.php
Step 26 — admin/index.php
Step 27 — admin/users.php
Step 28 — admin/canvas.php
Step 29 — admin/logs.php
Step 30 — api/admin_action.php
Step 31 — api/get_territory.php
Step 32 — logs/.htaccess               (Deny from all)
Step 33 — .htaccess                    (root — block /logs/ + /includes/ access)
Step 34 — README.md                    (setup: DB, config, install.php, cron, perms)
```

---

# ╔══════════════════════════════════════════════════╗

# ║ SECTION 8 — EDGE CASES ║

# ╚══════════════════════════════════════════════════╝

Handle every one of these. No silent failures.

| Scenario                                      | Required Handling                                                                   |
| --------------------------------------------- | ----------------------------------------------------------------------------------- |
| Two users claim same pixel simultaneously     | UNIQUE KEY (x,y) catches race. Return: "Pixel just claimed — refresh and try again" |
| Score submitted with used/expired token       | Reject + log_sec. Return: "Invalid game session. Start a new game."                 |
| Multiplier doesn't match score                | Reject + log_sec (tampering). Return: "Score rejected."                             |
| Score above 500 cap                           | Reject + log_sec. Return: "Score rejected."                                         |
| Canvas 100% full, nothing expiring            | Show full-canvas banner. Disable Place button. Tooltip/territory still work.        |
| User tries to overwrite another's valid pixel | Server rejects. Return: "Owned by [username]"                                       |
| Admin deletes currently-logged-in user        | Session check on next request fails (user_id gone). Redirect to login.              |
| Only admin tries to demote/delete themselves  | Block: "You are the only admin. Promote another user first."                        |
| Invalid hex color submitted                   | Regex fail server-side. Return: "Invalid color format."                             |
| Achievement already earned, triggered again   | `INSERT IGNORE` — silently skip, no duplicate toast.                                |
| XP/level edge cases                           | Cap display at level 100. XP accumulates beyond.                                    |
| Pixel repainted at exact moment of expiry     | Transaction + SELECT FOR UPDATE handles atomically.                                 |
| User with zero balance tries to claim pixel   | Reject before DB hit. Return: "Insufficient balance."                               |
| Rate limit hit on pixel placement             | Reject + log_sec. Return: "Too many placements — wait a moment."                    |
| DB connection fails                           | logger catches exception, return 500 JSON, never expose PDO message.                |
| install.php run twice                         | `CREATE TABLE IF NOT EXISTS` + `INSERT IGNORE` — idempotent. No duplicate data.     |

---

# ╔══════════════════════════════════════════════════╗

# ║ SECTION 9 — SETUP & INSTALL ║

# ╚══════════════════════════════════════════════════╝

### `/config.php`

```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pixelflap');
define('DB_USER', 'pixelflap_user');
define('DB_PASS', 'your_password_here');
define('APP_NAME', 'PixelFlap');
define('BASE_URL', 'http://localhost');
define('ADMIN_DEFAULT_PASS', 'Admin1234!');
```

### `/install.php` must:

1. `require_once 'includes/logger.php'` and `require_once 'config.php'`
2. Create PDO connection
3. Run all `CREATE TABLE IF NOT EXISTS` statements
4. Run all `INSERT IGNORE INTO achievements` statements
5. Create default admin: username=`admin`, email=`admin@pixelflap.local`, password=`password_hash(ADMIN_DEFAULT_PASS, PASSWORD_BCRYPT)`, role=`admin`
6. Echo a success HTML page with setup instructions
7. `unlink(__FILE__)` — self-delete

### `README.md` must document:

1. Create MySQL database: `CREATE DATABASE pixelflap CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
2. Create DB user with SELECT/INSERT/UPDATE/DELETE only
3. Edit `config.php`
4. Visit `/install.php` once in browser
5. Set permissions: `chmod 755 logs/`, `chown www-data:www-data logs/`
6. Set up cron for decay cleanup: `0 * * * * curl -s http://yourdomain.com/api/get_canvas.php > /dev/null`
7. Change default admin password immediately after install
8. How to read logs: `tail -f logs/event.log`
9. How to filter logs: `grep "[SECURITY]" logs/event.log`

ENDOFPROMPT
