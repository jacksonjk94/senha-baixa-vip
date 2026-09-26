import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Garante o token CSRF em toda requisição XHR — incluindo as do Inertia,
// que usa esta mesma instância do axios. Sem ele, POSTs (login/logout)
// retornam 419 quando o header automático não é anexado (ex: ambientes
// serverless/proxy). Lê o cookie XSRF-TOKEN no momento do envio.
function xsrfTokenFromCookie() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? match[1] : null;
}

function setHeaderIfMissing(headers, name, value) {
    if (headers && typeof headers.set === 'function' && typeof headers.get === 'function') {
        if (!headers.get(name)) headers.set(name, value);
        return;
    }
    if (headers && !headers[name]) {
        headers[name] = value;
    }
}

window.axios.interceptors.request.use((config) => {
    const token = xsrfTokenFromCookie();
    if (token) {
        config.headers = config.headers || {};
        setHeaderIfMissing(config.headers, 'X-XSRF-TOKEN', token);
    }
    return config;
});
