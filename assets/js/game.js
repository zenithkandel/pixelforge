/**
 * PixelForge - Match-3 Game Engine
 * Core game logic: board state, matching, cascading, scoring.
 * Depends on utils.js (Utils) loaded before this file.
 */

class GemForge {
    constructor(rows = 8, cols = 8, numTypes = 6) {
        this.rows = rows;
        this.cols = cols;
        this.numTypes = numTypes;
        this.board = [];
        this.selected = null;
        this.score = 0;
        this.movesLeft = 30;
        this.combo = 0;
        this.comboMultiplier = 1.0;
        this.maxCombo = 0;
        this.isProcessing = false;
        this.events = Utils.createEmitter();

        this.stats = {
            gemsMatched: 0,
            specialsCreated: 0,
            boostsUsed: 0,
            highestCombo: 0
        };
    }

    /** Initialize board with no initial matches. */
    init() {
        this.board = [];
        for (let r = 0; r < this.rows; r++) {
            this.board[r] = [];
            for (let c = 0; c < this.cols; c++) {
                let type;
                do {
                    type = Utils.randInt(0, this.numTypes - 1);
                } while (this._wouldMatch(r, c, type));

                this.board[r][c] = { type, special: null, row: r, col: c };
            }
        }
        this.events.emit('init', this.board);
    }

    /**
     * Check if placing `type` at (r,c) would create a 3-in-a-row.
     * Used during board generation to prevent initial matches.
     */
    _wouldMatch(r, c, type) {
        // Horizontal: check two to the left
        if (c >= 2) {
            const left1 = this.board[r][c - 1];
            const left2 = this.board[r][c - 2];
            if (left1 && left2 && left1.type === type && left2.type === type) {
                return true;
            }
        }
        // Vertical: check two above
        if (r >= 2) {
            const up1 = this.board[r - 1]?.[c];
            const up2 = this.board[r - 2]?.[c];
            if (up1 && up2 && up1.type === type && up2.type === type) {
                return true;
            }
        }
        return false;
    }

    /**
     * Attempt to swap two adjacent gems.
     * Returns true if swap was valid and created matches, false otherwise.
     */
    trySwap(r1, c1, r2, c2) {
        if (this.isProcessing) return false;
        if (this.movesLeft <= 0) return false;

        const dr = Math.abs(r1 - r2);
        const dc = Math.abs(c1 - c2);
        const adjacent = (dr === 1 && dc === 0) || (dr === 0 && dc === 1);
        if (!adjacent) return false;

        this.isProcessing = true;
        this._swapGems(r1, c1, r2, c2);

        const matches = this.findAllMatches();
        if (matches.length > 0) {
            this.movesLeft--;
            this.combo = 0;
            this.events.emit('swap', { r1, c1, r2, c2, valid: true });
            this._processCascade();
            return true;
        }

        // No matches – revert the swap
        this._swapGems(r1, c1, r2, c2);
        this.events.emit('swap', { r1, c1, r2, c2, valid: false });
        this.isProcessing = false;
        return false;
    }

    /** Swap two gems in the board array and update their positions. */
    _swapGems(r1, c1, r2, c2) {
        const temp = this.board[r1][c1];
        this.board[r1][c1] = this.board[r2][c2];
        this.board[r2][c2] = temp;

        if (this.board[r1][c1]) {
            this.board[r1][c1].row = r1;
            this.board[r1][c1].col = c1;
        }
        if (this.board[r2][c2]) {
            this.board[r2][c2].row = r2;
            this.board[r2][c2].col = c2;
        }
    }

