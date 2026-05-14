export class GridRenderer {
    constructor(ctx, chunkSize) {
        this.ctx = ctx;
        this.chunkSize = chunkSize;
    }

    renderChunk(chunkData, screenX, screenY, zoom) {
        if (!chunkData || chunkData.length !== this.chunkSize * this.chunkSize * 3) {
            this.renderPlaceholder(screenX, screenY, zoom);
            return;
        }

        const imageData = new ImageData(
            new Uint8ClampedArray(chunkData),
            this.chunkSize,
            this.chunkSize
        );

        this.ctx.putImageData(
            imageData,
            Math.floor(screenX),
            Math.floor(screenY),
            0,
            0,
            Math.floor(this.chunkSize * zoom),
            Math.floor(this.chunkSize * zoom)
        );
    }

    renderPlaceholder(screenX, screenY, zoom) {
        const size = this.chunkSize * zoom;
        this.ctx.fillStyle = '#FFFFFF';
        this.ctx.fillRect(screenX, screenY, size, size);

        this.ctx.strokeStyle = 'rgba(200, 200, 200, 0.5)';
        this.ctx.lineWidth = 1;
        this.ctx.strokeRect(screenX, screenY, size, size);
    }
}