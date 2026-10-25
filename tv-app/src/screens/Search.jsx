import { useEffect, useRef, useState } from 'preact/hooks';
import { get } from '../api';
import { useKeys } from '../hooks';
import { Row } from '../components/Row';
import { openItem } from './Home';

const COLUMNS = 6;
const LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890'.split('');
// The last keyboard row: three wide keys, each two columns across.
const ACTIONS = [
    { key: 'space', label: 'Space' },
    { key: 'delete', label: 'Delete' },
    { key: 'clear', label: 'Clear' },
];
const LETTER_ROWS = Math.ceil(LETTERS.length / COLUMNS);
const ROW_HEIGHT = 470;

function resultRows(results) {
    if (!results) {
        return [];
    }

    return [
        { key: 'tracks', title: 'Songs', kind: 'track', items: results.tracks },
        { key: 'artists', title: 'Artists', kind: 'artist', items: results.artists },
        { key: 'albums', title: 'Albums & EPs', kind: 'album', items: results.albums },
    ].filter((row) => row.items.length > 0);
}

export function Search({ nav, active }) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState(null);
    const [searching, setSearching] = useState(false);
    const [zone, setZone] = useState('keyboard');
    const [keyRow, setKeyRow] = useState(0);
    const [keyCol, setKeyCol] = useState(0);
    const [rowIndex, setRowIndex] = useState(0);
    const [cols, setCols] = useState({});
    const latest = useRef(0);

    // Search as you type, once the typing pauses.
    useEffect(() => {
        if (query.trim() === '') {
            setResults(null);
            return undefined;
        }

        const ticket = ++latest.current;
        setSearching(true);
        const timer = setTimeout(() => {
            get('/search?q=' + encodeURIComponent(query.trim()))
                .then((data) => {
                    if (ticket === latest.current) {
                        setResults(data);
                        setRowIndex(0);
                        setCols({});
                    }
                })
                .catch(() => {})
                .then(() => {
                    if (ticket === latest.current) setSearching(false);
                });
        }, 400);

        return () => clearTimeout(timer);
    }, [query]);

    const rows = resultRows(results);

    function press(keyName) {
        if (keyName === 'space') setQuery(query + ' ');
        else if (keyName === 'delete') setQuery(query.slice(0, -1));
        else if (keyName === 'clear') setQuery('');
        else setQuery(query + keyName.toLowerCase());
    }

    function keyAt(row, col) {
        if (row === LETTER_ROWS) {
            return ACTIONS[Math.floor(col / 2)].key;
        }

        return LETTERS[row * COLUMNS + col];
    }

    useKeys((key) => {
        // Typing on a computer keyboard, for testing.
        if (key.name === 'char') {
            setQuery(query + key.char.toLowerCase());
            return true;
        }
        if (key.name === 'space') {
            setQuery(query + ' ');
            return true;
        }
        if (key.name === 'backspace') {
            setQuery(query.slice(0, -1));
            return true;
        }

        if (zone === 'keyboard') {
            if (key.name === 'up') setKeyRow(Math.max(0, keyRow - 1));
            else if (key.name === 'down') setKeyRow(Math.min(LETTER_ROWS, keyRow + 1));
            else if (key.name === 'left') {
                // On the wide-key row a step is a whole key, two columns.
                setKeyCol(keyRow === LETTER_ROWS ? Math.max(0, (Math.floor(keyCol / 2) - 1) * 2) : Math.max(0, keyCol - 1));
            } else if (key.name === 'right') {
                const last = keyRow === LETTER_ROWS ? Math.floor(keyCol / 2) === ACTIONS.length - 1 : keyCol === COLUMNS - 1;
                if (last && rows.length) setZone('results');
                else if (!last) setKeyCol(keyRow === LETTER_ROWS ? (Math.floor(keyCol / 2) + 1) * 2 : keyCol + 1);
            } else if (key.name === 'enter') press(keyAt(keyRow, keyCol));
            else return false;
            return true;
        }

        const row = rows[rowIndex];
        const col = cols[rowIndex] || 0;

        if (!row) {
            setZone('keyboard');
            return true;
        }

        if (key.name === 'up') setRowIndex(Math.max(0, rowIndex - 1));
        else if (key.name === 'down') setRowIndex(Math.min(rows.length - 1, rowIndex + 1));
        else if (key.name === 'left') {
            if (col === 0) setZone('keyboard');
            else setCols(Object.assign({}, cols, { [rowIndex]: col - 1 }));
        } else if (key.name === 'right') setCols(Object.assign({}, cols, { [rowIndex]: Math.min(row.items.length - 1, col + 1) }));
        else if (key.name === 'enter') openItem(nav, row.kind, row.items, col);
        else return false;
        return true;
    }, active);

    return (
        <div class="screen search">
            <div class="search__side">
                <div class="search__query">
                    {query || <span class="search__placeholder">Search songs, artists, albums</span>}
                    <span class="search__caret" />
                </div>
                <div class="keyboard">
                    {LETTERS.map((letter, i) => {
                        const focused = zone === 'keyboard' && keyRow === Math.floor(i / COLUMNS) && keyCol === i % COLUMNS;
                        return (
                            <div key={letter} class={'key' + (focused ? ' is-focused' : '')}>
                                {letter}
                            </div>
                        );
                    })}
                    {ACTIONS.map((action, i) => {
                        const focused = zone === 'keyboard' && keyRow === LETTER_ROWS && Math.floor(keyCol / 2) === i;
                        return (
                            <div key={action.key} class={'key key--wide' + (focused ? ' is-focused' : '')}>
                                {action.label}
                            </div>
                        );
                    })}
                </div>
            </div>

            <div class="search__results">
                {query.trim() === '' ? (
                    <p class="search__hint">Use the letters on the left to start searching.</p>
                ) : rows.length === 0 ? (
                    <p class="search__hint">{searching ? 'Searching…' : 'No matches for "' + query.trim() + '".'}</p>
                ) : (
                    <div class="search__rows" style={{ transform: 'translateY(' + -(rowIndex * ROW_HEIGHT) + 'px)' }}>
                        {rows.map((row, i) => (
                            <Row
                                key={row.key}
                                row={row}
                                focusedCol={zone === 'results' && i === rowIndex ? cols[i] || 0 : -1}
                                showImages
                                above={i < rowIndex}
                            />
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}