    /**
     * Find all current matches on the board.
     * Returns an array of { row, col, gem } objects for every matched cell.
     * A match is 3+ consecutive same-type gems horizontally or vertically.
     */
    findAllMatches() {
        const matched = new Set();

        // --- Horizontal runs ---
        for (let r = 0; r < this.rows; r++) {
            let run = 1;
            for (let c = 1; c < this.cols; c++) {
                const prev = this.board[r][c - 1];
                const curr = this.board[r][c];
                if (curr && prev && curr.type === prev.type && curr.type !== -1) {
                    run++;
                } else {
                    if (run >= 3) {
                        for (let i = 0; i < run; i++) {
                            matched.add(`${r},${c - 1 - i}`);
                        }
                    }
                    run = 1;
                }
            }
            // End-of-row run
            if (run >= 3) {
                for (let i = 0; i < run; i++) {
                    matched.add(`${r},${this.cols - 1 - i}`);
                }
            }
        }

        // --- Vertical runs ---
        for (let c = 0; c < this.cols; c++) {
            let run = 1;
            for (let r = 1; r < this.rows; r++) {
                const prev = this.board[r - 1][c];
                const curr = this.board[r][c];
                if (curr && prev && curr.type === prev.type && curr.type !== -1) {
                    run++;
                } else {
                    if (run >= 3) {
                        for (let i = 0; i < run; i++) {
                            matched.add(`${r - 1 - i},${c}`);
                        }
                    }
                    run = 1;
                }
            }
            // End-of-column run
            if (run >= 3) {
                for (let i = 0; i < run; i++) {
                    matched.add(`${this.rows - 1 - i},${c}`);
                }
            }
        }

        return Array.from(matched).map(key => {
            const [r, c] = key.split(',').map(Number);
            return { row: r, col: c, gem: this.board[r][c] };
        });
    }

    /**
     * Detect L/T shape patterns among matched gems.
     * An L/T shape exists when a match intersects both horizontally and vertically
     * on the same gem with at least 3 in each direction (5+ total).
     * Returns specials to create: [{ row, col, type, pattern }]
     */
    findSpecialPatterns(matches) {
        const specials = [];
        const byType = {};

        matches.forEach(m => {
            const t = m.gem.type;
            if (!byType[t]) byType[t] = [];
            byType[t].push(m);
        });

        Object.values(byType).forEach(gems => {
            if (gems.length < 5) return;

            for (const gem of gems) {
                const hCount = gems.filter(g => g.row === gem.row).length;
                const vCount = gems.filter(g => g.col === gem.col).length;

                // Intersection point: has at least 3 horizontal AND 3 vertical neighbors
                if (hCount >= 3 && vCount >= 3) {
                    specials.push({
                        row: gem.row,
                        col: gem.col,
                        type: gem.gem.type,
                        pattern: 'colorblast'
                    });
                    break; // One L/T per type group
                }
            }
        });

        return specials;
    }

    /**
     * Main cascade loop: match → remove → gravity → fill → repeat.
     * Processes special gem activations, scoring, and emits events for the renderer.
     */
    async _processCascade() {
        let matches = this.findAllMatches();

        while (matches.length > 0) {
            this.combo++;
            if (this.combo > this.maxCombo) this.maxCombo = this.combo;
            if (this.combo > this.stats.highestCombo) this.stats.highestCombo = this.combo;

            // Combo multiplier: 1.0, 1.5, 2.0, 2.5 … capped at 5.0
            this.comboMultiplier = 1.0 + (this.combo - 1) * 0.5;
            if (this.comboMultiplier > 5.0) this.comboMultiplier = 5.0;

            const specials = this.findSpecialPatterns(matches);
            const matchScore = this._calculateMatchScore(matches, specials);
            this.score += matchScore;
            this.stats.gemsMatched += matches.length;

            this.events.emit('match', {
                matches,
                specials,
                score: matchScore,
                combo: this.combo,
                comboMultiplier: this.comboMultiplier
            });

            // Activate any special gems caught in the match
            const chainReactions = this._activateSpecials(matches);
            if (chainReactions.length > 0) {
                this.events.emit('chainReaction', chainReactions);
                chainReactions.forEach(cr => {
                    cr.matches.forEach(m => matches.push(m));
                });
            }

            // Create special gems from L/T / long-run patterns
            specials.forEach(s => {
                if (!this.board[s.row][s.col]) return;
                this.board[s.row][s.col].special = s.pattern;
                this.stats.specialsCreated++;
            });

            this._removeMatches(matches);
            await this._wait(300);

            const falls = this._applyGravity();
            this.events.emit('gravity', falls);
            await this._wait(300);

            const newGems = this._fillEmpty();
            this.events.emit('fill', newGems);
            await this._wait(200);

            matches = this.findAllMatches();
        }

        // End-of-turn checks
        if (this.movesLeft <= 0) {
            this.events.emit('gameOver', {
                score: this.score,
                maxCombo: this.maxCombo,
                stats: this.stats
            });
        } else if (!this._hasPossibleMoves()) {
            this.events.emit('noMoves');
            await this._shuffleBoard();
        }

        this.combo = 0;
        this.isProcessing = false;
        this.events.emit('stateChange', this.getState());
    }

