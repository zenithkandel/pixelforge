/**
 * PixelForge - Power-Up / Booster System
 * Manages consumable boosters that players can buy with earned currency.
 * Depends on: Utils (utils.js), GemForge (game.js), GameRenderer (game-renderer.js)
 */

class PowerUpManager {
    constructor(game, renderer) {
        this.game = game;
        this.renderer = renderer;

        this.boosters = {
            hammer: { count: 0, cost: 50, name: 'Hammer', icon: '\u{1F528}', description: 'Destroy one gem' },
            shuffle: { count: 0, cost: 30, name: 'Shuffle', icon: '\u{1F500}', description: 'Rearrange all gems' },
            extraMoves: { count: 0, cost: 40, name: '+5 Moves', icon: '\u26A1', description: 'Add 5 extra moves' },
            colorBurst: { count: 0, cost: 60, name: 'Color Burst', icon: '\u{1F308}', description: 'Clear all of one color' },
            lightning: { count: 0, cost: 80, name: 'Lightning', icon: '\u26A1', description: 'Clear a row and column' }
        };

        this.activeBooster = null;
        this.colorPickerOpen = false;
        this.colorPickerCallback = null;

        this.events = Utils.createEmitter();
    }

    async loadBoosters() {
        try {
            const response = await fetch('/codes/pixelforge/api/game.php?action=get_boosters');
            const data = await response.json();
            if (data.success) {
                Object.keys(this.boosters).forEach(key => {
                    if (data.boosters[key] !== undefined) {
                        this.boosters[key].count = data.boosters[key];
                    }
                });
                this.events.emit('boostersUpdated', this.boosters);
            }
        } catch (err) {
            console.error('Failed to load boosters:', err);
        }
    }

