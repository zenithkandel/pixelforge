class ApiClient {
  constructor() {
    this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  url(path) {
    return API_BASE + path;
  }

  async post(path, data) {
    const res = await fetch(this.url(path), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': this.csrfToken,
      },
      body: JSON.stringify(data),
      credentials: 'same-origin',
    });
    if (!res.ok && res.status !== 422 && res.status !== 429) {
      throw new Error(`HTTP ${res.status}`);
    }
    return res.json();
  }

  async get(path) {
    const res = await fetch(this.url(path), { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  async getBinary(path) {
    const res = await fetch(this.url(path), { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return {
      data: new Uint8Array(await res.arrayBuffer()),
      version: parseInt(res.headers.get('X-Chunk-Version') || '0'),
    };
  }
}

const API_BASE = (function () {
  const meta = document.querySelector('meta[name="base-url"]');
  if (meta) return meta.content;
  const scripts = document.querySelectorAll('script[src]');
  for (const s of scripts) {
    const m = s.src.match(/\/assets\/js\/(?:canvas\/|game\/|)([^/]+\.js)$/);
    if (m) {
      const idx = s.src.indexOf('/assets/js/');
      return s.src.slice(0, idx + '/assets/'.length);
    }
  }
  return '';
})();

export const api = new ApiClient();

export async function claimAchievement(achievementId) {
  return api.post('api/user/claim-achievement.php', { achievement_id: achievementId });
}

export async function getMe() {
  return api.get('api/user/me.php');
}

export async function startGame() {
  return api.post('api/game/start.php', {});
}

export async function checkpointGame(sessionId, score, lives, speedTier, hmac) {
  return api.post('api/game/checkpoint.php', { session_id: sessionId, score, lives, speed_tier: speedTier, hmac });
}

export async function submitGame(sessionId, score, durationMs, hmac) {
  return api.post('api/game/submit.php', { session_id: sessionId, score, duration_ms: durationMs, hmac });
}

export async function buyPixel(x, y, color) {
  return api.post('api/grid/buy.php', { x, y, color });
}

export async function getChunk(cx, cy) {
  return api.getBinary(`api/grid/chunk.php?cx=${cx}&cy=${cy}`);
}

export { API_BASE };
export default api;