    /**
     * Calculate score for a set of matches.
     * 10 pts per gem + size bonus (4-match +30, 5-match +70, 6+ +120)
     * + 50 pts per special created, all multiplied by combo multiplier.
     */
    _calculateMatchScore(matches, specials) {
        let base = 0;
        const counted = new Set();

        matches.forEach(m => {
            const key = `${m.row},${m.col}`;
            if (!counted.has(key)) {
                counted.add(key);
                base += 10;
            }
        });

        // Size bonus based on total unique gems in the match
        const uniqueCount = counted.size;
        if (uniqueCount === 4) base += 30;
        else if (uniqueCount === 5) base += 70;
        else if (uniqueCount >= 6) base += 120;

        // Special creation bonus
        specials.forEach(() => {
            base += 50;
        });

        return Math.floor(base * this.comboMultiplier);
    }

    /**
     * Activate special gems that were part of matched cells.
     * Each special type clears a different area, producing chain-reaction matches.
     */
    _activateSpecials(matches) {
        const reactions = [];

        matches.forEach(m => {
            if (!m.gem || !m.gem.special) return;

            let affected = [];
            switch (m.gem.special) {
                case 'rocket':
                    affected = this._getLineTargets(m.row, m.col);
                    break;
                case 'bomb':
                    affected = this._getBombTargets(m.row, m.col);
                    break;
                case 'colorblast':
                    affected = this._getColorTargets(m.gem.type);
                    break;
                case 'nova':
                    affected = this._getNovaTargets(m.row, m.col);
                    break;
            }

            if (affected.length > 0) {
                reactions.push({
                    type: m.gem.special,
                    source: { row: m.row, col: m.col },
                    matches: affected.map(g => ({ row: g.row, col: g.col, gem: g }))
                });
            }
        });

        return reactions;
    }

    /** Rocket: clear an entire row or column. Direction chosen by neighboring count heuristic. */
    _getLineTargets(row, col) {
        const targets = [];
        const gemType = this.board[row][col]?.type;
        if (gemType === undefined) return targets;

        let hCount = 0;
        let vCount = 0;
        for (let c = 0; c < this.cols; c++) {
            if (this.board[row][c] && this.board[row][c].type === gemType) hCount++;
        }
        for (let r = 0; r < this.rows; r++) {
            if (this.board[r][col] && this.board[r][col].type === gemType) vCount++;
        }

        if (hCount >= vCount) {
            // Clear the column
            for (let r = 0; r < this.rows; r++) {
                if (this.board[r][col]) targets.push(this.board[r][col]);
            }
        } else {
            // Clear the row
            for (let c = 0; c < this.cols; c++) {
                if (this.board[row][c]) targets.push(this.board[row][c]);
            }
        }
        return targets;
    }

