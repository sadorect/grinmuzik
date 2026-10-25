import { useEffect, useState } from 'preact/hooks';
import { get } from '../api';
import { useKeys } from '../hooks';
import { currentTrack, playQueue } from '../player';
import { Row } from '../components/Row';

const ROW_HEIGHT = 470;

export function navItems(session) {
    const items = [
        { key: 'home', label: 'Home' },
        { key: 'search', label: 'Search' },
        { key: 'nowplaying', label: 'Playing' },
    ];

    items.push(session ? { key: 'account', label: session.user ? session.user.name : 'Account' } : { key: 'signin', label: 'Sign in' });

    return items;
}

/** Opens what a tile stands for: a song plays (with its row queued after it), anything else opens its page. */
export function openItem(nav, kind, items, index) {
    const item = items[index];

    if (kind === 'track') {
        playQueue(items, index);
        nav.open({ type: 'nowplaying' });
    } else {
        nav.open({ type: kind, slug: item.slug, title: item.title || item.name });
    }
}

export function Home({ nav, active, session, sessionVersion }) {
    const [rows, setRows] = useState(null);
    const [failed, setFailed] = useState(false);
    const [zone, setZone] = useState('rows');
    const [navIndex, setNavIndex] = useState(0);
    const [rowIndex, setRowIndex] = useState(0);
    const [cols, setCols] = useState({});

    function load() {
        setFailed(false);
        get('/home')
            .then((data) => {
                setRows(data.rows);
                setRowIndex(0);
                setCols({});
            })
            .catch(() => setFailed(true));
    }

    useEffect(load, [sessionVersion]);

    const items = navItems(session);

    function choose(key) {
        if (key === 'search') nav.open({ type: 'search' });
        if (key === 'signin') nav.open({ type: 'signin' });
        if (key === 'account') nav.open({ type: 'account' });
        if (key === 'nowplaying' && currentTrack()) nav.open({ type: 'nowplaying' });
        if (key === 'home') setZone(rows && rows.length ? 'rows' : 'nav');
    }

    useKeys((key) => {
        if (failed) {
            if (key.name === 'enter') {
                load();
                return true;
            }
            return false;
        }

        if (zone === 'nav' || !rows || rows.length === 0) {
            if (key.name === 'up') setNavIndex(Math.max(0, navIndex - 1));
            else if (key.name === 'down') setNavIndex(Math.min(items.length - 1, navIndex + 1));
            else if (key.name === 'right' && rows && rows.length) setZone('rows');
            else if (key.name === 'enter') choose(items[navIndex].key);
            else return false;
            return true;
        }

        const row = rows[rowIndex];
        const col = cols[rowIndex] || 0;

        if (key.name === 'up') setRowIndex(Math.max(0, rowIndex - 1));
        else if (key.name === 'down') setRowIndex(Math.min(rows.length - 1, rowIndex + 1));
        else if (key.name === 'left') {
            if (col === 0) setZone('nav');
            else setCols(Object.assign({}, cols, { [rowIndex]: col - 1 }));
        } else if (key.name === 'right') setCols(Object.assign({}, cols, { [rowIndex]: Math.min(row.items.length - 1, col + 1) }));
        else if (key.name === 'enter') openItem(nav, row.kind, row.items, col);
        else return false;
        return true;
    }, active);

    return (
        <div class="screen home">
            <nav class={'rail' + (zone === 'nav' ? ' is-open' : '')}>
                <div class="rail__brand">GrinMuzik</div>
                {items.map((item, i) => (
                    <div key={item.key} class={'rail__item' + (zone === 'nav' && i === navIndex ? ' is-focused' : '')}>
                        {item.label}
                    </div>
                ))}
            </nav>

            <main class="home__content">
                {failed ? (
                    <div class="message">
                        <h1>Can't reach GrinMuzik</h1>
                        <p>Check the TV's internet connection, then press OK to try again.</p>
                    </div>
                ) : !rows ? (
                    <div class="message">
                        <p>Loading music…</p>
                    </div>
                ) : rows.length === 0 ? (
                    <div class="message">
                        <p>No music to show yet.</p>
                    </div>
                ) : (
                    <div class="home__rows" style={{ transform: 'translateY(' + -(rowIndex * ROW_HEIGHT) + 'px)' }}>
                        {rows.map((row, i) => (
                            <Row
                                key={row.key}
                                row={row}
                                focusedCol={zone === 'rows' && i === rowIndex ? cols[i] || 0 : -1}
                                showImages={Math.abs(i - rowIndex) <= 2}
                                above={i < rowIndex}
                            />
                        ))}
                    </div>
                )}
            </main>
        </div>
    );
}
