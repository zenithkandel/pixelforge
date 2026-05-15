class GameAudio {
  constructor() {
    this.audioContext = null;
    this.sounds = {};
    this.musicPlaying = false;
    this.musicVolume = 0.3;
    this.sfxVolume = 0.5;
    this.initialized = false;
  }

  init() {
    if (this.initialized) return;
    
    try {
      this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
      this.initialized = true;
      this.createSounds();
    } catch (err) {
      console.warn('Web Audio API not supported');
    }
  }

  createSounds() {
    this.sounds = {
      jump: { freq: 400, duration: 0.1, type: 'square' },
      collect: { freq: 800, duration: 0.1, type: 'sine' },
      hit: { freq: 150, duration: 0.2, type: 'sawtooth' },
      start: { freq: 600, duration: 0.15, type: 'sine' },
      gameover: { freq: 200, duration: 0.5, type: 'sawtooth' },
      shield_break: { freq: 500, duration: 0.15, type: 'square' }
    };
  }

  play(soundName) {
    if (!this.initialized) this.init();
    if (!this.audioContext || !this.sounds[soundName]) return;

    try {
      const sound = this.sounds[soundName];
      const oscillator = this.audioContext.createOscillator();
      const gainNode = this.audioContext.createGain();

      oscillator.connect(gainNode);
      gainNode.connect(this.audioContext.destination);

      oscillator.type = sound.type;
      oscillator.frequency.setValueAtTime(sound.freq, this.audioContext.currentTime);
      oscillator.frequency.exponentialRampToValueAtTime(
        sound.freq * 0.5,
        this.audioContext.currentTime + sound.duration
      );

      gainNode.gain.setValueAtTime(this.sfxVolume * 0.3, this.audioContext.currentTime);
      gainNode.gain.exponentialRampToValueAtTime(
        0.01,
        this.audioContext.currentTime + sound.duration
      );

      oscillator.start(this.audioContext.currentTime);
      oscillator.stop(this.audioContext.currentTime + sound.duration);
    } catch (err) {
      // Silently fail
    }
  }

  setSFXVolume(volume) {
    this.sfxVolume = Math.max(0, Math.min(1, volume));
  }

  setMusicVolume(volume) {
    this.musicVolume = Math.max(0, Math.min(1, volume));
  }
}

window.GameAudio = GameAudio;