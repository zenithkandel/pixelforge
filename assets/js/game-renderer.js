const GEM_COLORS = [
    { name: 'Ruby', base: '#ef4444', light: '#fca5a5', dark: '#b91c1c', glow: '#ef4444' },
    { name: 'Sapphire', base: '#3b82f6', light: '#93c5fd', dark: '#1d4ed8', glow: '#3b82f6' },
    { name: 'Emerald', base: '#22c55e', light: '#86efac', dark: '#15803d', glow: '#22c55e' },
    { name: 'Topaz', base: '#f59e0b', light: '#fcd34d', dark: '#b45309', glow: '#f59e0b' },
    { name: 'Amethyst', base: '#a855f7', light: '#d8b4fe', dark: '#7e22ce', glow: '#a855f7' },
    { name: 'Diamond', base: '#06b6d4', light: '#67e8f9', dark: '#0e7490', glow: '#06b6d4' }
];

class GameRenderer {
    constructor(canvasId, game) {
        this.canvas = document.getElementById(canvasId);
        this.ctx = this.canvas.getContext('2d');
        this.game = game;

        this.gemSize = 60;
        this.padding = 4;
        this.boardOffset = { x: 0, y: 0 };

        this.selectedGem = null;
        this.hintGems = null;
        this.hintTimer = 0;
        this.particles = new AnimationManager(this);

        this.hoverGem = null;

        this.scorePopups = [];

        this.comboDisplay = { combo: 0, alpha: 0, scale: 1 };

        this.shake = { x: 0, y: 0, intensity: 0, decay: 0.9 };

        this.swapAnimation = null;

        this._setupEvents();
        this._resize();
        window.addEventListener('resize', () => this._resize());
    }

    _resize() {
        const container = this.canvas.parentElement;
        const size = Math.min(container.clientWidth, container.clientHeight, 520);
        const boardPixels = this.game.cols * (this.gemSize + this.padding) - this.padding;

        this.canvas.width = boardPixels;
        this.canvas.height = boardPixels;
        this.canvas.style.width = boardPixels + 'px';
        this.canvas.style.height = boardPixels + 'px';
    }

    _setupEvents() {
        this.canvas.addEventListener('click', (e) => {
            if (this.game.isProcessing) return;

            const rect = this.canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const col = Math.floor(x / (this.gemSize + this.padding));
            const row = Math.floor(y / (this.gemSize + this.padding));

            if (row >= 0 && row < this.game.rows && col >= 0 && col < this.game.cols) {
                this.game.select(row, col);
            }
        });

        this.canvas.addEventListener('mousemove', (e) => {
            const rect = this.canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            const col = Math.floor(x / (this.gemSize + this.padding));
            const row = Math.floor(y / (this.gemSize + this.padding));

            if (row >= 0 && row < this.game.rows && col >= 0 && col < this.game.cols) {
                this.hoverGem = { row, col };
                this.canvas.style.cursor = 'pointer';
            } else {
                this.hoverGem = null;
                this.canvas.style.cursor = 'default';
            }
        });

        this.canvas.addEventListener('mouseleave', () => {
            this.hoverGem = null;
        });
    }

    render(timestamp) {
        if (!this.game.board || !this.game.board.length || !this.game.board[0] || !this.game.board[0].length) return;
        const ctx = this.ctx;

        ctx.save();
        if (this.shake.intensity > 0.1) {
            ctx.translate(this.shake.x, this.shake.y);
            this.shake.x = (Math.random() - 0.5) * this.shake.intensity * 2;
            this.shake.y = (Math.random() - 0.5) * this.shake.intensity * 2;
            this.shake.intensity *= this.shake.decay;
        }

        ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        this._drawBoardBackground();

        for (let r = 0; r < this.game.rows; r++) {
            for (let c = 0; c < this.game.cols; c++) {
                const gem = this.game.board[r][c];
                if (gem) {
                    const x = c * (this.gemSize + this.padding);
                    const y = r * (this.gemSize + this.padding);

                    if (!this.particles.isAnimating(r, c)) {
                        this._drawGem(ctx, gem, x, y, timestamp);
                    }
                }
            }
        }

        if (this.game.selected) {
            this._drawSelection(ctx, timestamp);
        }

        if (this.hintGems) {
            this._drawHint(ctx, timestamp);
        }

        this.particles.render(ctx, timestamp);

        this._renderScorePopups(ctx, timestamp);

        this._renderCombo(ctx, timestamp);

        ctx.restore();

        if (this.hintTimer > 0) {
            this.hintTimer--;
            if (this.hintTimer === 0) {
                this.hintGems = null;
            }
        }
    }

