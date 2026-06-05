# PixelForge Frontend Redesign — Complete Plan

## 1. Sitemap & Information Architecture

```
pixelforge/
├── index.html          → Landing / Auth (unauthenticated)
├── home.html           → Dashboard (authenticated)
├── play.html           → Match-3 Game (authenticated)
├── world.html          → Canvas World (public, enhanced)
├── rankings.html       → Rankings (public)
├── profile.html        → Player Profile (public view, own = edit)
└── admin/
    └── index.html      → Admin Panel (admin only)
```

### Page Purpose Matrix

| Page | Auth | Purpose | Primary CTA | API Endpoints Used |
|------|------|---------|-------------|-------------------|
| **index** | No | Convert visitors to players | "Play Now" → register | `login`, `register` |
| **home** | Yes | Mission control, show progress | "Play Match-3" | `me`, `canvas.php`, `leaderboard`, `profile` |
| **play** | Yes | Core gameplay loop | N/A (game) | `start_game`, `submit_score`, `get_boosters`, `buy_booster`, `use_booster` |
| **world** | No | Explore the shared canvas | Place pixel | `canvas.php`, `get_area`, `place`, `get_user_pixels` |
| **rankings** | No | Competitive discovery | View player | `leaderboard` |
| **profile** | No | Player identity & stats | — | `profile`, `get_user_pixels` |

### Navigation Hierarchy

```
Unauthenticated:
  index.html → Login/Register

Authenticated (sidebar):
  home.html      → Dashboard
  play.html      → Match-3
  world.html     → Canvas
  rankings.html  → Rankings
  profile.html   → Profile
  ─────────────
  Settings       → (future)
  Logout
```

---

## 2. User Flow Diagrams

### Flow 1: New User Acquisition
```
Landing Page (index.html)
  ↓ Click "Play Now"
Register Form
  ↓ Submit (api/auth.php?action=register)
  ↓ Session created, balance = 10 gems
Home Dashboard (home.html)
  ↓ See "Play Match-3" CTA
Play Page (play.html)
  ↓ Start Game (api/game.php?action=start_game)
  ↓ Play match-3, earn score
  ↓ Game Over → Submit Score
  ↓ Gems earned, XP gained
  ↓ Level up? → Achievement check
Return to Home
  ↓ See updated stats
  ↓ See "Open Canvas" CTA
World Page (world.html)
  ↓ Spend gems to place pixels
  ↓ Build territory
```

### Flow 2: Returning Player
```
Login (index.html)
  ↓ Session restored
Home Dashboard (home.html)
  ↓ "Good evening, Zenith."
  ↓ "Your territory gained 134 pixels while you were away."
  ↓ See gem balance, level, rank
  ↓ Click "Play Match-3"
Play Page (play.html)
  ↓ Game loop
  ↓ Submit score
  ↓ Earn gems
  ↓ Spend gems on pixels
World Page (world.html)
```

### Flow 3: Canvas Exploration
```
World Page (world.html)
  ↓ Pan/zoom canvas
  ↓ See ownership overlay
  ↓ Click coordinate → Place pixel
  ↓ Gem balance deducted
  ↓ Pixel appears on canvas
  ↓ See activity feed update
```

### Flow 4: Competitive Discovery
```
Rankings Page (rankings.html)
  ↓ Browse Players tab
  ↓ Click player → Profile
  ↓ See stats, achievements, territory
  ↓ Compare with own profile
  ↓ Motivated to play more
```

---

## 3. Design System Specification

### 3.1 Color Tokens

```css
:root {
  /* Backgrounds */
  --bg-primary: #F7F7F5;
  --bg-secondary: #FFFFFF;
  --bg-tertiary: #F0F0ED;
  --bg-hover: #EDEDEA;

  /* Borders */
  --border-default: #E7E7E4;
  --border-strong: #D1D1CC;
  --border-subtle: #F0F0ED;

  /* Text */
  --text-primary: #111111;
  --text-secondary: #666666;
  --text-muted: #999999;
  --text-inverse: #FFFFFF;

  /* Accent */
  --accent: #E17B47;
  --accent-hover: #D06A36;
  --accent-light: #FDF0E8;
  --accent-subtle: #FAE8DA;

  /* Semantic */
  --success: #4D9B6D;
  --success-light: #E8F5EE;
  --danger: #D96464;
  --danger-light: #FDE8E8;
  --warning: #DDAA45;
  --warning-light: #FDF5E0;
  --info: #5B8DEF;
  --info-light: #EBF1FD;

  /* Game gem colors */
  --gem-red: #E17B47;
  --gem-blue: #5B8DEF;
  --gem-green: #4D9B6D;
  --gem-yellow: #DDAA45;
  --gem-purple: #9B6BDF;
  --gem-pink: #DF6B9B;
}
```

