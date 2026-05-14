// Game Renderer
export class GameRenderer {
    constructor(canvas) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.width = canvas.width;
        this.height = canvas.height;
    }

    clear() {
        // Draw background (3-layer parallax)
        const gradient = this.ctx.createLinearGradient(0, 0, 0, this.height);
        gradient.addColorStop(0, '#0A0A1A');
        gradient.addColorStop(1, '#1A1A2E');
        this.ctx.fillStyle = gradient;
        this.ctx.fillRect(0, 0, this.width, this.height);

        // Draw ground
        this.ctx.fillStyle = '#111122';
        this.ctx.fillRect(0, this.height * 0.75, this.width, this.height * 0.25);

        // Draw ground border
        this.ctx.strokeStyle = '#00F5FF';
        this.ctx.lineWidth = 2;
        this.ctx.strokeRect(0, this.height * 0.75 - 2, this.width, 2);
    }

    drawCharacter(x, y, powered = false) {
        this.ctx.fillStyle = powered ? '#FFD700' : '#00F5FF';
        this.ctx.fillRect(x, y, 16, 16);

        // Draw eye
        this.ctx.fillStyle = '#FFFFFF';
        this.ctx.fillRect(x + 8, y + 4, 2, 2);
    }

    drawObstacle(x, y, type) {
        this.ctx.fillStyle = '#FF1493';
        this.ctx.fillRect(x, y, 16, 16);
    }

    drawShard(x, y, color = '#888888') {
        this.ctx.fillStyle = color;
        this.ctx.beginPath();
        this.ctx.moveTo(x + 4, y);
        this.ctx.lineTo(x + 8, y + 4);
        this.ctx.lineTo(x + 4, y + 8);
        this.ctx.lineTo(x, y + 4);
        this.ctx.closePath();
        this.ctx.fill();
    }

    drawText(text, x, y, color = '#FFFFFF', size = 16) {
        this.ctx.fillStyle = color;
        this.ctx.font = `${size}px 'JetBrains Mono'`;
        this.ctx.fillText(text, x, y);
    }
}