    _drawBoardBackground() {
        const ctx = this.ctx;
        const size = this.gemSize + this.padding;

        for (let r = 0; r < this.game.rows; r++) {
            for (let c = 0; c < this.game.cols; c++) {
                const x = c * size;
                const y = r * size;

                ctx.fillStyle = (r + c) % 2 === 0 ? 'rgba(255,255,255,0.02)' : 'rgba(255,255,255,0.01)';
                ctx.beginPath();
                ctx.roundRect(x, y, this.gemSize, this.gemSize, 8);
                ctx.fill();
            }
        }
    }

    _drawGem(ctx, gem, x, y, timestamp) {
        const colors = GEM_COLORS[gem.type];
        if (!colors) return;

        const size = this.gemSize;
        const centerX = x + size / 2;
        const centerY = y + size / 2;
        const radius = size / 2 - 2;

        const isHovered = this.hoverGem && this.hoverGem.row === gem.row && this.hoverGem.col === gem.col;
        const hoverScale = isHovered ? 1.05 : 1.0;

        ctx.save();
        ctx.translate(centerX, centerY);
        ctx.scale(hoverScale, hoverScale);

        if (gem.special) {
            ctx.shadowColor = colors.glow;
            ctx.shadowBlur = 15;
        }

        const gradient = ctx.createLinearGradient(-radius, -radius, radius, radius);
        gradient.addColorStop(0, colors.light);
        gradient.addColorStop(0.5, colors.base);
        gradient.addColorStop(1, colors.dark);

        ctx.fillStyle = gradient;
        ctx.beginPath();
        ctx.roundRect(-radius, -radius, size - 4, size - 4, 10);
        ctx.fill();

        const shineGradient = ctx.createRadialGradient(-radius * 0.3, -radius * 0.3, 0, 0, 0, radius);
        shineGradient.addColorStop(0, 'rgba(255,255,255,0.4)');
        shineGradient.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = shineGradient;
        ctx.beginPath();
        ctx.roundRect(-radius, -radius, size - 4, size - 4, 10);
        ctx.fill();

        const shadowGradient = ctx.createLinearGradient(0, 0, 0, radius);
        shadowGradient.addColorStop(0, 'rgba(0,0,0,0)');
        shadowGradient.addColorStop(1, 'rgba(0,0,0,0.2)');
        ctx.fillStyle = shadowGradient;
        ctx.beginPath();
        ctx.roundRect(-radius, -radius, size - 4, size - 4, 10);
        ctx.fill();

        if (gem.special) {
            this._drawSpecialIndicator(ctx, gem.special, radius);
        }

        ctx.restore();
    }

    _drawSpecialIndicator(ctx, special, radius) {
        ctx.save();
        ctx.fillStyle = 'rgba(255,255,255,0.9)';
        ctx.strokeStyle = 'rgba(0,0,0,0.3)';
        ctx.lineWidth = 1.5;

        switch (special) {
            case 'rocket':
                ctx.beginPath();
                ctx.moveTo(0, -radius * 0.5);
                ctx.lineTo(-radius * 0.3, 0);
                ctx.lineTo(-radius * 0.1, 0);
                ctx.lineTo(-radius * 0.1, radius * 0.4);
                ctx.lineTo(radius * 0.1, radius * 0.4);
                ctx.lineTo(radius * 0.1, 0);
                ctx.lineTo(radius * 0.3, 0);
                ctx.closePath();
                ctx.fill();
                ctx.stroke();
                break;

            case 'bomb':
                ctx.beginPath();
                for (let i = 0; i < 8; i++) {
                    const angle = (i * Math.PI * 2) / 8 - Math.PI / 2;
                    const r = i % 2 === 0 ? radius * 0.45 : radius * 0.25;
                    const px = Math.cos(angle) * r;
                    const py = Math.sin(angle) * r;
                    if (i === 0) ctx.moveTo(px, py);
                    else ctx.lineTo(px, py);
                }
                ctx.closePath();
                ctx.fill();
                ctx.stroke();
                break;

            case 'colorblast':
                ctx.beginPath();
                ctx.moveTo(0, -radius * 0.45);
                ctx.lineTo(radius * 0.35, 0);
                ctx.lineTo(0, radius * 0.45);
                ctx.lineTo(-radius * 0.35, 0);
                ctx.closePath();
                ctx.fill();
                ctx.stroke();
                break;

            case 'nova':
                ctx.beginPath();
                ctx.arc(0, 0, radius * 0.25, 0, Math.PI * 2);
                ctx.fill();
                ctx.stroke();
                for (let i = 0; i < 6; i++) {
                    const angle = (i * Math.PI * 2) / 6;
                    ctx.beginPath();
                    ctx.moveTo(Math.cos(angle) * radius * 0.3, Math.sin(angle) * radius * 0.3);
                    ctx.lineTo(Math.cos(angle) * radius * 0.5, Math.sin(angle) * radius * 0.5);
                    ctx.stroke();
                }
                break;
        }

        ctx.restore();
    }

