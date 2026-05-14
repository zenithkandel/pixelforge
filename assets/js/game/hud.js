// HUD (Heads-Up Display) rendering and updates
export class HUDRenderer {
    constructor(canvas) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
    }

    drawHUD(score, lives, combo, pxl, speedTier) {
        const y = 30;
        const x = 20;

        this.ctx.fillStyle = 'rgba(0, 0, 0, 0.5)';
        this.ctx.fillRect(x, y - 20, 300, 40);

        this.ctx.fillStyle = '#00F5FF';
        this.ctx.font = '16px JetBrains Mono';

        let hearts = '❤️'.repeat(Math.max(0, lives));
        this.ctx.fillText(`${hearts}`, x + 10, y + 10);
        this.ctx.fillText(`SCORE: ${score}`, x + 100, y + 10);
        this.ctx.fillText(`x${combo}`, x + 220, y + 10);
    }

    drawComboPopup(x, y, multiplier) {
        this.ctx.fillStyle = this.getComboColor(multiplier);
        this.ctx.font = 'bold 20px JetBrains Mono';
        this.ctx.fillText(`x${multiplier}`, x, y);
    }

    getComboColor(multiplier) {
        if (multiplier >= 4) return '#FFFFFF';
        if (multiplier >= 3) return '#FF0000';
        if (multiplier >= 2) return '#FFAA00';
        if (multiplier >= 1.5) return '#FFFF00';
        return '#00FF00';
    }
}
