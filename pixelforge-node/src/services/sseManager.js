const EventEmitter = require('events');

class SSEManager extends EventEmitter {
  constructor() {
    super();
    this.connections = new Map();
    this.subscriptions = new Map();
    this.heartbeatInterval = null;
    this.maxConnections = 500;
  }

  addConnection(id, res, subscribedChunks = []) {
    if (this.connections.size >= this.maxConnections) {
      const oldestKey = this.connections.keys().next().value;
      this.removeConnection(oldestKey);
    }

    res.writeHead(200, {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache',
      'Connection': 'keep-alive',
      'X-Accel-Buffering': 'no',
      'Access-Control-Allow-Origin': '*'
    });

    res.write(':ok\n\n');
    res.flushHeaders?.();

    this.connections.set(id, res);
    this.subscriptions.set(id, subscribedChunks);

    res.on('close', () => {
      this.removeConnection(id);
    });

    if (!this.heartbeatInterval) {
      this.startHeartbeat();
    }

    this.emit('connection', id);
  }

  removeConnection(id) {
    this.connections.delete(id);
    this.subscriptions.delete(id);

    if (this.connections.size === 0 && this.heartbeatInterval) {
      this.stopHeartbeat();
    }

    this.emit('disconnection', id);
  }

  startHeartbeat() {
    this.heartbeatInterval = setInterval(() => {
      for (const [id, res] of this.connections) {
        try {
          res.write(':heartbeat\n\n');
        } catch (err) {
          this.removeConnection(id);
        }
      }
    }, 30000);
  }

  stopHeartbeat() {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }
  }

  broadcast(event, data) {
    const payload = `event: ${event}\ndata: ${JSON.stringify(data)}\n\n`;
    const filteredPayload = `event: ${event}\ndata: ${JSON.stringify({ type: event, ...data })}\n\n`;

    if (event === 'pixel') {
      const cx = data.cx;
      for (const [id, res] of this.connections) {
        const subscribed = this.subscriptions.get(id);
        if (!subscribed || subscribed.length === 0 || subscribed.includes(cx.toString()) || subscribed.includes(`${cx}`)) {
          try {
            res.write(filteredPayload);
          } catch (err) {
            this.removeConnection(id);
          }
        }
      }
    } else {
      for (const [id, res] of this.connections) {
        try {
          res.write(filteredPayload);
        } catch (err) {
          this.removeConnection(id);
        }
      }
    }

    this.emit('broadcast', event, data);
  }

  sendToUser(username, event, data) {
    const res = this.connections.get(username);
    if (res) {
      try {
        res.write(`event: ${event}\ndata: ${JSON.stringify({ type: event, ...data })}\n\n`);
      } catch (err) {
        this.removeConnection(username);
      }
    }
  }

  getStats() {
    return {
      totalConnections: this.connections.size,
      maxConnections: this.maxConnections,
      active: this.heartbeatInterval !== null
    };
  }
}

const sseManager = new SSEManager();

module.exports = sseManager;