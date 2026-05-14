export function lerp(a, b, t) { return a + (b - a) * t; }
export function clamp(v, min, max) { return Math.min(Math.max(v, min), max); }
export function randInt(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }
export function randFloat(min, max) { return Math.random() * (max - min) + min; }

export function now() { return performance.now(); }
export function elapsed(start) { return performance.now() - start; }