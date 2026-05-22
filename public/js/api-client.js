export class ApiClient {
    constructor(baseUrl = '') {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    }

    async get(url, params = {}) {
        const query = new URLSearchParams(params).toString();
        const fullUrl = this.baseUrl + url + (query ? '?' + query : '');
        return this._request(fullUrl, { method: 'GET' });
    }

    async post(url, data = {}) {
        return this._request(this.baseUrl + url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    }

    async put(url, data = {}) {
        return this._request(this.baseUrl + url, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
    }

    async delete(url) {
        return this._request(this.baseUrl + url, { method: 'DELETE' });
    }

    async _request(url, options = {}) {
        const headers = { 'Accept': 'application/json', ...options.headers };

        if (['POST', 'PUT', 'DELETE'].includes(options.method) && this.csrfToken) {
            headers['X-CSRF-Token'] = this.csrfToken;
        }

        const response = await fetch(url, { ...options, headers, credentials: 'same-origin' });

        if (response.status === 401) {
            window.location.href = '/login';
            throw new Error('Unauthorized');
        }

        const body = await response.json().catch(() => null);

        if (!response.ok) {
            const message = body?.error || body?.message || `Request failed (${response.status})`;
            throw new Error(message);
        }

        return body;
    }
}
