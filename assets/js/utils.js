const Utils = {
    // Random integer between min and max (inclusive)
    randInt(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    },

    // Random float between min and max
    randFloat(min, max) {
        return Math.random() * (max - min) + min;
    },

    // Random item from array
    randItem(arr) {
        return arr[Math.floor(Math.random() * arr.length)];
    },

    // Shuffle array (Fisher-Yates)
    shuffle(arr) {
        const a = [...arr];
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [a[i], a[j]] = [a[j], a[i]];
        }
        return a;
    },

    // Clamp value between min and max
    clamp(val, min, max) {
        return Math.max(min, Math.min(max, val));
    },

    // Linear interpolation
    lerp(a, b, t) {
        return a + (b - a) * t;
    },

    // Easing functions
    ease: {
        linear: t => t,
        easeInQuad: t => t * t,
        easeOutQuad: t => t * (2 - t),
        easeInOutQuad: t => t < 0.5 ? 2 * t * t : -1 + (4 - 2 * t) * t,
        easeOutBack: t => { const c = 1.70158; return 1 + (c + 1) * Math.pow(t - 1, 3) + c * Math.pow(t - 1, 2); },
        easeOutElastic: t => {
            if (t === 0 || t === 1) return t;
            return Math.pow(2, -10 * t) * Math.sin((t - 0.075) * (2 * Math.PI) / 0.3) + 1;
        },
        easeOutBounce: t => {
            if (t < 1 / 2.75) return 7.5625 * t * t;
            if (t < 2 / 2.75) return 7.5625 * (t -= 1.5 / 2.75) * t + 0.75;
            if (t < 2.5 / 2.75) return 7.5625 * (t -= 2.25 / 2.75) * t + 0.9375;
            return 7.5625 * (t -= 2.625 / 2.75) * t + 0.984375;
        }
    },

    // Parse hex color to rgb object
    hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : { r: 0, g: 0, b: 0 };
    },

    // RGB to hex string
    rgbToHex(r, g, b) {
        return '#' + [r, g, b].map(x => {
            const hex = Math.round(x).toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        }).join('');
    },

    // Darken a hex color by percentage
    darken(hex, percent) {
        const { r, g, b } = this.hexToRgb(hex);
        const factor = 1 - percent / 100;
        return this.rgbToHex(r * factor, g * factor, b * factor);
    },

    // Lighten a hex color by percentage
    lighten(hex, percent) {
        const { r, g, b } = this.hexToRgb(hex);
        const factor = percent / 100;
        return this.rgbToHex(
            r + (255 - r) * factor,
            g + (255 - g) * factor,
            b + (255 - b) * factor
        );
    },

    // Create color with alpha
    alpha(hex, alpha) {
        const { r, g, b } = this.hexToRgb(hex);
        return `rgba(${r}, ${g}, ${b}, ${alpha})`;
    },

    // Distance between two points
    dist(x1, y1, x2, y2) {
        return Math.sqrt((x2 - x1) ** 2 + (y2 - y1) ** 2);
    },

    // Throttle function calls
    throttle(fn, delay) {
        let last = 0;
        return function(...args) {
            const now = Date.now();
            if (now - last >= delay) {
                last = now;
                return fn.apply(this, args);
            }
        };
    },

    // Debounce function calls
    debounce(fn, delay) {
        let timer;
        return function(...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    },

    // Simple event emitter
    createEmitter() {
        const listeners = {};
        return {
            on(event, fn) {
                (listeners[event] = listeners[event] || []).push(fn);
                return this;
            },
            off(event, fn) {
                if (listeners[event]) {
                    listeners[event] = listeners[event].filter(f => f !== fn);
                }
                return this;
            },
            emit(event, ...args) {
                (listeners[event] || []).forEach(fn => fn(...args));
                return this;
            }
        };
    },

    // Request animation frame polyfill / wrapper
    raf: window.requestAnimationFrame || window.webkitRequestAnimationFrame || window.mozRequestAnimationFrame || function(cb) { setTimeout(cb, 16); },

    // Get current timestamp in ms
    now() { return performance.now(); }
};
