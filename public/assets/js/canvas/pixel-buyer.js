export class PixelBuyer {
    constructor() {
        this.pendingPixel = null;
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    setPendingPixel(x, y, color) {
        this.pendingPixel = { x, y, color };
    }

    clearPendingPixel() {
        this.pendingPixel = null;
    }

    async purchase(x, y, color) {
        if (!this.pendingPixel) {
            return { success: false, error: 'no_pending_pixel' };
        }

        if (this.pendingPixel.x !== x || this.pendingPixel.y !== y || this.pendingPixel.color !== color) {
            return { success: false, error: 'pixel_changed' };
        }

        try {
            const response = await fetch('/api/grid/buy.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken
                },
                body: JSON.stringify({
                    x: x,
                    y: y,
                    color: color,
                    csrf_token: this.csrfToken
                })
            });

            const result = await response.json();

            if (result.ok) {
                this.clearPendingPixel();
                return {
                    success: true,
                    newBalance: result.data.new_balance,
                    chunkVersion: result.data.chunk_version
                };
            } else {
                return {
                    success: false,
                    error: result.error,
                    message: result.message
                };
            }
        } catch (err) {
            console.error('Purchase failed:', err);
            return { success: false, error: 'network_error' };
        }
    }

    updateCsrfToken(token) {
        this.csrfToken = token;
    }
}