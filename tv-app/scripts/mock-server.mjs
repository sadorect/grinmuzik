// Serves dist/ plus a fake /api/tv, so the TV interface can be run and
// tested without the Laravel app.  node scripts/mock-server.mjs [port] [audio-file]
//
// Covers are generated SVGs; every track plays the same audio file. Sign-in
// is approved automatically on the second poll. GET /mock/log lists the plays
// the interface reported.
import { createServer } from 'node:http';
import { existsSync, readFileSync } from 'node:fs';
import { extname, join } from 'node:path';

const port = Number(process.argv[2] || 8130);
const audioFile = process.argv[3] || null;
const dist = new URL('../dist/', import.meta.url).pathname;
const plays = [];
let polls = 0;

const COLOURS = ['#4f74f0', '#e8505b', '#14a76c', '#f9a03f', '#8e5cd9', '#1fa2c2', '#d94f8a', '#5c6b8a'];
const ARTISTS = ['Ada Sings', "D'son", 'Tolu Grace', 'Brother Emeka', 'Kemi Joy', 'Sola Praise'];

const cover = (n) => '/mock/cover/' + n + '.svg';

function track(n) {
    return {
        id: n,
        slug: 'song-' + n,
        title: ['Yahweh', 'Enlarge My Coast', 'Thank You', 'Alubarika', 'Agbara', 'Morning Light', 'Grace Upon Grace'][n % 7] + (n > 6 ? ' ' + n : ''),
        artist: { slug: 'artist-' + (n % ARTISTS.length), name: ARTISTS[n % ARTISTS.length] },
        album: null,
        duration: 180 + (n % 5) * 17,
        cover_url: cover(n),
        artwork_url: cover(n),
    };
}

const tracks = (from, count) => Array.from({ length: count }, (_, i) => track(from + i));
const album = (n) => ({ slug: 'album-' + n, title: 'Album ' + n, type: n % 2 ? 'ep' : 'album', artist: track(n).artist, release_year: 2026, track_count: 8, cover_url: cover(100 + n), artwork_url: cover(100 + n) });
const artist = (n) => ({ slug: 'artist-' + n, name: ARTISTS[n % ARTISTS.length], image_url: cover(200 + n) });
const playlist = (n) => ({ slug: 'playlist-' + n, name: ['Sunday Mornings', 'Worship Night', 'Afrobeats Mix'][n % 3], owner: 'GrinMuzik', track_count: 12, cover_url: cover(300 + n) });

function signedIn(req) {
    return /^Bearer /.test(req.headers.authorization || '');
}

function home(req) {
    const rows = [];

    if (signedIn(req)) {
        rows.push({ key: 'recent', title: 'Recently played', kind: 'track', items: tracks(40, 6) });
        rows.push({ key: 'liked', title: 'Liked songs', kind: 'track', items: tracks(50, 4) });
    }

    return {
        rows: rows.concat([
            { key: 'featured', title: 'Featured', kind: 'track', items: tracks(0, 12) },
            { key: 'trending', title: 'Trending now', kind: 'track', items: tracks(12, 10) },
            { key: 'new', title: 'New releases', kind: 'track', items: tracks(22, 15) },
            { key: 'albums', title: 'Albums & EPs', kind: 'album', items: Array.from({ length: 8 }, (_, i) => album(i)) },
            { key: 'artists', title: 'Artists', kind: 'artist', items: Array.from({ length: 6 }, (_, i) => artist(i)) },
            { key: 'playlists', title: 'Playlists', kind: 'playlist', items: Array.from({ length: 3 }, (_, i) => playlist(i)) },
        ]),
    };
}

function svg(n) {
    const colour = COLOURS[n % COLOURS.length];
    return `<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300"><rect width="300" height="300" fill="${colour}"/><circle cx="150" cy="150" r="90" fill="rgba(255,255,255,0.18)"/><text x="150" y="172" font-family="Arial" font-size="64" font-weight="bold" fill="#fff" text-anchor="middle">${n}</text></svg>`;
}

