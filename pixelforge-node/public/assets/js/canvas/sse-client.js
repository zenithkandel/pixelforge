class SSEClient {
  constructor(api) {
    this.api = api;
    this.eventSource = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 5;
    this.reconnectDelay = 1000;
    this.subscribedChunks = [];
    this.onPixelUpdate = null;
    this.onGridReset = null;
    this.onPowerHourStart = null;
    this.onPowerHourEnd = null;
    this.onAchievement = null;
    this.connected = false;
  }

  connect(chunks = []) {
    this.subscribedChunks = chunks;
    this.createConnection();
  }

  createConnection() {
    const params = new URLSearchParams();
    if (this.subscribedChunks.length > 0) {
      params.set('chunks', this.subscribedChunks.join(','));
    }

    const url = `/api/grid/updates${params.toString() ? '?' + params.toString() : ''}`;
    
    this.eventSource = new EventSource(url);

    this.eventSource.onopen = () => {
      this.connected = true;
      this.reconnectAttempts = 0;
      this.updateConnectionStatus(true);
    };

    this.eventSource.addEventListener('pixel', (event) => {
      try {
        const data = JSON.parse(event.data);
        if (this.onPixelUpdate) {
          this.onPixelUpdate(data);
        }
      } catch (err) {
        console.error('Failed to parse pixel event:', err);
      }
    });

    this.eventSource.addEventListener('grid_reset', (event) => {
      try {
        const data = JSON.parse(event.data);
        if (this.onGridReset) {
          this.onGridReset(data);
        }
      } catch (err) {
        console.error('Failed to parse grid_reset event:', err);
      }
    });

    this.eventSource.addEventListener('power_hour_start', (event) => {
      try {
        const data = JSON.parse(event.data);
        if (this.onPowerHourStart) {
          this.onPowerHourStart(data);
        }
      } catch (err) {
        console.error('Failed to parse power_hour_start event:', err);
      }
    });

    this.eventSource.addEventListener('power_hour_end', (event) => {
      try {
        const data = JSON.parse(event.data);
        if (this.onPowerHourEnd) {
          this.onPowerHourEnd(data);
        }
      } catch (err) {
        console.error('Failed to parse power_hour_end event:', err);
      }
    });

    this.eventSource.addEventListener('achievement', (event) => {
      try {
        const data = JSON.parse(event.data);
        if (this.onAchievement) {
          this.onAchievement(data);
        }
      } catch (err) {
        console.error('Failed to parse achievement event:', err);
      }
    });

    this.eventSource.onerror = (err) => {
      this.connected = false;
      this.updateConnectionStatus(false);
      
      this.eventSource.close();
      
      if (this.reconnectAttempts < this.maxReconnectAttempts) {
        this.reconnectAttempts++;
        setTimeout(() => {
          this.createConnection();
        }, this.reconnectDelay * this.reconnectAttempts);
      }
    };
  }

  updateConnectionStatus(connected) {
    const statusEl = document.getElementById('connectionStatus');
    if (statusEl) {
      statusEl.classList.toggle('disconnected', !connected);
      statusEl.textContent = connected ? '●' : '○';
    }
  }

  disconnect() {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
      this.connected = false;
    }
  }

  updateSubscriptions(chunks) {
    this.subscribedChunks = chunks;
  }
}

window.SSEClient = SSEClient;