### 3.2 Spacing Scale

```css
:root {
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-6: 24px;
  --space-8: 32px;
  --space-12: 48px;
  --space-16: 64px;
  --space-24: 96px;
}
```

### 3.3 Typography

```css
:root {
  --font-sans: 'Inter', system-ui, -apple-system, sans-serif;
  --font-mono: 'JetBrains Mono', 'SF Mono', monospace;

  --text-xs: 12px;
  --text-sm: 14px;
  --text-base: 16px;
  --text-lg: 20px;
  --text-xl: 24px;
  --text-2xl: 32px;
  --text-3xl: 48px;

  --weight-regular: 400;
  --weight-medium: 500;
  --weight-semibold: 600;
  --weight-bold: 700;

  --leading-tight: 1.2;
  --leading-normal: 1.5;
  --leading-relaxed: 1.65;
}
```

### 3.4 Border Radius

```css
:root {
  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;
}
```

### 3.5 Shadows

```css
:root {
  --shadow-xs: 0 1px 2px rgba(0,0,0,0.04);
  --shadow-sm: 0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04);
  --shadow-md: 0 4px 6px rgba(0,0,0,0.04), 0 2px 4px rgba(0,0,0,0.03);
  --shadow-lg: 0 10px 15px rgba(0,0,0,0.05), 0 4px 6px rgba(0,0,0,0.03);
}
```

### 3.6 Motion

```css
:root {
  --duration-fast: 100ms;
  --duration-normal: 150ms;
  --duration-slow: 200ms;
  --duration-slower: 300ms;
  --ease-out: cubic-bezier(.22,.61,.36,1);
  --ease-in-out: cubic-bezier(.45,.05,.55,.95);
}
```

### 3.7 Z-Index Scale

```css
:root {
  --z-base: 0;
  --z-dropdown: 100;
  --z-sticky: 200;
  --z-overlay: 300;
  --z-modal: 400;
  --z-toast: 500;
  --z-tooltip: 600;
}
```

---

## 4. Component Inventory

### Primitives

| Component | Variants | States | Notes |
|-----------|----------|--------|-------|
| **Button** | primary, secondary, ghost, danger | default, hover, active, disabled, loading | 3 sizes: sm (32px), md (40px), lg (48px) |
| **Input** | text, email, password, search, number | default, focus, error, disabled | With label, helper text, error message |
| **Badge** | default, accent, success, danger, warning, info | — | Pill shape, 12px text |
| **Avatar** | sm (32px), md (40px), lg (56px), xl (80px) | — | Color-based (no images), shows initials |
| **Progress** | linear, circular | — | Animated fill |
| **Tooltip** | top, bottom, left, right | — | On hover/focus |

### Layout

| Component | Description |
|-----------|-------------|
| **Sidebar** | Left sidebar, 240px desktop, overlay on mobile |
| **PageHeader** | Page title + optional action |
| **Container** | Max-width wrapper, 1200px |
| **Grid** | 2-col, 3-col, 4-col responsive grid |
| **Stack** | Vertical flex with gap |

### Cards

| Component | Description |
|-----------|-------------|
| **StatCard** | Icon, label, value, optional trend |
| **PlayerCard** | Avatar, name, level, stats, rank |
| **AchievementCard** | Icon, name, description, progress/earned |
| **ActivityItem** | Icon, text, timestamp |
| **GameCard** | Score, combo, gems earned, date |

### Navigation

| Component | Description |
|-----------|-------------|
| **Sidebar** | Logo, nav items (icons + labels), active indicator, user section at bottom |
| **MobileNav** | Bottom bar, 5 icons, active state |
| **Breadcrumb** | Optional, for deep navigation |

### Feedback

| Component | Description |
|-----------|-------------|
| **Toast** | Success, error, warning, info. Auto-dismiss 3s. |
| **Modal** | Overlay, header, body, footer, close button |
| **ConfirmDialog** | Modal variant for destructive actions |
| **Skeleton** | Loading placeholder, shimmer animation |
| **EmptyState** | Icon, message, optional CTA |

### Data Display

| Component | Description |
|-----------|-------------|
| **Table** | Sortable headers, row hover, responsive |
| **RankingRow** | Rank number, avatar, name, value, medal for top 3 |
| **Tabs** | Horizontal tab bar, active indicator |
| **Dropdown** | Menu items, keyboard navigable |

---

## 5. Wireframes

