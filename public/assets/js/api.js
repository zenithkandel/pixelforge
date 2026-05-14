export class ApiClient {
    constructor() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    setCsrfToken(token) {
        this.csrfToken = token;
    }

    async post(url, data = {}) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken,
            },
            body: JSON.stringify(data),
            credentials: 'same-origin'
        });

        if (!res.ok && res.status !== 422 && res.status !== 429) {
            throw new Error(`HTTP ${res.status}`);
        }

        const json = await res.json();

        if (!json.ok) {
            throw new Error(json.message || 'Request failed');
        }

        return json;
    }

    async get(url) {
        const res = await fetch(url, { credentials: 'same-origin' });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        return res.json();
    }

    async getBinary(url) {
        const res = await fetch(url, { credentials: 'same-origin' });

        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        return {
            data: new Uint8Array(await res.arrayBuffer()),
            version: parseInt(res.headers.get('X-Chunk-Version') || '0'),
        };
    }
}

export const api = new ApiClient();