/* AMCS shared browser helpers (vanilla JS, no build step required). */
window.AMCS = (function () {
    'use strict';

    function getCookie(name) {
        const match = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
        return match ? decodeURIComponent(match.pop()) : null;
    }

    function escapeHtml(value) {
        if (value === null || value === undefined) return '';
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function apiBase() {
        const meta = document.querySelector('meta[name="amcs-api-base"]');
        return meta && meta.content ? meta.content.replace(/\/+$/, '') : '/api/v1';
    }

    async function api(path, options) {
        options = options || {};
        const method = (options.method || 'GET').toUpperCase();

        let url = apiBase() + path;
        if (options.query) {
            const params = new URLSearchParams();
            Object.entries(options.query).forEach(([key, value]) => {
                if (value !== null && value !== undefined && value !== '') params.append(key, value);
            });
            if (params.toString()) url += '?' + params.toString();
        }

        const headers = {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        };

        const init = { method: method, headers: headers, credentials: 'same-origin' };

        if (options.body !== undefined && options.body !== null) {
            headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(options.body);
        }

        if (method !== 'GET' && method !== 'HEAD') {
            const token = getCookie('XSRF-TOKEN');
            if (token) headers['X-XSRF-TOKEN'] = token;
        }

        const response = await fetch(url, init);
        const contentType = response.headers.get('content-type') || '';
        let data = null;

        if (contentType.includes('application/json')) {
            data = await response.json().catch(() => null);
        }

        if (!response.ok) {
            const error = new Error((data && data.message) || ('Permintaan gagal (HTTP ' + response.status + ')'));
            error.status = response.status;
            error.errors = (data && data.errors) || {};
            error.payload = data;
            throw error;
        }

        return data;
    }

    function toast(title, message, type) {
        type = type || 'info';
        let host = document.getElementById('toasts');
        if (!host) {
            host = document.createElement('div');
            host.id = 'toasts';
            document.body.appendChild(host);
        }

        const el = document.createElement('div');
        el.className = 'toast ' + type;
        el.innerHTML = '<b>' + escapeHtml(title) + '</b>' + escapeHtml(message || '');

        host.appendChild(el);
        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateX(20px)';
            el.style.transition = 'all 0.25s';
            setTimeout(() => el.remove(), 260);
        }, type === 'error' ? 7000 : 4500);
    }

    function errorToast(error, fallbackTitle) {
        let detail = error && error.message ? error.message : 'Terjadi kesalahan.';
        if (error && error.errors) {
            const first = Object.values(error.errors)[0];
            if (Array.isArray(first) && first.length) detail = first[0];
        }
        toast(fallbackTitle || 'Gagal', detail, 'error');
    }

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function clockString(date) {
        date = date || new Date();
        const dd = pad(date.getDate());
        const mm = pad(date.getMonth() + 1);
        const yyyy = date.getFullYear();
        return dd + '/' + mm + '/' + yyyy + ' - ' + pad(date.getHours()) + ':' + pad(date.getMinutes()) + ':' + pad(date.getSeconds()) + ' WITA';
    }

    function shortClock(date) {
        date = date || new Date();
        return pad(date.getHours()) + ':' + pad(date.getMinutes()) + ':' + pad(date.getSeconds());
    }

    function todayISO() {
        const now = new Date();
        return now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
    }

    function startClock(elementId, formatFn) {
        const el = document.getElementById(elementId);
        if (!el) return;
        const render = () => { el.innerText = (formatFn || clockString)(new Date()); };
        render();
        setInterval(render, 1000);
    }

    function statusClass(state) {
        switch (state) {
            case 'VACANT': return 'status-vacant';
            case 'ENTERING': return 'status-approaching';
            case 'ON': return 'status-occupied';
            case 'RON': return 'status-ron';
            case 'DELAY': return 'status-delay';
            default: return 'status-vacant';
        }
    }

    function isoTimestamp() {
        const now = new Date();
        return now.toLocaleTimeString('id-ID', { hour12: false });
    }

    return {
        api: api,
        apiUrl: function (path) { return apiBase() + path; },
        toast: toast,
        errorToast: errorToast,
        escapeHtml: escapeHtml,
        clockString: clockString,
        shortClock: shortClock,
        todayISO: todayISO,
        startClock: startClock,
        statusClass: statusClass,
        getCookie: getCookie,
    };
})();