### 5.1 Landing Page (index.html)

```
┌─────────────────────────────────────────────────────────┐
│  [Logo]  PixelForge                  [Login] [Play Now] │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌──────────────────┐  ┌──────────────────────────┐    │
│  │                  │  │                          │    │
│  │  Build your      │  │   ┌──────────────────┐   │    │
│  │  territory.      │  │   │  Login / Register │   │    │
│  │                  │  │   │  ┌────────────┐   │   │    │
│  │  Play match-3.   │  │   │  │ [form]     │   │   │    │
│  │  Earn gems.      │  │   │  │            │   │   │    │
│  │  Claim pixels.   │  │   │  │ [Submit]   │   │   │    │
│  │                  │  │   │  └────────────┘   │   │    │
│  │  [Play Now]      │  │   └──────────────────┘   │    │
│  └──────────────────┘  └──────────────────────────┘    │
│                                                         │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  How it works                                           │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐               │
│  │ 1. Play │  │ 2. Earn │  │ 3. Build│               │
│  │ Match-3 │  │  Gems   │  │ Territory│               │
│  └─────────┘  └─────────┘  └─────────┘               │
│                                                         │
├─────────────────────────────────────────────────────────┤
│  Footer                                                 │
└─────────────────────────────────────────────────────────┘
```

### 5.2 Home Dashboard (home.html)

```
┌─────────────────────────────────────────────────────────┐
│ ┌──────┐                                                │
│ │Logo  │  Good evening, Zenith.                         │
│ │      │  Your territory gained 134 pixels while away.  │
│ ├──────┤                                                │
│ │ Home │  ┌────────┐ ┌────────┐ ┌────────┐             │
│ │ Play │  │ Gems   │ │ Level  │ │Territory│             │
│ │ World│  │ 247    │ │ 12     │ │ 1,847  │             │
│ │Rankng│  │ ▲ +32  │ │ ████░░ │ │ ▲ +134 │             │
│ │Profil│  └────────┘ └────────┘ └────────┘             │
│ ├──────┤                                                │
│ │      │  ┌──────────────────┐ ┌──────────────────┐    │
│ │      │  │ Recent Activity  │ │ Leaderboard      │    │
│ │      │  │ • Placed pixel   │ │ 1. Zenith  12.4k │    │
│ │      │  │ • Earned 24 gems │ │ 2. Alex    11.2k │    │
│ │      │  │ • Achievement    │ │ 3. Maya    9.8k  │    │
│ │      │  │                  │ │                  │    │
│ │      │  └──────────────────┘ └──────────────────┘    │
│ │ ⚙ Set │                                              │
│ │ 🔑 Out│                                              │
│ └──────┘                                                │
└─────────────────────────────────────────────────────────┘
```

### 5.3 Play Page (play.html)

