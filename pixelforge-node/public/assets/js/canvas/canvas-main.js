class CanvasApp {
  constructor() {
    this.api = window.pixelforge.api;
    this.chunkCache = new ChunkCache();
    this.canvas = document.getElementById('pixelCanvas');
    this.minimap = document.getElementById('minimap');
    this.renderer = new GridRenderer(this.canvas, this.minimap, this.chunkCache);
    this.pixelBuyer = new PixelBuyer(this.api, this.renderer);
    this.sseClient = new SSEClient(this.api);

    this.currentTool = 'brush';
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
      const response = await this.api.getSession();
      if (response.ok && response.data) {
        const themeName = document.querySelector('.theme-name');
        const themeDesc = document.getElementById('themeDesc');
        if (themeName) themeName.textContent = response.data.theme || 'Free Paint';
        if (themeDesc) themeDesc.textContent = response.data.description || '';
      }
    } catch (err) {
      console.error('Session error:', err);
    }

    if (window.pixelforge.auth?.isLoggedIn) {
      try {
        const user = await window.pixelforge.api.getUserMe();
        if (user.ok && user.data) {
          const balanceEl = document.getElementById('pxlBalance');
          if (balanceEl) balanceEl.textContent = user.data.pxlBalance || 0;
        }
      } catch (e) {}
    }
  }

  async loadInitialChunks() {
    await this.chunkCache.preloadChunks(0, 0, 12, this.api);
  }

  setupEventListeners() {
    this.canvas.addEventListener('mousedown', (e) => this.handleMouseDown(e));
    this.canvas.addEventListener('mousemove', (e) => this.handleMouseMove(e));
    this.canvas.addEventListener('mouseup', () => this.handleMouseUp());
    this.canvas.addEventListener('mouseleave', () => this.handleMouseUp());
    this.canvas.addEventListener('wheel', (e) => this.handleWheel(e), { passive: false });

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
      console.error('Leaderboard error:', err);
    }
  }

  handleMouseDown(e) {
    if (e.button !== 0) return;

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
    
    if (coordX) coordX.textContent = Math.max(0, Math.min(799, x));
    if (coordY) coordY.textContent = Math.max(0, Math.min(799, y));
    if (chunkDisplay) chunkDisplay.textContent = `${Math.floor(Math.max(0, Math.min(799, x))/64},${Math.floor(Math.max(0, Math.min(799, y))/64)}`;

    this.renderer.setHover(x, y);

    if (this.isDrawing) {
      this.handleDraw(e);
    }
  }

  handleMouseUp() {
    this.isDrawing = false;
  }

  async handleDraw(e) {
    const { x, y } = this.renderer.screenToGrid(e.clientX, e.clientY);

    if (x < 0 || x >= 800 || y < 0 || y >= 800) return;

    if (this.currentTool === 'brush') {
      const success = await this.pixelBuyer.purchasePixel(x, y, this.renderer.selectedColor);
      if (success) {
        this.sessionPixels++;
        this.updatePixelCount(this.sessionPixels);
        this.updateBalanceDisplay();
      }
    } else if (this.currentTool === 'eyedropper') {
      const color = this.pixelBuyer.eyedrop(x, y);
      this.renderer.selectedColor = color;
      const input = document.getElementById('customColor');
      if (input) input.value = color;
      document.querySelectorAll('.color-swatch').forEach(s => s.classList.remove('active'));
    }
  }

  updatePixelCount(count) {
    const el = document.getElementById('sessionPixels');
    if (el) el.textContent = count;
  }

  updateBalanceDisplay() {
    const balanceEl = document.getElementById('pxlBalance');
    const navBalanceEl = document.getElementById('navBalance');
    
    if (window.pixelforge.auth?.user) {
      window.pixelforge.auth.user.pxlBalance = Math.max(0, (window.pixelforge.auth.user.pxlBalance || 100) - 1);
      if (balanceEl) balanceEl.textContent = window.pixelforge.auth.user.pxlBalance;
      if (navBalanceEl) navBalanceEl.textContent = window.pixelforge.auth.user.pxlBalance;
    }
  }

  handleWheel(e) {
    e.preventDefault();
    const delta = e.deltaY > 0 ? -0.15 : 0.15;
    const newZoom = Math.max(0.5, Math.min(4, this.renderer.getZoom() + delta));
    this.renderer.setZoom(newZoom);
    const level = document.getElementById('zoomLevel');
    if (level) level.textContent = `${Math.round(newZoom * 100)}%`;
  }

  handleMinimapClick(e) {
    const rect = this.minimap.getBoundingClientRect();
    const x = Math.floor((e.clientX - rect.left) / (160 / 800));
    const y = Math.floor((e.clientY - rect.top) / (160 / 800));
    
    const container = document.getElementById('canvasScroll');
    if (container) {
      container.scrollLeft = x * this.renderer.getZoom() - container.clientWidth / 2;
      container.scrollTop = y * this.renderer.getZoom() - container.clientHeight / 2;
    }
  }

  zoomIn() {
    const newZoom = Math.min(4, this.renderer.getZoom() + 0.5);
    this.renderer.setZoom(newZoom);
    document.getElementById('zoomLevel').textContent = `${Math.round(newZoom * 100)}%`;
  }

  zoomOut() {
    const newZoom = Math.max(0.5, this.renderer.getZoom() - 0.5);
    this.renderer.setZoom(newZoom);
    document.getElementById('zoomLevel').textContent = `${Math.round(newZoom * 100)}%`;
  }

  zoomFit() {
    const container = document.getElementById('canvasContainer');
    if (!container) return;
    const maxSize = Math.min(container.clientWidth, container.clientHeight) - 40;
    const newZoom = maxSize / 800;
    this.renderer.setZoom(newZoom);
    document.getElementById('zoomLevel').textContent = `${Math.round(newZoom * 100)}%`;
  }

  startResetTimer() {
    const updateTimer = () => {
      const now = new Date();
      const daysUntilSunday = 7 - now.getDay() || 7;
      const hoursUntilReset = daysUntilSunday * 24 - now.getHours() - (now.getDay() === 0 ? 24 : 0);
      const secondsTotal = Math.max(0, hoursUntilReset * 3600 - now.getMinutes() * 60 - now.getSeconds());

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