// public/assets/js/api.js

export function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

export async function apiPost(url, data = {}) {
    const token = getCsrfToken();
    if (token) data.csrf_token = token;
    
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': token
        },
        body: JSON.stringify(data)
    });
    return res.json();
}

export async function apiGet(url) {
    const res = await fetch(url);
    return res.json();
}
