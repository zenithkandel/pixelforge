class ApiClient {
  constructor() {
    this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  async post(url, data) {
    const res = await fetch(url, {
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

  async get(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  async getBinary(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return {
      data: new Uint8Array(await res.arrayBuffer()),
      version: parseInt(res.headers.get('X-Chunk-Version') || '0'),
    };
  }
}

export const api = new ApiClient();

export async function claimAchievement(achievementId) {
  return api.post('/api/user/claim-achievement.php', { achievement_id: achievementId });
}

export async function getMe() {
  return api.get('/api/user/me.php');
}

export async function startGame() {
  return api.post('/api/game/start.php', {});
}

export async function checkpointGame(sessionId, score, lives, speedTier, hmac) {
  return api.post('/api/game/checkpoint.php', { session_id: sessionId, score, lives, speed_tier: speedTier, hmac });
}

export async function submitGame(sessionId, score, durationMs, hmac) {
  return api.post('/api/game/submit.php', { session_id: sessionId, score, duration_ms: durationMs, hmac });
}

export async function buyPixel(x, y, color) {
  return api.post('/api/grid/buy.php', { x, y, color });
}

export async function getChunk(cx, cy) {
  return api.getBinary(`/api/grid/chunk.php?cx=${cx}&cy=${cy}`);
}

export default api;