    /** Bomb: clear a 3×3 area around the source. */
    _getBombTargets(row, col) {
        const targets = [];
        for (let r = row - 1; r <= row + 1; r++) {
            for (let c = col - 1; c <= col + 1; c++) {
                if (r >= 0 && r < this.rows && c >= 0 && c < this.cols && this.board[r][c]) {
                    targets.push(this.board[r][c]);
                }
            }
        }
        return targets;
    }

    /** Nova: clear a 5×5 area around the source. */
    _getNovaTargets(row, col) {
        const targets = [];
        for (let r = row - 2; r <= row + 2; r++) {
            for (let c = col - 2; c <= col + 2; c++) {
                if (r >= 0 && r < this.rows && c >= 0 && c < this.cols && this.board[r][c]) {
                    targets.push(this.board[r][c]);
                }
            }
        }
        return targets;
    }

    /** Color Blast: clear every gem of the same type on the board. */
    _getColorTargets(type) {
        const targets = [];
        for (let r = 0; r < this.rows; r++) {
            for (let c = 0; c < this.cols; c++) {
                if (this.board[r][c] && this.board[r][c].type === type) {
                    targets.push(this.board[r][c]);
                }
            }
        }
        return targets;
    }

    /** Remove matched gems from the board (set cells to null). */
    _removeMatches(matches) {
        matches.forEach(m => {
            this.board[m.row][m.col] = null;
        });
    }

    /**
     * Apply gravity: non-null gems fall downward to fill gaps.
     * Returns an array describing each fall: { gem, fromRow, toRow, col }.
     */
    _applyGravity() {
        const falls = [];

        for (let c = 0; c < this.cols; c++) {
            let writeRow = this.rows - 1;

            for (let r = this.rows - 1; r >= 0; r--) {
                if (this.board[r][c]) {
                    if (r !== writeRow) {
                        const gem = this.board[r][c];
                        const fromRow = r;
                        this.board[writeRow][c] = gem;
                        this.board[r][c] = null;
                        gem.row = writeRow;
                        gem.col = c;
                        falls.push({ gem, fromRow, toRow: writeRow, col: c });
                    }
                    writeRow--;
                }
            }
        }

        return falls;
    }

    /**
     * Fill empty cells at the top of each column with new random gems.
     * Returns an array of new gems: { gem, row, col, fromRow } (fromRow is negative for drop animation).
     */
    _fillEmpty() {
        const newGems = [];

        for (let c = 0; c < this.cols; c++) {
            let emptyCount = 0;
            for (let r = 0; r < this.rows; r++) {
                if (!this.board[r][c]) emptyCount++;
                else break;
            }

            for (let i = 0; i < emptyCount; i++) {
                const type = Utils.randInt(0, this.numTypes - 1);
                const gem = { type, special: null, row: i, col: c };
                this.board[i][c] = gem;
                newGems.push({ gem, row: i, col: c, fromRow: -(emptyCount - i) });
            }
        }

        return newGems;
    }

    /**
     * Check if at least one valid move exists on the board.
     * Tries every possible swap and checks for matches.
     */
    _hasPossibleMoves() {
        for (let r = 0; r < this.rows; r++) {
            for (let c = 0; c < this.cols; c++) {
                // Try swap right
                if (c < this.cols - 1) {
                    this._swapGems(r, c, r, c + 1);
                    const hasMatch = this.findAllMatches().length > 0;
                    this._swapGems(r, c, r, c + 1);
                    if (hasMatch) return true;
                }
                // Try swap down
                if (r < this.rows - 1) {
                    this._swapGems(r, c, r + 1, c);
                    const hasMatch = this.findAllMatches().length > 0;
                    this._swapGems(r, c, r + 1, c);
                    if (hasMatch) return true;
                }
            }
        }
        return false;
    }

