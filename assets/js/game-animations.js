class AnimationManager {
    constructor(renderer) {
        this.renderer = renderer;
        this.particles = [];
        this.gemAnimations = [];
        this.animatingGems = new Set();
    }

    isAnimating(row, col) {
        return this.animatingGems.has(`${row},${col}`);
    }

    spawnRemovalParticles(matches, gemSize) {
        const ctx = this.renderer.ctx;

        matches.forEach(m => {
            const x = m.col * (gemSize + 4) + gemSize / 2;
            const y = m.row * (gemSize + 4) + gemSize / 2;
            const gem = m.gem;
            const color = gem ? GEM_COLORS[gem.type]?.base : '#ffffff';

            const count = Utils.randInt(8, 12);
            for (let i = 0; i < count; i++) {
                const angle = (i / count) * Math.PI * 2 + (Math.random() - 0.5) * 0.5;
                const speed = Utils.randInt(2, 5);
                const size = Utils.randInt(2, 5);

                this.particles.push({
                    type: 'circle',
                    x, y,
                    vx: Math.cos(angle) * speed,
                    vy: Math.sin(angle) * speed - 2,
                    size,
                    color,
                    alpha: 1,
                    decay: Utils.randFloat(0.02, 0.04),
                    gravity: 0.1,
                    life: 1
                });
            }

            this.particles.push({
                type: 'ring',
                x, y,
                radius: 5,
                maxRadius: gemSize * 0.6,
                color,
                alpha: 0.8,
                decay: 0.03,
                life: 1
            });
        });
    }

    spawnSpecialEffect(type, row, col, gemSize) {
        const x = col * (gemSize + 4) + gemSize / 2;
        const y = row * (gemSize + 4) + gemSize / 2;

        switch (type) {
            case 'rocket':
                this._spawnRocketTrail(x, y, gemSize);
                break;
            case 'bomb':
                this._spawnBombExplosion(x, y, gemSize);
                break;
            case 'colorblast':
                this._spawnColorBlast(x, y, gemSize);
                break;
            case 'nova':
                this._spawnNovaExplosion(x, y, gemSize);
                break;
        }
    }

    _spawnRocketTrail(x, y, gemSize) {
        for (let i = 0; i < 20; i++) {
            this.particles.push({
                type: 'circle',
                x: x + (Math.random() - 0.5) * 10,
                y: y + (Math.random() - 0.5) * 10,
                vx: (Math.random() - 0.5) * 0.5,
                vy: Utils.randFloat(-8, -3),
                size: Utils.randInt(2, 4),
                color: '#ffffff',
                alpha: 1,
                decay: 0.02,
                gravity: 0,
                life: 1
            });
        }

        this.particles.push({
            type: 'flash',
            x, y,
            radius: gemSize,
            color: '#ffffff',
            alpha: 0.8,
            decay: 0.05,
            life: 1
        });
    }

    _spawnBombExplosion(x, y, gemSize) {
        for (let i = 0; i < 24; i++) {
            const angle = (i / 24) * Math.PI * 2;
            const speed = Utils.randInt(3, 7);

            this.particles.push({
                type: 'circle',
                x, y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                size: Utils.randInt(2, 6),
                color: i % 2 === 0 ? '#fbbf24' : '#ef4444',
                alpha: 1,
                decay: 0.02,
                gravity: 0.05,
                life: 1
            });
        }

        this.particles.push({
            type: 'ring',
            x, y,
            radius: 5,
            maxRadius: gemSize * 1.5,
            color: '#fbbf24',
            alpha: 1,
            decay: 0.04,
            life: 1
        });
    }

    _spawnColorBlast(x, y, gemSize) {
        const colors = ['#ef4444', '#3b82f6', '#22c55e', '#f59e0b', '#a855f7', '#06b6d4'];

        for (let i = 0; i < 30; i++) {
            const angle = Math.random() * Math.PI * 2;
            const speed = Utils.randInt(2, 6);

            this.particles.push({
                type: 'circle',
                x, y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                size: Utils.randInt(3, 6),
                color: Utils.randItem(colors),
                alpha: 1,
                decay: 0.015,
                gravity: 0,
                life: 1,
                rainbow: true
            });
        }
    }

    _spawnNovaExplosion(x, y, gemSize) {
        for (let i = 0; i < 40; i++) {
            const angle = Math.random() * Math.PI * 2;
            const speed = Utils.randInt(1, 10);

            this.particles.push({
                type: 'circle',
                x, y,
                vx: Math.cos(angle) * speed,
                vy: Math.sin(angle) * speed,
                size: Utils.randInt(2, 7),
                color: Utils.randItem(['#ffffff', '#fbbf24', '#f472b6']),
                alpha: 1,
                decay: 0.01,
                gravity: 0.02,
                life: 1,
                trail: Math.random() > 0.7
            });
        }
    }

    animateGemFall(gem, fromRow, toRow, col, gemSize, callback) {
        const key = `${toRow},${col}`;
        this.animatingGems.add(key);

        const fromY = fromRow * (gemSize + 4);
        const toY = toRow * (gemSize + 4);
        const duration = Math.abs(toRow - fromRow) * 60;
        const startTime = Utils.now();

        this.gemAnimations.push({
            gem,
            fromY,
            toY,
            x: col * (gemSize + 4),
            startTime,
            duration,
            callback: () => {
                this.animatingGems.delete(key);
                if (callback) callback();
            }
        });
    }

    animateGemSpawn(gem, row, col, gemSize, callback) {
        const key = `${row},${col}`;
        this.animatingGems.add(key);

        const fromY = -(row + 1) * (gemSize + 4);
        const toY = row * (gemSize + 4);
        const duration = (row + 1) * 60;
        const startTime = Utils.now();

        this.gemAnimations.push({
            gem,
            fromY,
            toY,
            x: col * (gemSize + 4),
            startTime,
            duration,
            callback: () => {
                this.animatingGems.delete(key);
                if (callback) callback();
            }
        });
    }

    animateSwap(gem1, r1, c1, gem2, r2, c2, gemSize, onComplete) {
        const key1 = `${r1},${c1}`;
        const key2 = `${r2},${c2}`;
        this.animatingGems.add(key1);
        this.animatingGems.add(key2);

        const gemSize4 = gemSize + 4;
        const startTime = Utils.now();
        const duration = 200;

        this.gemAnimations.push({
            type: 'swap',
            gem1, gem2,
            from1: { x: c1 * gemSize4, y: r1 * gemSize4 },
            to1: { x: c2 * gemSize4, y: r2 * gemSize4 },
            from2: { x: c2 * gemSize4, y: r2 * gemSize4 },
            to2: { x: c1 * gemSize4, y: r1 * gemSize4 },
            startTime,
            duration,
            callback: () => {
                this.animatingGems.delete(key1);
                this.animatingGems.delete(key2);
                if (onComplete) onComplete();
            }
        });
    }

    render(ctx, timestamp) {
        this.gemAnimations = this.gemAnimations.filter(anim => {
            const elapsed = timestamp - anim.startTime;
            const t = Math.min(elapsed / anim.duration, 1);
            const eased = Utils.ease.easeOutBounce(t);

            if (anim.type === 'swap') {
                this._renderMovingGem(ctx, anim.gem1,
                    Utils.lerp(anim.from1.x, anim.to1.x, eased),
                    Utils.lerp(anim.from1.y, anim.to1.y, eased)
                );
                this._renderMovingGem(ctx, anim.gem2,
                    Utils.lerp(anim.from2.x, anim.to2.x, eased),
                    Utils.lerp(anim.from2.y, anim.to2.y, eased)
                );
            } else {
                const y = Utils.lerp(anim.fromY, anim.toY, eased);
                this._renderMovingGem(ctx, anim.gem, anim.x, y);
            }

            if (t >= 1) {
                anim.callback();
                return false;
            }
            return true;
        });

        this.particles = this.particles.filter(p => {
            p.life -= p.decay;

            if (p.life <= 0) return false;

            ctx.save();

            switch (p.type) {
                case 'circle':
                    p.x += p.vx;
                    p.y += p.vy;
                    p.vy += p.gravity;
                    p.alpha = p.life;

                    ctx.globalAlpha = p.alpha;
                    ctx.fillStyle = p.color;
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.size * p.life, 0, Math.PI * 2);
                    ctx.fill();

                    if (p.trail) {
                        ctx.globalAlpha = p.alpha * 0.3;
                        ctx.beginPath();
                        ctx.arc(p.x - p.vx, p.y - p.vy, p.size * p.life * 0.7, 0, Math.PI * 2);
                        ctx.fill();
                    }
                    break;

                case 'ring':
                    p.radius = Utils.lerp(p.radius, p.maxRadius, 0.1);
                    p.alpha = p.life;

                    ctx.globalAlpha = p.alpha;
                    ctx.strokeStyle = p.color;
                    ctx.lineWidth = 2;
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
                    ctx.stroke();
                    break;

                case 'flash':
                    p.alpha = p.life * 0.5;

                    ctx.globalAlpha = p.alpha;
                    const gradient = ctx.createRadialGradient(p.x, p.y, 0, p.x, p.y, p.radius * (1 - p.life));
                    gradient.addColorStop(0, p.color);
                    gradient.addColorStop(1, 'transparent');
                    ctx.fillStyle = gradient;
                    ctx.fillRect(p.x - p.radius, p.y - p.radius, p.radius * 2, p.radius * 2);
                    break;
            }

            ctx.restore();
            return true;
        });
    }

    _renderMovingGem(ctx, gem, x, y) {
        if (!gem) return;

        const colors = GEM_COLORS[gem.type];
        if (!colors) return;

        const gemSize = this.renderer.gemSize;
        const centerX = x + gemSize / 2;
        const centerY = y + gemSize / 2;
        const radius = gemSize / 2 - 2;

        ctx.save();
        ctx.translate(centerX, centerY);

        const gradient = ctx.createLinearGradient(-radius, -radius, radius, radius);
        gradient.addColorStop(0, colors.light);
        gradient.addColorStop(0.5, colors.base);
        gradient.addColorStop(1, colors.dark);

        ctx.fillStyle = gradient;
        ctx.beginPath();
        ctx.roundRect(-radius, -radius, gemSize - 4, gemSize - 4, 10);
        ctx.fill();

        const shineGradient = ctx.createRadialGradient(-radius * 0.3, -radius * 0.3, 0, 0, 0, radius);
        shineGradient.addColorStop(0, 'rgba(255,255,255,0.4)');
        shineGradient.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = shineGradient;
        ctx.beginPath();
        ctx.roundRect(-radius, -radius, gemSize - 4, gemSize - 4, 10);
        ctx.fill();

        ctx.restore();
    }

    clear() {
        this.particles = [];
        this.gemAnimations = [];
        this.animatingGems.clear();
    }
}
