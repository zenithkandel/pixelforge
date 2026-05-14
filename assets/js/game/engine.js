import { PRNG } from './prng.js';

// Game Engine
export class GameEngine {
    constructor(seed) {
        this.prng = new PRNG(seed);
        this.score = 0;
        this.lives = 3;
        this.speedTier = 1;
        this.combo = 0;
        this.gameTime = 0;
        this.isRunning = false;
        this.isPaused = false;
    }

    start() {
        this.isRunning = true;
        this.gameTime = 0;
    }

    update(deltaTime) {
        if (!this.isRunning || this.isPaused) return;

        this.gameTime += deltaTime;

        // Update speed tier based on score
        if (this.score >= 6000) this.speedTier = 7;
        else if (this.score >= 3500) this.speedTier = 6;
        else if (this.score >= 1800) this.speedTier = 5;
        else if (this.score >= 800) this.speedTier = 4;
        else if (this.score >= 300) this.speedTier = 3;
        else if (this.score >= 0) this.speedTier = 2;
    }

    addScore(points) {
        this.score += points;
        this.combo++;
    }

    resetCombo() {
        this.combo = 0;
    }

    loseLife() {
        this.lives--;
        if (this.lives <= 0) {
            this.isRunning = false;
        }
    }

    pause() {
        this.isPaused = true;
    }

    resume() {
        this.isPaused = false;
    }

    isGameOver() {
        return !this.isRunning && this.lives <= 0;
    }
}