    _drawSelection(ctx, timestamp) {
        const { row, col } = this.game.selected;
        const x = col * (this.gemSize + this.padding);
        const y = row * (this.gemSize + this.padding);
        const pulse = Math.sin(timestamp / 200) * 0.1 + 1.0;

        ctx.save();
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 3;
        ctx.shadowColor = '#ffffff';
        ctx.shadowBlur = 10;
        ctx.globalAlpha = 0.8;

        ctx.beginPath();
        ctx.roundRect(x - 2, y - 2, this.gemSize + 4, this.gemSize + 4, 12);
        ctx.stroke();

        ctx.restore();
    }

    _drawHint(ctx, timestamp) {
        const pulse = Math.sin(timestamp / 300) * 0.3 + 0.7;

        this.hintGems.forEach(gem => {
            const x = gem.col * (this.gemSize + this.padding);
            const y = gem.row * (this.gemSize + this.padding);

            ctx.save();
            ctx.globalAlpha = pulse;
            ctx.strokeStyle = '#fbbf24';
            ctx.lineWidth = 2;
            ctx.setLineDash([4, 4]);
            ctx.lineDashOffset = -timestamp / 50;

            ctx.beginPath();
            ctx.roundRect(x - 2, y - 2, this.gemSize + 4, this.gemSize + 4, 12);
            ctx.stroke();

            ctx.restore();
        });
    }

    showHint() {
        this.hintGems = this.game.findHint();
        this.hintTimer = 180;
    }

    triggerShake(intensity = 5) {
        this.shake.intensity = intensity;
    }

    addScorePopup(score, x, y) {
        this.scorePopups.push({
            text: '+' + score,
            x, y,
            alpha: 1,
            vy: -2,
            life: 60
        });
    }

    _renderScorePopups(ctx, timestamp) {
        this.scorePopups = this.scorePopups.filter(p => {
            p.y += p.vy;
            p.alpha -= 1 / p.life;
            p.life--;

            if (p.life <= 0) return false;

            ctx.save();
            ctx.globalAlpha = p.alpha;
            ctx.fillStyle = '#fbbf24';
            ctx.font = 'bold 16px Inter';
            ctx.textAlign = 'center';
            ctx.fillText(p.text, p.x, p.y);
            ctx.restore();

            return true;
        });
    }

    showCombo(combo) {
        this.comboDisplay = {
            combo,
            alpha: 1,
            scale: 1.5,
            targetScale: 1
        };
    }

    _renderCombo(ctx, timestamp) {
        if (this.comboDisplay.alpha <= 0) return;

        this.comboDisplay.alpha -= 0.015;
        this.comboDisplay.scale = Utils.lerp(this.comboDisplay.scale, this.comboDisplay.targetScale, 0.1);

        ctx.save();
        ctx.globalAlpha = this.comboDisplay.alpha;
        ctx.fillStyle = '#fbbf24';
        ctx.font = `bold ${24 * this.comboDisplay.scale}px Inter`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.shadowColor = '#fbbf24';
        ctx.shadowBlur = 20;

        ctx.fillText(`${this.comboDisplay.combo}x COMBO!`, this.canvas.width / 2, 30);
        ctx.restore();
    }
}