```
┌─────────────────────────────────────────────────────────┐
│ ← Back to Home                    Score: 1,247  Moves: 18│
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────────────┐  ┌──────────────────┐     │
│  │                         │  │ Boosters         │     │
│  │    ┌───┬───┬───┬───┐   │  │ ┌──────────────┐ │     │
│  │    │   │   │   │   │   │  │ │🔨 Hammer  x3 │ │     │
│  │    ├───┼───┼───┼───┤   │  │ │🔀 Shuffle x2 │ │     │
│  │    │   │   │   │   │   │  │ │⚡ +5 Moves x1│ │     │
│  │    ├───┼───┼───┼───┤   │  │ │💥 Color   x1 │ │     │
│  │    │   │ ◆ │   │   │   │  │ │⚡ Lightning x0│ │     │
│  │    ├───┼───┼───┼───┤   │  │ └──────────────┘ │     │
│  │    │   │   │   │   │   │  │                  │     │
│  │    ├───┼───┼───┼───┤   │  │ Objectives       │     │
│  │    │   │   │   │   │   │  │ Score 500 pts    │     │
│  │    ├───┼───┼───┼───┤   │  │ Make 3 combos    │     │
│  │    │   │   │   │   │   │  │ Use 1 booster    │     │
│  │    └───┴───┴───┴───┘   │  │                  │     │
│  │                         │  │ [Hint] [Shuffle]│     │
│  │  Combo: 3x  Score: +40  │  └──────────────────┘     │
│  └─────────────────────────┘                            │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### 5.4 World Page (world.html)

```
┌─────────────────────────────────────────────────────────┐
│ ← Home                    World Canvas     [Place Mode] │
├─────────────────────────────────────────────────────────┤
│ ┌───┐ ┌──────────────────────────────────────┐ ┌─────┐ │
│ │   │ │                                      │ │Info │ │
│ │ C │ │                                      │ │     │ │
│ │ O │ │         MAIN CANVAS                  │ │Pos: │ │
│ │ L │ │         (pan/zoom)                   │ │12,45│ │
│ │ O │ │                                      │ │Own: │ │
│ │ R │ │                                      │ │@zen │ │
│ │ S │ │                                      │ │     │ │
│ │   │ │                                      │ │Stats│ │
│ │ ──│ │                                      │ │2.4k │ │
│ │ sw│ │                                      │ │pix  │ │
│ │ at│ └──────────────────────────────────────┘ │32   │ │
│ │ ch│ ┌────────────────────┐                   │artst│ │
│ │ es│ │ Minimap            │  [Zoom: 100%]    │     │ │
│ │   │ │ ┌────────────────┐ │  [+][-][⟲]      │ │View │ │
│ └───┘ │ │  ● viewport     │ │                   │ │Over │ │
│       │ └────────────────┘ │                   │ │lays │ │
│       └────────────────────┘                   │ └─────┘ │
└─────────────────────────────────────────────────────────┘
```

### 5.5 Rankings Page (rankings.html)

```
┌─────────────────────────────────────────────────────────┐
│ ← Home                    Rankings                      │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  [Players] [Territory] [Weekly] [All Time]              │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ ┌────┬──────────────────────────┬──────────────┐│   │
│  │ │ �1 │ 🟣 Zenith      Lv.12   │    12,450    ││   │
│  │ ├────┼──────────────────────────┼──────────────┤│   │
│  │ │ 🥈 │ 🔵 Alex        Lv.11   │    11,200    ││   │
│  │ ├────┼──────────────────────────┼──────────────┤│   │
│  │ │ 🥉 │ 🟢 Maya        Lv.10   │     9,800    ││   │
│  │ ├────┼──────────────────────────┼──────────────┤│   │
│  │ │ 4  │ 🟡 Jordan      Lv.9    │     8,400    ││   │
│  │ ├────┼──────────────────────────┼──────────────┤│   │
│  │ │ 5  │ 🔴 Casey       Lv.8    │     7,200    ││   │
│  │ └────┴──────────────────────────┴──────────────┘│   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │ You: #12  🟣 Zenith  12,450 pts                │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

### 5.6 Profile Page (profile.html)

```
┌─────────────────────────────────────────────────────────┐
│ ← Home                    Profile                       │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  ┌─────────────────────────────────────────────────┐   │
│  │  🟣  Zenith                    Level 12        │   │
│  │  Avatar                     ████████░░ 75% XP  │   │
│  │  Member since Jan 2024                         │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────┐     │
│  │ Gems    │ │ Games   │ │ Pixels  │ │Territory│     │
│  │ 247     │ │ 89      │ │ 1,847   │ │ 1,203   │     │
│  │ earned  │ │ played  │ │ placed  │ │ owned   │     │
│  └─────────┘ └─────────┘ └─────────┘ └─────────┘     │
│                                                         │
│  Achievements (12/16)                                  │
│  ┌────┬────┬────┬────┬────┬────┬────┬────┐            │
│  │ 🔥 │ ⚡ │ 🏆 │ 💎 │ 🎯 │ 🌟 │    │    │            │
│  │Earn│Earn│Earn│Earn│Earn│Earn│Lock│Lock│            │
│  └────┴────┴────┴────┴────┴────┴────┴────┘            │
│                                                         │
│  Recent Games                                           │
│  ┌─────────────────────────────────────────────────┐   │
│  │ Score: 1,247  Combo: 5x  Gems: +12  2h ago    │   │
│  │ Score: 980    Combo: 3x  Gems: +9   5h ago    │   │
│  │ Score: 2,100  Combo: 8x  Gems: +21  1d ago    │   │
│  └─────────────────────────────────────────────────┘   │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## 6. File Architecture Plan

### New Frontend Structure

```
assets/
├── css/
│   ├── base/
│   │   ├── reset.css           # Global reset
│   │   ├── tokens.css          # Design tokens (colors, spacing, etc.)
│   │   └── typography.css      # Font, heading, body styles
│   ├── components/
│   │   ├── button.css
│   │   ├── card.css
│   │   ├── input.css
│   │   ├── badge.css
│   │   ├── avatar.css
│   │   ├── modal.css
│   │   ├── toast.css
│   │   ├── tooltip.css
│   │   ├── tabs.css
│   │   ├── table.css
│   │   ├── progress.css
│   │   ├── dropdown.css
│   │   └── skeleton.css
│   ├── layout/
│   │   ├── sidebar.css         # Desktop sidebar nav
│   │   ├── mobile-nav.css      # Bottom mobile nav
│   │   └── page.css            # Page layout wrapper
│   ├── pages/
│   │   ├── landing.css
│   │   ├── home.css
│   │   ├── play.css
│   │   ├── world.css
│   │   ├── rankings.css
│   │   └── profile.css
│   └── main.css                # Imports all above
├── js/
│   ├── core/
│   │   ├── api.js              # API client (fetch wrapper)
│   │   ├── auth.js             # Auth state management
│   │   ├── router.js           # Simple page router (optional)
│   │   └── events.js           # Event bus
│   ├── components/
│   │   ├── sidebar.js          # Sidebar behavior
│   │   ├── toast.js            # Toast notifications
│   │   ├── modal.js            # Modal manager
│   │   ├── tabs.js             # Tab component
│   │   └── dropdown.js         # Dropdown behavior
│   ├── pages/
│   │   ├── landing.js          # Auth forms
│   │   ├── home.js             # Dashboard logic
│   │   ├── play.js             # Game controller (wraps existing game engine)
│   │   ├── world.js            # Canvas controller
│   │   ├── rankings.js         # Rankings logic
│   │   └── profile.js          # Profile logic
│   └── utils.js                # Existing utils (keep)
├── game/                       # Keep existing game engine files
│   ├── game.js
│   ├── game-renderer.js
│   ├── game-animations.js
│   └── game-powerups.js
└── images/                     # (empty, SVGs inline)
```

### HTML Page Template

Every page will share this structure:

```html
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Page Title — PixelForge</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/main.css">
  <link rel="stylesheet" href="assets/css/pages/[page].css">
