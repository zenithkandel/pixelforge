export class SSEClient {
    constructor() {
        this.eventSource = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.baseReconnectDelay = 1000;
        this.maxReconnectDelay = 30000;
        this.subscribedChunks = [];
        this.callback = null;
    }

    connect(callback) {
        this.callback = callback;
        this.establishConnection();
    }

    establishConnection() {
        const chunksParam = this.subscribedChunks.length > 0
            ? '&chunks=' + this.subscribedChunks.map(c => `${c.cx}_${c.cy}`).join(',')
            : '';

        const url = `/api/grid/updates.php?${chunksParam.substring(1)}`;

        try {
            this.eventSource = new EventSource(url);

            this.eventSource.onopen = () => {
                this.reconnectAttempts = 0;
            };

            this.eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    if (this.callback) {
                        this.callback(data);
                    }
                } catch (err) {
                    console.error('SSE parse error:', err);
                }
            };

            this.eventSource.onerror = () => {
                this.handleError();
            };
        } catch (err) {
            this.handleError();
        }
    }

    handleError() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }

        this.reconnectAttempts++;

        if (this.reconnectAttempts <= this.maxReconnectAttempts) {
            const delay = Math.min(
                this.baseReconnectDelay * Math.pow(2, this.reconnectAttempts - 1),
                this.maxReconnectDelay
            );

            setTimeout(() => {
                console.log(`SSE reconnect attempt ${this.reconnectAttempts}`);
                this.establishConnection();
            }, delay);
        } else {
            console.error('SSE max reconnect attempts reached');
        }
    }

    updateSubscriptions(chunks) {
        this.subscribedChunks = chunks;

        if (this.eventSource) {
            this.eventSource.close();
            this.establishConnection();
        }
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }
}