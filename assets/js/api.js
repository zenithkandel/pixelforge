import { getCsrfToken, showError } from './utils.js';

export class ApiClient {
    constructor(baseUrl = '') {
        this.baseUrl = baseUrl;
    }

    async request(method, path, data = null) {
        const url = this.baseUrl + path;
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
            },
        };

        if (method !== 'GET' && method !== 'HEAD') {
            const csrfToken = getCsrfToken();
            if (csrfToken) {
                options.headers['X-CSRF-Token'] = csrfToken;
            }
            if (data) {
                options.body = JSON.stringify(data);
            }
        }

        try {
            const response = await fetch(url, options);
            const result = await response.json();

            if (!response.ok) {
                if (result.error === 'unauthorized' || result.error === 'email_not_verified') {
                    // Redirect to login
                    window.location.href = '/index.php';
                    return null;
                }
                throw new Error(result.message || result.error || 'Request failed');
            }

            return result.data || result;
        } catch (error) {
            showError(error.message);
            return null;
        }
    }

    get(path) {
        return this.request('GET', path);
    }

    post(path, data) {
        return this.request('POST', path, data);
    }

    put(path, data) {
        return this.request('PUT', path, data);
    }

    delete(path) {
        return this.request('DELETE', path);
    }
}

// Global API instance
export const api = new ApiClient();