</head>
<body>
  <!-- Sidebar (desktop) -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">...</div>
    <nav class="sidebar-nav">...</nav>
    <div class="sidebar-footer">...</div>
  </aside>

  <!-- Mobile Bottom Nav -->
  <nav class="mobile-nav" id="mobileNav">...</nav>

  <!-- Main Content -->
  <main class="page" id="mainContent">
    <!-- Page-specific content -->
  </main>

  <!-- Toast Container -->
  <div class="toast-container" id="toastContainer"></div>

  <!-- Scripts -->
  <script src="assets/js/core/api.js"></script>
  <script src="assets/js/core/auth.js"></script>
  <script src="assets/js/components/sidebar.js"></script>
  <script src="assets/js/components/toast.js"></script>
  <script src="assets/js/pages/[page].js"></script>
</body>
</html>
```

---

## 7. Implementation Phases

### Phase 1: Design System Foundation
- Create `tokens.css` with all design tokens
- Create `reset.css` with modern CSS reset
- Create `typography.css` with Inter font and type scale
- Create base component CSS files (button, card, input, badge, avatar, modal, toast, tabs, table, progress, skeleton)
- Create layout CSS (sidebar, mobile-nav, page)

### Phase 2: Shared Components (JS)
- `api.js` — fetch wrapper with error handling, CSRF support
- `auth.js` — session check, user state, login/logout
- `sidebar.js` — active state, mobile toggle, collapse
- `toast.js` — show/hide toast notifications
- `modal.js` — open/close modals with focus trap

### Phase 3: Landing Page (index.html)
- Clean hero with auth card
- How-it-works section
- Responsive, accessible
- Wire to `api/auth.php?action=login|register`

### Phase 4: Home Dashboard (home.html)
- Greeting with time-of-day
- Stat cards (gems, level, territory)
- Activity feed
- Leaderboard snapshot
- CTA buttons to Play and World

### Phase 5: Play Page (play.html)
- Integrate existing game engine (game.js, renderer, animations, powerups)
- Redesigned HUD and layout
- Booster sidebar
- Game over modal
- Score submission flow

### Phase 6: World Page (world.html)
- Canvas rendering with zoom/pan
- Minimap
- Color picker sidebar
- Coordinate inspector
- Ownership overlay
- Activity feed

### Phase 7: Rankings Page (rankings.html)
- Tabbed interface (Players, Territory, Weekly, All Time)
- Ranked list with medals
- Player cards
- Search/filter

### Phase 8: Profile Page (profile.html)
- Profile header with avatar
- Stats grid
- Achievements grid
- Recent games
- XP progression

### Phase 9: Responsive & Accessibility
- Mobile-first responsive pass
- Keyboard navigation
- Focus management
- ARIA labels
- Reduced motion
- Screen reader testing

### Phase 10: Polish & Performance
- Animation refinement
- Loading states
- Error states
- Empty states
- Performance audit (60fps target)
