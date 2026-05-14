class AudioManager {
  constructor() {
    this.ctx = null;
    this.muted = false;
    this.sounds = {};
    this.bgm = null;
  }

  init() {
    try {
      this.ctx = new (window.AudioContext || window.webkitAudioContext)();
    } catch (e) {
      console.warn('Web Audio API not available');
    }
  }

  async loadSound(name, url) {
    if (!this.ctx) return;
    try {
      const res = await fetch(url);
      const buf = await res.arrayBuffer();
      this.sounds[name] = await this.ctx.decodeAudioData(buf);
    } catch (e) { /* ignore */ }
  }

  play(name, loop = false) {
    if (!this.ctx || this.muted) return;
    const src = this.ctx.createBufferSource();
    src.buffer = this.sounds[name];
    src.loop = loop;
    src.connect(this.ctx.destination);
    src.start();
    if (loop) this.bgm = src;
  }

  playTone(freq, duration, type = 'square') {
    if (!this.ctx || this.muted) return;
    const osc = this.ctx.createOscillator();
    const gain = this.ctx.createGain();
    osc.type = type;
    osc.frequency.value = freq;
    gain.gain.setValueAtTime(0.1, this.ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, this.ctx.currentTime + duration);
    osc.connect(gain);
    gain.connect(this.ctx.destination);
    osc.start();
    osc.stop(this.ctx.currentTime + duration);
  }

  jump() { this.playTone(440, 0.1); }
  collect() { this.playTone(880, 0.08); this.playTone(1100, 0.08); }
  hit() { this.playTone(150, 0.3, 'sawtooth'); }
  powerup() { [523, 659, 784, 1047].forEach((f, i) => setTimeout(() => this.playTone(f, 0.15), i * 80)); }
  death() { [400, 300, 200].forEach((f, i) => setTimeout(() => this.playTone(f, 0.2, 'sawtooth'), i * 100)); }
  levelup() { [262, 330, 392, 523].forEach((f, i) => setTimeout(() => this.playTone(f, 0.15), i * 60)); }

  setMuted(muted) {
    this.muted = muted;
    if (muted && this.bgm) {
      try { this.bgm.stop(); } catch (e) { /* ignore */ }
      this.bgm = null;
    }
  }

  isMuted() { return this.muted; }
}

export { AudioManager };