    /**
     * Shuffle the board when no moves are available.
     * Collects all gem types, shuffles them, and reassigns.
     * Repeats until the board is both match-free and has at least one valid move.
     */
    async _shuffleBoard() {
        this.events.emit('shuffle');

        const gems = [];
        for (let r = 0; r < this.rows; r++) {
            for (let c = 0; c < this.cols; c++) {
                if (this.board[r][c]) gems.push(this.board[r][c].type);
            }
        }

        // Keep shuffling until we get a playable, match-free board
        let attempts = 0;
        do {
            const shuffled = Utils.shuffle([...gems]);
            let idx = 0;

            for (let r = 0; r < this.rows; r++) {
                for (let c = 0; c < this.cols; c++) {
                    this.board[r][c] = {
                        type: shuffled[idx++],
                        special: null,
                        row: r,
                        col: c
                    };
                }
            }
            attempts++;
        } while (
            this.findAllMatches().length > 0 ||
            !this._hasPossibleMoves()
        );

        await this._wait(500);
        this.events.emit('init', this.board);
    }

    /**
     * Find a hint: the first valid swap on the board.
     * Returns [{ row, col }, { row, col }] or null.
     */
    findHint() {
        for (let r = 0; r < this.rows; r++) {
            for (let c = 0; c < this.cols; c++) {
                if (c < this.cols - 1) {
                    this._swapGems(r, c, r, c + 1);
                    if (this.findAllMatches().length > 0) {
                        this._swapGems(r, c, r, c + 1);
                        return [{ row: r, col: c }, { row: r, col: c + 1 }];
                    }
                    this._swapGems(r, c, r, c + 1);
                }
                if (r < this.rows - 1) {
                    this._swapGems(r, c, r + 1, c);
                    if (this.findAllMatches().length > 0) {
                        this._swapGems(r, c, r + 1, c);
                        return [{ row: r, col: c }, { row: r + 1, col: c }];
                    }
                    this._swapGems(r, c, r + 1, c);
                }
            }
        }
        return null;
    }

    /** Serialize the current game state for server storage. */
    getState() {
        return {
            board: this.board.map(row =>
                row.map(gem => gem ? { type: gem.type, special: gem.special } : null)
            ),
            score: this.score,
            movesLeft: this.movesLeft,
            maxCombo: this.maxCombo,
            stats: { ...this.stats }
        };
    }

    /** Load a previously saved game state. */
    loadState(state) {
        this.score = state.score;
        this.movesLeft = state.movesLeft;
        this.maxCombo = state.maxCombo || 0;
        this.stats = state.stats || { ...this.stats };

        this.board = [];
        for (let r = 0; r < this.rows; r++) {
            this.board[r] = [];
            for (let c = 0; c < this.cols; c++) {
                const s = state.board[r]?.[c];
                if (s) {
                    this.board[r][c] = { type: s.type, special: s.special, row: r, col: c };
                } else {
                    this.board[r][c] = null;
                }
            }
        }

        this.events.emit('init', this.board);
    }

    /**
     * Handle gem selection via click/tap.
     * If a gem is already selected and the new gem is adjacent, attempt a swap.
     * Otherwise, select the new gem (or deselect if same gem).
     */
    select(row, col) {
        if (this.isProcessing) return;
        if (!this.board[row][col]) return;

        if (this.selected) {
            // Same gem – deselect
            if (this.selected.row === row && this.selected.col === col) {
                this.selected = null;
                this.events.emit('deselect');
                return;
            }

            // Adjacent – try swap
            const dr = Math.abs(this.selected.row - row);
            const dc = Math.abs(this.selected.col - col);
            if ((dr === 1 && dc === 0) || (dr === 0 && dc === 1)) {
                this.trySwap(this.selected.row, this.selected.col, row, col);
                this.selected = null;
                return;
            }

            // Not adjacent – select the new gem
            this.selected = { row, col };
            this.events.emit('select', { row, col });
        } else {
            this.selected = { row, col };
            this.events.emit('select', { row, col });
        }
    }

    /** Promise-based wait helper for animation timing. */
    _wait(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }
}
