export class MiniMap {
    constructor() {
        this.canvas = null;
        this.ctx = null;
        this.viewport = null;
        this.chunkCache = null;
        this.miniMapScale = 200 / 800;
    }

    init(chunkCache) {
        this.canvas = document.getElementById('miniMapCanvas');
        this.ctx = this.canvas.getContext('2d');
        this.viewport = document.getElementById('miniMapViewport');
        this.chunkCache = chunkCache;

        this.canvas.width = 200;
        this.canvas.height = 200;

        this.render();
    }

    update(viewX, viewY, viewWidth, viewHeight) {
        const mapWidth = 200;
        const mapHeight = 200;

        const vpLeft = viewX * this.miniMapScale;
        const vpTop = viewY * this.miniMapScale;
        const vpWidth = viewWidth * this.miniMapScale;
        const vpHeight = viewHeight * this.miniMapScale;

        this.viewport.style.left = Math.max(0, vpLeft) + 'px';
        this.viewport.style.top = Math.max(0, vpTop) + 'px';
        this.viewport.style.width = Math.min(mapWidth - vpLeft, vpWidth) + 'px';
        this.viewport.style.height = Math.min(mapHeight - vpTop, vpHeight) + 'px';
    }

    render() {
        this.ctx.fillStyle = '#FFFFFF';
        this.ctx.fillRect(0, 0, 200, 200);

        const ctx = this.ctx;
        const scale = 200 / 800;

        ctx.fillStyle = '#888888';

        for (let cy = 0; cy < 12; cy++) {
            for (let cx = 0; cx < 12; cx++) {
                const chunkData = this.chunkCache?.getChunk(cx, cy);
                if (chunkData) {
                    for (let py = 0; py < 64; py += 8) {
                        for (let px = 0; px < 64; px += 8) {
                            const offset = ((py * 64) + px) * 3;
                            const r = chunkData[offset];
                            const g = chunkData[offset + 1];
                            const b = chunkData[offset + 2];

                            if (r !== 255 || g !== 255 || b !== 255) {
                                ctx.fillStyle = `rgb(${r},${g},${b})`;
                                ctx.fillRect(
                                    (cx * 64 + px) * scale,
                                    (cy * 64 + py) * scale,
                                    8 * scale,
                                    8 * scale
                                );
                            }
                        }
                    }
                }
            }
        }

        ctx.strokeStyle = 'rgba(0, 0, 0, 0.1)';
        ctx.lineWidth = 0.5;

        for (let i = 0; i <= 8; i++) {
            ctx.beginPath();
            ctx.moveTo(i * 25, 0);
            ctx.lineTo(i * 25, 200);
            ctx.stroke();

            ctx.beginPath();
            ctx.moveTo(0, i * 25);
            ctx.lineTo(200, i * 25);
            ctx.stroke();
        }
    }
}