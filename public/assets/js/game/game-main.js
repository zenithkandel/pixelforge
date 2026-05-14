import { GameEngine } from './engine.js';
import { startGame, checkpointGame, submitGame } from '/assets/js/api.js';
import { showToast } from '/assets/js/ui.js';

const canvas = document.getElementById('game-canvas');
const startScreen = document.getElementById('game-start-screen');
const overScreen = document.getElementById('game-over-screen');
const pauseOverlay = document.getElementById('game-pause-overlay');

let engine = null;
let currentPxlBalance = 0;

async function initGame() {
  try {
    const meRes = await fetch('/api/user/me.php', { credentials: 'same-origin' });
    const meData = await meRes.json();
    if (meData.ok) currentPxlBalance = meData.data.pxl_balance || 0;
  } catch (e) { /* ignore */ }
}

document.getElementById('play-btn')?.addEventListener('click', startNewGame);
document.getElementById('replay-btn')?.addEventListener('click', startNewGame);
document.getElementById('resume-btn')?.addEventListener('click', () => engine?.togglePause());
document.getElementById('quit-btn')?.addEventListener('click', () => {
  engine?.stop();
  showStart();
});

async function startNewGame() {
  try {
    const res = await startGame();
    if (!res.ok) {
      showToast(res.message || 'Failed to start game', 'error');
      return;
    }
    const { session_id, seed, hmac } = res.data;

    window._checkpointFn = checkpointGame;
    window._submitFn = submitGame;

    startScreen.hidden = true;
    overScreen.hidden = true;
    canvas.hidden = false;
    pauseOverlay.hidden = true;

    engine = new GameEngine(canvas, seed, <?= isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0 ?>);
    await engine.start(session_id, seed, hmac);
  } catch (e) {
    showToast('Failed to start game. Please try again.', 'error');
  }
}

window._showGameOver = function(data) {
  canvas.hidden = true;
  overScreen.hidden = false;
  pauseOverlay.hidden = true;

  document.getElementById('go-score').textContent = Number(data.score).toLocaleString();
  document.getElementById('go-distance').textContent = data.distance + 'm';
  document.getElementById('go-tier').textContent = data.tier;

  const pxlArea = document.getElementById('go-pxl-area');
  const pxlEl = document.getElementById('go-pxl');
  const bestEl = document.getElementById('go-best');

  if (data.pxl > 0) {
    pxlArea.hidden = false;
    pxlEl.textContent = data.pxl;
  } else {
    pxlArea.hidden = true;
  }

  if (data.isBest) {
    bestEl.hidden = false;
  } else {
    bestEl.hidden = true;
  }
};

function showStart() {
  startScreen.hidden = false;
  overScreen.hidden = true;
  canvas.hidden = true;
  pauseOverlay.hidden = true;
}

initGame();