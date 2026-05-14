export class AudioManager {
    constructor() {
        this.ctx = null;
        this.enabled = true;
        this.initialized = false;
    }

    init() {
        if (this.initialized) return;

        try {
            this.ctx = new (window.AudioContext || window.webkitAudioContext)();
            this.initialized = true;
        } catch (e) {
            console.warn('Web Audio not available');
            this.enabled = false;
        }
    }

    playTone(frequency, duration, type = 'square', volume = 0.1) {
        if (!this.enabled || !this.ctx) return;

        try {
            const osc = this.ctx.createOscillator();
            const gain = this.ctx.createGain();

            osc.type = type;
            osc.frequency.setValueAtTime(frequency, this.ctx.currentTime);

            gain.gain.setValueAtTime(volume, this.ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, this.ctx.currentTime + duration);

            osc.connect(gain);
            gain.connect(this.ctx.destination);

            osc.start();
            osc.stop(this.ctx.currentTime + duration);
        } catch (e) {
        }
    }

    playJump() {
        this.init();
        this.playTone(400, 0.1, 'square', 0.08);
        setTimeout(() => this.playTone(600, 0.08, 'square', 0.06), 50);
    }

    playCollect() {
        this.init();
        this.playTone(800, 0.05, 'sine', 0.1);
        setTimeout(() => this.playTone(1000, 0.05, 'sine', 0.08), 30);
    }

    playHit() {
        this.init();
        this.playTone(150, 0.2, 'sawtooth', 0.15);
    }

    playPowerup() {
        this.init();
        const notes = [400, 500, 600, 800, 1000];
        notes.forEach((freq, i) => {
            setTimeout(() => this.playTone(freq, 0.1, 'square', 0.08), i * 60);
        });
    }

    playDeath() {
        this.init();
        const notes = [600, 500, 400, 300, 200];
        notes.forEach((freq, i) => {
            setTimeout(() => this.playTone(freq, 0.15, 'sawtooth', 0.1), i * 100);
        });
    }

    playLevelUp() {
        this.init();
        const notes = [400, 500, 600, 800];
        notes.forEach((freq, i) => {
            setTimeout(() => this.playTone(freq, 0.15, 'square', 0.1), i * 80);
        });
    }

    toggle() {
        this.enabled = !this.enabled;
        return this.enabled;
    }

    isEnabled() {
        return this.enabled;
    }
}