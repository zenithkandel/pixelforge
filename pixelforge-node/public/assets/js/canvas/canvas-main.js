class CanvasApp {
  constructor() {
    this.api = window.pixelforge.api;
    this.chunkCache = new ChunkCache();
    this.canvas = document.getElementById('pixelCanvas');
    this.minimap = document.getElementById('minimap');
    this.renderer = new GridRenderer(this.canvas, this.minimap, this.chunkCache);
    this.pixelBuyer = new PixelBuyer(this.api, this.renderer);
    this.sseClient = new SSEClient(this.api);

    this.zoom = 1;
    this.minZoom = 0.5;
    this.maxZoom = 4;
    this.currentTool = 'brush';
    this.isPanning = false;
    this.lastMousePos = { x: 0, y: 0 };
    this.isDrawing = false;

    this.sessionPixels = 0;
    this.resetTimer = null;

    this.init();
  }

  async init() {
    await this.loadSession();
    await this.loadInitialChunks();
    this.setupEventListeners();
    this.setupSSE();
    this.setupUI();
    this.startResetTimer();
    
    document.getElementById('loadingOverlay')?.classList.add('hidden');
  }

  async loadSession() {
    try {
      const session = await this.api.get('/grid/session');
      if (session?.data) {
        document.getElementById('themeDisplay').querySelector('.theme-name').textContent = 
          session.data.theme || 'Free Paint';
        document.getElementById('themeDesc').textContent = 
          session.data.description || '';
      }
    } catch (err) {
      console.error('Failed to load session:', err);
    }

    if (window.pixelforge.auth?.isLoggedIn) {
      const user = await this.api.get('/user/me');
      if (user?.data) {
        document.getElementById('pxlBalance').textContent = user.data.pxlBalance;
      }
    }
  }

  async loadInitialChunks() {
    const centerChunk = 6;
    await this.chunkCache.preloadChunks(centerChunk, centerChunk, 3, this.api);
    this.renderer.render();
  }

  setupEventListeners() {
    this.canvas.addEventListener('mousedown', (e) => this.handleMouseDown(e));
    this.canvas.addEventListener('mousemove', (e) => this.handleMouseMove(e));
    this.canvas.addEventListener('mouseup', () => this.handleMouseUp());
    this.canvas.addEventListener('mouseleave', () => this.handleMouseUp());
    this.canvas.addEventListener('wheel', (e) => this.handleWheel(e));

    this.canvas.addEventListener('contextmenu', (e) => e.preventDefault());

    this.minimap.addEventListener('click', (e) => this.handleMinimapClick(e));

    document.getElementById('zoomIn')?.addEventListener('click', () => this.zoomIn());
    document.getElementById('zoomOut')?.addEventListener('click', () => this.zoomOut());
    document.getElementById('zoomFit')?.addEventListener('click', () => this.zoomFit());

    document.querySelectorAll('.tool-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        this.currentTool = btn.dataset.tool;
      });
    });

    document.querySelectorAll('.color-swatch').forEach(swatch => {
      swatch.addEventListener('click', () => {
        document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('active'));
        swatch.classList.add('active');
        this.renderer.selectedColor = swatch.dataset.color;
      });
    });

    document.getElementById('customColor')?.addEventListener('input', (e) => {
      document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('active'));
      this.renderer.selectedColor = e.target.value.toUpperCase();
    });
  }

  setupSSE() {
    this.sseClient.onPixelUpdate = (data) => {
      this.renderer.applyPixelUpdate(data.x, data.y, data.color);
      
      if (data.gemBonus) {
        window.pixelforge.showToast(
          `${data.username} found a gem!`,
          'success'
        );
      }
    };

    this.sseClient.onGridReset = async (data) => {
      window.pixelforge.showToast(data.message, 'info', 5000);
      this.chunkCache.invalidateAll();
      await this.loadInitialChunks();
      await this.loadSession();
    };

    this.sseClient.onPowerHourStart = (data) => {
      window.pixelforge.showToast(data.message, 'success', 10000);
      document.getElementById('powerHourBanner')?.classList.remove('hidden');
    };

    this.sseClient.onPowerHourEnd = () => {
      window.pixelforge.showToast('Power Hour has ended!', 'info');
      document.getElementById('powerHourBanner')?.classList.add('hidden');
    };

    this.sseClient.onAchievement = (data) => {
      window.pixelforge.showToast(
        `🏆 Achievement: ${data.name}! +${data.reward} PXL`,
        'success',
        5000
      );
    };

    this.sseClient.connect([]);
  }

  setupUI() {
    this.loadLeaderboard();
    setInterval(() => this.loadLeaderboard(), 30000);
  }

  async loadLeaderboard() {
    try {
      const response = await this.api.get('/leaderboard/weekly-pixels');
      if (response?.data?.leaders) {
        const container = document.getElementById('leaderboardMini');
        container.innerHTML = response.data.leaders.slice(0, 5).map((leader, i) => `
          <div class="leader-item">
            <span class="rank">#${i + 1}</span>
            <span class="name">${this.escapeHtml(leader.username)}</span>
            <span class="count">${leader.pixel_count}</span>
          </div>
        `).join('');
      }
    } catch (err) {
      console.error('Failed to load leaderboard:', err);
    }
  }

  handleMouseDown(e) {
    if (e.button === 2 || e.button === 1) {
      this.isPanning = true;
      this.lastMousePos = { x: e.clientX, y: e.clientY };
      this.canvas.style.cursor = 'grabbing';
      return;
    }

    if (!window.pixelforge.auth?.isLoggedIn) {
      window.pixelforge.showToast('Please login to paint', 'warning');
      return;
    }

    this.isDrawing = true;
    this.handleDraw(e);
  }

  handleMouseMove(e) {
    const { x, y } = this.renderer.screenToGrid(e.clientX, e.clientY);

    document.getElementById('coordX').textContent = x;
    document.getElementById('coordY').textContent = y;
    document.getElementById('currentChunk').textContent = `${Math.floor(x/64)},${Math.floor(y/64)}`;

    this.renderer.setHover(x, y);

    if (this.isPanning) {
      const dx = e.clientX - this.lastMousePos.x;
      const dy = e.clientY - this.lastMousePos.y;
      this.renderer.pan(dx, dy);
      this.lastMousePos = { x: e.clientX, y: e.clientY };
      this.renderer.render();
      return;
    }

    if (this.isDrawing) {
      this.handleDraw(e);
    } else {
      this.renderer.render();
    }
  }

  handleMouseUp() {
    this.isPanning = false;
    this.isDrawing = false;
    this.canvas.style.cursor = 'crosshair';
  }

  async handleDraw(e) {
    const { x, y } = this.renderer.screenToGrid(e.clientX, e.clientY);

    if (x < 0 || x >= 800 || y < 0 || y >= 800) return;

    if (this.currentTool === 'brush') {
      await this.pixelBuyer.purchasePixel(x, y, this.renderer.selectedColor);
      this.sessionPixels++;
      document.getElementById('sessionPixels').textContent = this.sessionPixels;
    } else if (this.currentTool === 'eyedropper') {
      const color = this.pixelBuyer.eyedrop(x, y);
      document.getElementById('customColor').value = color;
    }
  }

  handleWheel(e) {
    e.preventDefault();
    const delta = e.deltaY > 0 ? -0.1 : 0.1;
    this.setZoom(this.zoom + delta);
  }

  handleMinimapClick(e) {
    const rect = this.minimap.getBoundingClientRect();
    const x = Math.floor((e.clientX - rect.left) / (160 / 800));
    const y = Math.floor((e.clientY - rect.top) / (160 / 800));
    
    this.renderer.offsetX = (400 - x) * this.renderer.pixelSize;
    this.renderer.offsetY = (400 - y) * this.renderer.pixelSize;
    this.renderer.render();
  }

  setZoom(value) {
    this.zoom = Math.max(this.minZoom, Math.min(this.maxZoom, value));
    this.renderer.setZoom(this.zoom);
    document.getElementById('zoomLevel').textContent = `${Math.round(this.zoom * 100)}%`;
  }

  zoomIn() {
    this.setZoom(this.zoom + 0.25);
  }

  zoomOut() {
    this.setZoom(this.zoom - 0.25);
  }

  zoomFit() {
    const container = document.getElementById('canvasContainer');
    const maxSize = Math.min(container.clientWidth, container.clientHeight) - 40;
    this.setZoom(maxSize / 800);
  }

  startResetTimer() {
    const updateTimer = () => {
      const now = new Date();
      const daysUntilSunday = 7 - now.getDay();
      const hoursUntilSunday = daysUntilSunday * 24 - now.getHours();
      const minutesUntilReset = hoursUntilSunday * 60 - now.getMinutes();
      const secondsUntilReset = minutesUntilReset * 60 - now.getSeconds();

      const h = Math.floor(secondsUntilReset / 3600);
      const m = Math.floor((secondsUntilReset % 3600) / 60);
      const s = secondsUntilReset % 60;

      const timerEl = document.getElementById('resetTimer');
      if (timerEl) {
        timerEl.textContent = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
      }
    };

    updateTimer();
    this.resetTimer = setInterval(updateTimer, 1000);
  }

  escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  destroy() {
    if (this.resetTimer) {
      clearInterval(this.resetTimer);
    }
    this.sseClient.disconnect();
  }
}

window.CanvasApp = CanvasApp;

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('pixelCanvas')) {
    window.canvasApp = new CanvasApp();
  }
});

window.addEventListener('beforeunload', () => {
  window.canvasApp?.destroy();
});