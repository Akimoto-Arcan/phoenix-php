/**
 * Point of Sale — API Client
 * PhoenixPHP Module — CDAC Programming
 *
 * All API calls route through the module's routes.php using ?path= query parameter.
 */
window.PosAPI = (function () {
    'use strict';

    var BASE = '/modules/pos/api/routes.php';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function buildUrl(path, params) {
        var url = BASE + '?path=' + encodeURIComponent(path);
        if (params) {
            Object.keys(params).forEach(function (key) {
                if (params[key] !== undefined && params[key] !== null && params[key] !== '') {
                    url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
                }
            });
        }
        return url;
    }

    function handleResponse(response) {
        if (response.status === 401) {
            window.location.href = '/login.php';
            return Promise.reject(new Error('Authentication required'));
        }
        return response.json().then(function (data) {
            if (!response.ok || data.ok === false) {
                var msg = data.error || 'Request failed';
                return Promise.reject(new Error(msg));
            }
            return data;
        });
    }

    function get(path, params) {
        return fetch(buildUrl(path, params), {
            method: 'GET',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        }).then(handleResponse);
    }

    function post(path, body) {
        return fetch(buildUrl(path), {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken()
            },
            body: JSON.stringify(body || {})
        }).then(handleResponse);
    }

    function put(path, body) {
        return fetch(buildUrl(path), {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken()
            },
            body: JSON.stringify(body || {})
        }).then(handleResponse);
    }

    function del(path) {
        return fetch(buildUrl(path), {
            method: 'DELETE',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-Token': csrfToken()
            }
        }).then(handleResponse);
    }

    return {
        get: get,
        post: post,
        put: put,
        del: del,
        buildUrl: buildUrl
    };
})();