    async buyBooster(type) {
        const booster = this.boosters[type];
        if (!booster) return false;

        try {
            const response = await fetch('/codes/pixelforge/api/game.php?action=buy_booster', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booster_type: type })
            });
            const data = await response.json();

            if (data.success) {
                booster.count++;
                this.events.emit('boostersUpdated', this.boosters);
                this.events.emit('boosterPurchased', { type, count: booster.count });
                return true;
            } else {
                this.events.emit('error', data.message || 'Not enough coins');
                return false;
            }
        } catch (err) {
            this.events.emit('error', 'Network error');
            return false;
        }
    }

    activateBooster(type) {
        const booster = this.boosters[type];
        if (!booster || booster.count <= 0) return;
        if (this.game.isProcessing) return;
        if (this.activeBooster) return;

        switch (type) {
            case 'hammer':
                this._activateHammer();
                break;
            case 'shuffle':
                this._activateShuffle();
                break;
            case 'extraMoves':
                this._activateExtraMoves();
                break;
            case 'colorBurst':
                this._activateColorBurst();
                break;
            case 'lightning':
                this._activateLightning();
                break;
        }
    }

    cancelBooster() {
        if (this.activeBooster) {
            this.activeBooster = null;
            this.renderer.canvas.style.cursor = 'default';
            this.events.emit('boosterCancelled');
        }
    }

    handleBoosterClick(row, col) {
        if (!this.activeBooster) return false;

        switch (this.activeBooster) {
            case 'hammer':
                this._useHammer(row, col);
                return true;
            case 'colorBurst':
                this._useColorBurst(row, col);
                return true;
        }

        return false;
    }

    _activateHammer() {
        this.activeBooster = 'hammer';
        this.renderer.canvas.style.cursor = 'crosshair';
        this.events.emit('boosterActivated', { type: 'hammer', message: 'Click a gem to destroy it' });
    }

    async _useHammer(row, col) {
        const gem = this.game.board[row][col];
        if (!gem) {
            this.cancelBooster();
            return;
        }

        const booster = this.boosters.hammer;

        try {
            const response = await fetch('/codes/pixelforge/api/game.php?action=use_booster', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booster_type: 'hammer', target: { row, col } })
            });
            const data = await response.json();

            if (data.success) {
                booster.count--;

                const gemSize = this.renderer.gemSize;
                const padding = this.renderer.padding;
                const x = col * (gemSize + padding) + gemSize / 2;
                const y = row * (gemSize + padding) + gemSize / 2;
                const color = GEM_COLORS[gem.type]?.base || '#ffffff';

                for (let i = 0; i < 12; i++) {
                    const angle = (i / 12) * Math.PI * 2;
                    this.renderer.particles.particles.push({
                        type: 'circle',
                        x, y,
                        vx: Math.cos(angle) * Utils.randInt(2, 5),
                        vy: Math.sin(angle) * Utils.randInt(2, 5),
                        size: Utils.randInt(2, 4),
                        color,
                        alpha: 1,
                        decay: 0.03,
                        gravity: 0.1,
                        life: 1
                    });
                }

                this.game.board[row][col] = null;
                this.game.events.emit('hammerDestroy', { row, col });
                this.game.isProcessing = true;
                await this.game._processCascade();

                this.events.emit('boostersUpdated', this.boosters);
                this.events.emit('boosterUsed', { type: 'hammer' });
            }
        } catch (err) {
            this.events.emit('error', 'Failed to use hammer');
        }

        this.activeBooster = null;
        this.renderer.canvas.style.cursor = 'default';
    }

    async _activateShuffle() {
        const booster = this.boosters.shuffle;

        try {
            const response = await fetch('/codes/pixelforge/api/game.php?action=use_booster', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booster_type: 'shuffle' })
            });
            const data = await response.json();

            if (data.success) {
                booster.count--;

                const gems = [];
                const specials = [];

                for (let r = 0; r < this.game.rows; r++) {
                    for (let c = 0; c < this.game.cols; c++) {
                        const gem = this.game.board[r][c];
                        if (gem) {
                            gems.push(gem.type);
                            if (gem.special) {
                                specials.push({ row: r, col: c, special: gem.special });
                            }
                        }
                    }
                }

                const shuffled = Utils.shuffle(gems);

                let idx = 0;
                for (let r = 0; r < this.game.rows; r++) {
                    for (let c = 0; c < this.game.cols; c++) {
                        if (this.game.board[r][c]) {
                            this.game.board[r][c].type = shuffled[idx++];
                        }
                    }
                }

                specials.forEach(s => {
                    if (this.game.board[s.row][s.col]) {
                        this.game.board[s.row][s.col].special = s.special;
                    }
                });

                this.game.events.emit('shuffle');
                this.game.events.emit('init', this.game.board);
                this.events.emit('boostersUpdated', this.boosters);
                this.events.emit('boosterUsed', { type: 'shuffle' });
            }
        } catch (err) {
            this.events.emit('error', 'Failed to shuffle');
        }
    }

    async _activateExtraMoves() {
        const booster = this.boosters.extraMoves;

        try {
            const response = await fetch('/codes/pixelforge/api/game.php?action=use_booster', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booster_type: 'extraMoves' })
            });
            const data = await response.json();

            if (data.success) {
                booster.count--;
                this.game.movesLeft += 5;
                this.game.events.emit('movesChanged', this.game.movesLeft);
                this.events.emit('boostersUpdated', this.boosters);
                this.events.emit('boosterUsed', { type: 'extraMoves', movesAdded: 5 });
            }
        } catch (err) {
            this.events.emit('error', 'Failed to add moves');
        }
    }

    _activateColorBurst() {
        this.activeBooster = 'colorBurst';
        this.events.emit('boosterActivated', { type: 'colorBurst', message: 'Click a gem to destroy all of its color' });
    }

    async _useColorBurst(row, col) {
        const gem = this.game.board[row][col];
        if (!gem) {
            this.cancelBooster();
            return;
        }

        const targetType = gem.type;
        const booster = this.boosters.colorBurst;

        try {
            const response = await fetch('/codes/pixelforge/api/game.php?action=use_booster', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ booster_type: 'colorBurst', target: { type: targetType } })
            });
            const data = await response.json();

            if (data.success) {
                booster.count--;

                const matches = [];
                for (let r = 0; r < this.game.rows; r++) {
                    for (let c = 0; c < this.game.cols; c++) {
                        if (this.game.board[r][c]?.type === targetType) {
                            matches.push({ row: r, col: c, gem: this.game.board[r][c] });
                        }
                    }
                }

                const gemSize = this.renderer.gemSize;
                const padding = this.renderer.padding;
                matches.forEach(m => {
                    const x = m.col * (gemSize + padding) + gemSize / 2;
                    const y = m.row * (gemSize + padding) + gemSize / 2;
                    const color = GEM_COLORS[m.gem.type]?.base || '#ffffff';

                    for (let i = 0; i < 8; i++) {
                        const angle = (i / 8) * Math.PI * 2;
                        this.renderer.particles.particles.push({
                            type: 'circle',
                            x, y,
                            vx: Math.cos(angle) * Utils.randInt(2, 4),
                            vy: Math.sin(angle) * Utils.randInt(2, 4),
                            size: Utils.randInt(2, 4),
                            color,
                            alpha: 1,
                            decay: 0.03,
                            gravity: 0.05,
                            life: 1
                        });
                    }
                });

                matches.forEach(m => {
                    this.game.board[m.row][m.col] = null;
                });

                this.game.score += matches.length * 15;
                this.game.isProcessing = true;
                await this.game._processCascade();

                this.events.emit('boostersUpdated', this.boosters);
                this.events.emit('boosterUsed', { type: 'colorBurst', gemsDestroyed: matches.length });
            }
        } catch (err) {
            this.events.emit('error', 'Failed to use color burst');
        }

        this.activeBooster = null;
    }

    async _activateLightning() {
        const booster = this.boosters.lightning;

        try {
            const response = await fetch('/codes/pixelforge/api/game.php?action=use_booster', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: 'lightning' })
            });
            const data = await response.json();

            if (data.success) {
                booster.count--;

                const targetRow = Utils.randInt(0, this.game.rows - 1);
                const targetCol = Utils.randInt(0, this.game.cols - 1);

                const matches = [];
                const gemSize = this.renderer.gemSize;
                const padding = this.renderer.padding;

                for (let c = 0; c < this.game.cols; c++) {
                    if (this.game.board[targetRow][c]) {
                        matches.push({ row: targetRow, col: c, gem: this.game.board[targetRow][c] });

                        const x = c * (gemSize + padding) + gemSize / 2;
                        const y = targetRow * (gemSize + padding) + gemSize / 2;
                        this.renderer.particles.particles.push({
                            type: 'flash',
                            x, y,
                            radius: gemSize,
                            color: '#3b82f6',
                            alpha: 0.6,
                            decay: 0.04,
                            life: 1
                        });
                    }
                }

                for (let r = 0; r < this.game.rows; r++) {
                    if (this.game.board[r][targetCol]) {
                        matches.push({ row: r, col: targetCol, gem: this.game.board[r][targetCol] });

                        const x = targetCol * (gemSize + padding) + gemSize / 2;
                        const y = r * (gemSize + padding) + gemSize / 2;
                        this.renderer.particles.particles.push({
                            type: 'flash',
                            x, y,
                            radius: gemSize,
                            color: '#fbbf24',
                            alpha: 0.6,
                            decay: 0.04,
                            life: 1
                        });
                    }
                }

                const seen = new Set();
                const unique = matches.filter(m => {
                    const key = `${m.row},${m.col}`;
                    if (seen.has(key)) return false;
                    seen.add(key);
                    return true;
                });

                unique.forEach(m => {
                    this.game.board[m.row][m.col] = null;
                });

                this.game.score += unique.length * 12;
                this.game.isProcessing = true;
                await this.game._processCascade();

                this.events.emit('boostersUpdated', this.boosters);
                this.events.emit('boosterUsed', { type: 'lightning', gemsDestroyed: unique.length });
            }
        } catch (err) {
            this.events.emit('error', 'Failed to use lightning');
        }
    }
}