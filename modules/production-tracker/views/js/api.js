const BASE = '/modules/production-tracker/api/routes.php';
const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

async function request(path, options = {}) {
    const url = BASE + '?path=' + encodeURIComponent(path);
    const headers = { 'X-CSRF-Token': csrf, ...options.headers };
    if (options.body && typeof options.body === 'object') {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
    }
    const res = await fetch(url, { ...options, headers });
    const data = await res.json();
    if (!res.ok) throw new Error(data.error || 'Request failed');
    return data;
}

export const api = {
    get: (path) => request(path),
    post: (path, body) => request(path, { method: 'POST', body }),
    put: (path, body) => request(path, { method: 'PUT', body }),
    delete: (path) => request(path, { method: 'DELETE' }),
};
