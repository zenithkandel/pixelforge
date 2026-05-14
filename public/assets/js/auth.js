import { api } from './api.js';

export class AuthManager {
    constructor() {
        this.user = null;
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    setCsrfToken(token) {
        this.csrfToken = token;
        api.setCsrfToken(token);
    }

    async register(username, email, password) {
        try {
            const result = await api.post('/api/auth/register.php', {
                username,
                email,
                password,
                csrf_token: this.csrfToken
            });
            return { success: true, message: result.data.message };
        } catch (err) {
            return { success: false, message: err.message };
        }
    }

    async login(username, password) {
        try {
            const result = await api.post('/api/auth/login.php', {
                username,
                password,
                csrf_token: this.csrfToken
            });
            this.user = result.data;
            return { success: true, data: result.data };
        } catch (err) {
            return { success: false, message: err.message };
        }
    }

    async logout() {
        try {
            await api.post('/api/auth/logout.php', {});
            this.user = null;
            return { success: true };
        } catch (err) {
            return { success: false, message: err.message };
        }
    }

    async checkAuth() {
        try {
            const result = await api.get('/api/auth/me.php');
            this.user = result.data;
            return { success: true, user: result.data };
        } catch (err) {
            this.user = null;
            return { success: false };
        }
    }

    isLoggedIn() {
        return !!this.user;
    }

    getUser() {
        return this.user;
    }

    updateBalance(newBalance) {
        if (this.user) {
            this.user.pxl_balance = newBalance;
        }
    }
}

export const auth = new AuthManager();