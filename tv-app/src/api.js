// Talks to grinmuzik.com's /api/tv endpoints.
//
// Served from a web page (grinmuzik.com/tv-app, or a local dev server) the
// API is on the same origin. Packaged as a TV app the page is a local file,
// so it calls the live site by its full address.
const LIVE_ORIGIN = 'https://grinmuzik.com';
const TOKEN_KEY = 'grinmuzik.tv.token';
const USER_KEY = 'grinmuzik.tv.user';

function origin() {
    const override = /[?&]api=([^&]+)/.exec(window.location.search);

    if (override) {
        return decodeURIComponent(override[1]);
    }

    return window.location.protocol.indexOf('http') === 0 ? '' : LIVE_ORIGIN;
}

function read(key) {
    try {
        return window.localStorage.getItem(key);
    } catch (e) {
        return null;
    }
}

function write(key, value) {
    try {
        if (value === null) {
            window.localStorage.removeItem(key);
        } else {
            window.localStorage.setItem(key, value);
        }
    } catch (e) {
        // Storage refused (private mode): the TV just forgets sign-in on restart.
    }
}

export function getSession() {
    const token = read(TOKEN_KEY);
    let user = null;

    try {
        user = JSON.parse(read(USER_KEY) || 'null');
    } catch (e) {
        user = null;
    }

    return token ? { token, user } : null;
}

export function saveSession(token, user) {
    write(TOKEN_KEY, token);
    write(USER_KEY, JSON.stringify(user));
}

export function clearSession() {
    write(TOKEN_KEY, null);
    write(USER_KEY, null);
}

/**
 * Resolves to { status, data } for any HTTP answer, so callers can read the
 * device-flow error bodies; rejects only when the network itself failed.
 */
export function request(method, path, body) {
    const headers = { Accept: 'application/json' };
    const session = getSession();

    if (session) {
        headers.Authorization = 'Bearer ' + session.token;
    }

    if (body) {
        headers['Content-Type'] = 'application/json';
    }

    return fetch(origin() + '/api/tv' + path, {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined,
    }).then((response) => {
        // A token revoked from the phone: carry on signed out.
        if (response.status === 401 && session) {
            clearSession();
        }

        return response.text().then((text) => {
            let data = null;

            try {
                data = text ? JSON.parse(text) : null;
            } catch (e) {
                data = null;
            }

            return { status: response.status, data };
        });
    });
}

/** GET that rejects on anything but 200, for screens that just show data. */
export function get(path) {
    return request('GET', path).then((result) => {
        if (result.status !== 200) {
            const error = new Error('HTTP ' + result.status);
            error.status = result.status;
            throw error;
        }

        return result.data;
    });
}