function send(res, status, body, type) {
    res.writeHead(status, { 'Content-Type': type || 'application/json', 'Access-Control-Allow-Origin': '*' });
    res.end(typeof body === 'string' || Buffer.isBuffer(body) ? body : JSON.stringify(body));
}

function readBody(req) {
    return new Promise((resolve) => {
        let data = '';
        req.on('data', (chunk) => (data += chunk));
        req.on('end', () => {
            try {
                resolve(data ? JSON.parse(data) : {});
            } catch (e) {
                resolve({});
            }
        });
    });
}

async function api(req, res, path) {
    let m;

    if (path === '/home') return send(res, 200, home(req));
    if (path.startsWith('/search')) {
        const q = new URL('http://x' + path).searchParams.get('q') || '';
        return send(res, 200, q ? { tracks: tracks(60, 5), artists: [artist(1)], albums: [album(2)] } : { tracks: [], artists: [], albums: [] });
    }
    if ((m = /^\/albums\/album-(\d+)$/.exec(path))) return send(res, 200, { album: album(+m[1]), tracks: tracks(70, 8) });
    if ((m = /^\/artists\/artist-(\d+)$/.exec(path))) return send(res, 200, { artist: artist(+m[1]), tracks: tracks(80, 6), albums: [album(3), album(4)] });
    if ((m = /^\/playlists\/playlist-(\d+)$/.exec(path))) return send(res, 200, { playlist: playlist(+m[1]), tracks: tracks(90, 12) });
    if ((m = /^\/tracks\/song-(\d+)\/play$/.exec(path))) {
        if (req.method === 'POST') {
            const body = await readBody(req);
            plays.push(Object.assign({ slug: 'song-' + m[1] }, body));
            return send(res, 200, { counted: body.seconds_played >= 5 || body.completed });
        }
        return send(res, 200, {
            track: track(+m[1]),
            audio_url: '/mock/audio',
            audio_url_expires_at: new Date(Date.now() + 3600e3).toISOString(),
            play_count_min_seconds: 5,
            up_next: tracks(+m[1] + 500, 4),
        });
    }
    if (path === '/device-code' && req.method === 'POST') {
        polls = 0;
        return send(res, 200, { device_code: 'x'.repeat(64), user_code: 'KMTR-HXBQ', verification_uri: 'http://localhost/tv', verification_uri_complete: 'http://localhost/tv?code=KMTR-HXBQ', expires_in: 600, interval: 1 });
    }
    if (path === '/token' && req.method === 'POST') {
        polls++;
        return polls < 2 ? send(res, 400, { error: 'authorization_pending' }) : send(res, 200, { access_token: '1|mock', token_type: 'Bearer', user: { id: 7, name: 'Ada Listener', avatar_url: cover(400) } });
    }
    if (path === '/token' && req.method === 'DELETE') return send(res, 200, { message: 'Signed out' });

    return send(res, 404, { message: 'Not found' });
}

createServer((req, res) => {
    const path = req.url.split('?')[0];

    if (path.startsWith('/api/tv')) return api(req, res, req.url.slice('/api/tv'.length));
    if (path.startsWith('/mock/cover/')) return send(res, 200, svg(parseInt(path.slice(12), 10)), 'image/svg+xml');
    if (path === '/mock/log') return send(res, 200, plays);
    if (path === '/mock/audio') {
        if (!audioFile || !existsSync(audioFile)) return send(res, 404, 'no audio file given', 'text/plain');
        return send(res, 200, readFileSync(audioFile), 'audio/wav');
    }

    const file = join(dist, path === '/' ? 'index.html' : path);
    if (!file.startsWith(dist) || !existsSync(file)) return send(res, 404, 'not found', 'text/plain');
    const types = { '.html': 'text/html', '.js': 'application/javascript', '.css': 'text/css' };
    send(res, 200, readFileSync(file), types[extname(file)] || 'application/octet-stream');
}).listen(port, '127.0.0.1', () => console.log('TV mock on http://127.0.0.1:' + port));
