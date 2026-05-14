class SSEClient {
  constructor(url) {
    this.url = url;
    this.es = null;
    this.reconnectDelay = 1000;
    this.maxDelay = 30000;
    this.onPixelUpdate = null;
    this.connected = false;
  }

  connect() {
    this.es = new EventSource(this.url);

    this.es.onopen = () => {
      this.connected = true;
      this.reconnectDelay = 1000;
    };

    this.es.onmessage = (e) => {
      try {
        const data = JSON.parse(e.data);
        if (this.onPixelUpdate) this.onPixelUpdate(data);
      } catch (err) { /* ignore parse errors */ }
    };

    this.es.onerror = () => {
      this.connected = false;
      this.es.close();
      setTimeout(() => {
        this.reconnectDelay = Math.min(this.reconnectDelay * 2, this.maxDelay);
        this.connect();
      }, this.reconnectDelay);
    };
  }

  disconnect() {
    if (this.es) {
      this.es.close();
      this.es = null;
    }
    this.connected = false;
  }
}

export { SSEClient };