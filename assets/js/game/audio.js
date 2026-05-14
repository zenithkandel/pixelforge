// Audio management using Web Audio API
export class AudioManager {
    constructor() {
        this.audioContext = null;
        this.isMuted = false;
        this.initAudio();
    }

    initAudio() {
        if (!window.AudioContext && !window.webkitAudioContext) return;
        const AudioContext = window.AudioContext || window.webkitAudioContext;
        this.audioContext = new AudioContext();
    }

    playSound(type = 'jump') {
        if (!this.audioContext || this.isMuted) return;

        const ctx = this.audioContext;
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();

        osc.connect(gain);
        gain.connect(ctx.destination);

        switch (type) {
            case 'jump':
                osc.frequency.value = 400;
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.1);
                break;
            case 'collect':
                osc.frequency.value = 800;
                gain.gain.setValueAtTime(0.2, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.15);
                break;
            case 'hit':
                osc.frequency.value = 100;
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.2);
                osc.start(ctx.currentTime);
                osc.stop(ctx.currentTime + 0.2);
                break;
        }
    }

    toggle() {
        this.isMuted = !this.isMuted;
    }
}
