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
    this.currentTool = 'brush';
    this.isPanning = false;
    this.lastMousePos = { x: 0, y: 0 };
    this.isDrawing = false;
    this.sessionPixels = 0;
    this.resetTimer = null;

    this.init();
  }

  async init() {
    this.showLoading(true);
    
    try {
      await Promise.all([
        this.loadSession(),
        this.loadInitialChunks()
      ]);
      
      this.setupEventListeners();
      this.setupSSE();
      this.setupUI();
      this.startResetTimer();
      this.renderer.scheduleRender();
      
    } catch (err) {
      console.error('Init error:', err);
      window.pixelforge.showToast('Failed to load canvas', 'error');
    } finally {
      this.showLoading(false);
    }
  }

  showLoading(show) {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
      overlay.classList.toggle('hidden', !show);
    }
  }

  async loadSession() {
    try {
      const response = await this.api.get('/grid/session');
      if (response.ok && response.data) {
        const themeName = document.querySelector('.theme-name');
        const themeDesc = document.getElementById('themeDesc');
        if (themeName) themeName.textContent = response.data.theme || 'Free Paint';
        if (themeDesc) themeDesc.textContent = response.data.description || '';
      }
    } catch (err) {
      console.error('Session load error:', err);
    }

    if (window.pixelforge.auth?.isLoggedIn) {
      try {
        const user = await this.api.getUserMe();
        if (user.ok && user.data) {
          const balanceEl = document.getElementById('pxlBalance');
          if (balanceEl) balanceEl.textContent = user.data.pxlBalance || 0;
        }
      } catch (e) {}
    }
  }

  async loadInitialChunks() {
    await this.chunkCache.preloadChunks(6, 6, 6, this.api);
  }

  setupEventListeners() {
    this.canvas.addEventListener('mousedown', (e) => this.handleMouseDown(e));
    this.canvas.addEventListener('mousemove', (e) => this.handleMouseMove(e));
    this.canvas.addEventListener('mouseup', () => this.handleMouseUp());
    this.canvas.addEventListener('mouseleave', () => this.handleMouseUp());
    this.canvas.addEventListener('wheel', (e) => this.handleWheel(e), { passive: false });
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
      if (data.x !== undefined && data.y !== undefined && data.color) {
        this.renderer.applyPixelUpdate(data.x, data.y, data.color);
      }
      
      if (data.gemBonus) {
        window.pixelforge.showToast(`${data.username} found a gem! +${data.gemBonus} PXL`, 'success');
      }
    };

    this.sseClient.onGridReset = async () => {
      this.chunkCache.invalidateAll();
      this.renderer.invalidateAll();
      await this.loadInitialChunks();
      window.pixelforge.showToast('Canvas has been reset!', 'info');
    };

    this.sseClient.onPowerHourStart = () => {
      window.pixelforge.showToast('⚡ Power Hour Started! Free pixels!', 'success', 10000);
      document.getElementById('powerHourBanner')?.classList.remove('hidden');
    };

    this.sseClient.onPowerHourEnd = () => {
      window.pixelforge.showToast('Power Hour ended', 'info');
      document.getElementById('powerHourBanner')?.classList.add('hidden');
    };

    this.sseClient.onAchievement = (data) => {
      window.pixelforge.showToast(`🏆 ${data.name}! +${data.reward} PXL`, 'success', 5000);
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
      if (response.ok && response.data?.leaders) {
        const container = document.getElementById('leaderboardMini');
        if (container) {
          container.innerHTML = response.data.leaders.slice(0, 5).map((leader, i) => `
            <div class="leader-item">
              <span class="rank">#${i + 1}</span>
              <span class="name">${window.pixelforge.escapeHtml(leader.username)}</span>
              <span class="count">${leader.pixel_count}</span>
            </div>
          `).join('');
        }
      }
    } catch (err) {
      console.error('Leaderboard load error:', err);
    }
  }

  handleMouseDown(e) {
    if (e.button === 2) {
      this.isPanning = true;
      this.lastMousePos = { x: e.clientX, y: e.clientY };
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
    
    const coordX = document.getElementById('coordX');
    const coordY = document.getElementById('coordY');
    const chunkDisplay = document.getElementById('currentChunk');
    
    if (coordX) coordX.textContent = x;
    if (coordY) coordY.textContent = y;
    if (chunkDisplay) chunkDisplay.textContent = `${Math.floor(x/64)},${Math.floor(y/64)}`;

    this.renderer.setHover(x, y);

    if (this.isPanning) {
      const dx = e.clientX - this.lastMousePos.x;
      const dy = e.clientY - this.lastMousePos.y;
      const rect = this.canvas.getBoundingClientRect();
      this.canvas.parentElement.scrollLeft -= dx;
      this.canvas.parentElement.scrollTop -= dy;
      this.lastMousePos = { x: e.clientX, y: e.clientY };
      return;
    }

    if (this.isDrawing) {
      this.handleDraw(e);
    }
  }

  handleMouseUp() {
    this.isPanning = false;
    this.isDrawing = false;
  }

  async handleDraw(e) {
    const { x, y } = this.renderer.screenToGrid(e.clientX, e.clientY);

    if (x < 0 || x >= 800 || y < 0 || y >= 800) return;

    if (this.currentTool === 'brush') {
      const success = await this.pixelBuyer.purchasePixel(x, y, this.renderer.selectedColor);
      if (success) {
        this.sessionPixels++;
        const el = document.getElementById('sessionPixels');
        if (el) el.textContent = this.sessionPixels;
        
        const balanceEl = document.getElementById('pxlBalance');
        if (balanceEl && window.pixelforge.auth?.user) {
          window.pixelforge.auth.user.pxlBalance--;
          balanceEl.textContent = window.pixelforge.auth.user.pxlBalance;
        }
      }
    } else if (this.currentTool === 'eyedropper') {
      const color = this.pixelBuyer.eyedrop(x, y);
      this.renderer.selectedColor = color;
      const input = document.getElementById('customColor');
      if (input) input.value = color;
      document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('active'));
      window.pixelforge.showToast(`Color: ${color}`, 'info', 1500);
    }
  }

  handleWheel(e) {
    e.preventDefault();
    const delta = e.deltaY > 0 ? -0.2 : 0.2;
    this.zoom = Math.max(0.5, Math.min(4, this.zoom + delta));
    this.renderer.setZoom(this.zoom);
    const level = document.getElementById('zoomLevel');
    if (level) level.textContent = `${Math.round(this.zoom * 100)}%`;
  }

  handleMinimapClick(e) {
    const rect = this.minimap.getBoundingClientRect();
    const x = Math.floor((e.clientX - rect.left) / (160 / 800));
    const y = Math.floor((e.clientY - rect.top) / (160 / 800));
    
    const container = document.getElementById('canvasScroll');
    if (container) {
      container.scrollLeft = x * this.renderer.pixelSize - container.clientWidth / 2;
      container.scrollTop = y * this.renderer.pixelSize - container.clientHeight / 2;
    }
  }

  zoomIn() {
    this.zoom = Math.min(4, this.zoom + 0.5);
    this.renderer.setZoom(this.zoom);
    document.getElementById('zoomLevel').textContent = `${Math.round(this.zoom * 100)}%`;
  }

  zoomOut() {
    this.zoom = Math.max(0.5, this.zoom - 0.5);
    this.renderer.setZoom(this.zoom);
    document.getElementById('zoomLevel').textContent = `${Math.round(this.zoom * 100)}%`;
  }

  zoomFit() {
    const container = document.getElementById('canvasContainer');
    if (!container) return;
    const maxSize = Math.min(container.clientWidth, container.clientHeight) - 40;
    this.zoom = maxSize / 800;
    this.renderer.setZoom(this.zoom);
    document.getElementById('zoomLevel').textContent = `${Math.round(this.zoom * 100)}%`;
  }

  startResetTimer() {
    const updateTimer = () => {
      const now = new Date();
      const daysUntilSunday = 7 - now.getDay();
      const hoursUntilReset = daysUntilSunday * 24 - now.getHours() - (now.getDay() === 0 ? 24 : 0);
      const secondsTotal = hoursUntilReset * 3600 - now.getMinutes() * 60 - now.getSeconds();

      const h = Math.floor(secondsTotal / 3600);
      const m = Math.floor((secondsTotal % 3600) / 60);
      const s = secondsTotal % 60;

      const timerEl = document.getElementById('resetTimer');
      if (timerEl) {
        timerEl.textContent = `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
      }
    };

    updateTimer();
    this.resetTimer = setInterval(updateTimer, 1000);
  }

  destroy() {
    if (this.resetTimer) clearInterval(this.resetTimer);
    this.sseClient.disconnect();
  }
}

window.CanvasApp = CanvasApp;

document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('pixelCanvas')) {
    window.canvasApp = new CanvasApp();
  }
});