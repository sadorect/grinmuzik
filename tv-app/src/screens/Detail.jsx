import { useEffect, useState } from 'preact/hooks';
import { get } from '../api';
import { formatTime, useKeys } from '../hooks';
import { playQueue } from '../player';
import { subtitleFor } from '../components/Tile';

const LIST_ROW = 112;
const VISIBLE_ABOVE = 2;

function shuffled(tracks) {
    const copy = tracks.slice();

    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        const swap = copy[i];
        copy[i] = copy[j];
        copy[j] = swap;
    }

    return copy;
}

/** Album, artist and playlist pages: art and buttons on the left, a list on the right. */
export function Detail({ nav, active, type, slug, title }) {
    const [data, setData] = useState(null);
    const [failed, setFailed] = useState(false);
    const [zone, setZone] = useState('buttons');
    const [button, setButton] = useState(0);
    const [index, setIndex] = useState(0);

    useEffect(() => {
        get('/' + type + 's/' + encodeURIComponent(slug))
            .then(setData)
            .catch(() => setFailed(true));
    }, [type, slug]);

    const tracks = data ? data.tracks : [];
    // Artist pages list their albums after their songs.
    const list = tracks
        .map((track) => ({ kind: 'track', item: track }))
        .concat(data && data.albums ? data.albums.map((album) => ({ kind: 'album', item: album })) : []);

    const buttons = tracks.length ? ['Play', 'Shuffle'] : [];

    useKeys((key) => {
        if (!data) {
            return false;
        }

        if (zone === 'buttons' && buttons.length) {
            if (key.name === 'left') setButton(0);
            else if (key.name === 'right') {
                if (button === buttons.length - 1 && list.length) setZone('list');
                else setButton(Math.min(buttons.length - 1, button + 1));
            } else if (key.name === 'down' && list.length) setZone('list');
            else if (key.name === 'enter') {
                playQueue(button === 0 ? tracks : shuffled(tracks), 0);
                nav.open({ type: 'nowplaying' });
            } else return false;
            return true;
        }

        if (key.name === 'up') {
            if (index === 0 && buttons.length) setZone('buttons');
            else setIndex(Math.max(0, index - 1));
        } else if (key.name === 'down') setIndex(Math.min(list.length - 1, index + 1));
        else if (key.name === 'left' && buttons.length) setZone('buttons');
        else if (key.name === 'enter' && list[index]) {
            const entry = list[index];

            if (entry.kind === 'track') {
                playQueue(tracks, index);
                nav.open({ type: 'nowplaying' });
            } else {
                nav.open({ type: 'album', slug: entry.item.slug, title: entry.item.title });
            }
        } else return false;
        return true;
    }, active);

    if (failed) {
        return (
            <div class="screen message">
                <h1>This page isn't available</h1>
                <p>It may have been removed. Press Back to return.</p>
            </div>
        );
    }

    if (!data) {
        return (
            <div class="screen message">
                <p>Loading {title || ''}…</p>
            </div>
        );
    }

    const head = data.album || data.artist || data.playlist;
    const art = data.artist ? head.image_url : head.artwork_url || head.cover_url;
    const heading = head.title || head.name;
    const meta = data.album
        ? subtitleFor(head, 'album')
        : data.playlist
          ? [head.owner, subtitleFor(head, 'playlist')].filter(Boolean).join(' · ')
          : tracks.length + (tracks.length === 1 ? ' song' : ' songs');
    const shift = zone === 'list' && index > VISIBLE_ABOVE ? (index - VISIBLE_ABOVE) * LIST_ROW : 0;

    return (
        <div class="screen detail">
            <aside class="detail__side">
                <div class={'detail__art' + (data.artist ? ' detail__art--round' : '')}>
                    {art ? <img src={art} alt="" /> : null}
                </div>
                <h1 class="detail__title">{heading}</h1>
                <p class="detail__meta">{meta}</p>
                <div class="detail__buttons">
                    {buttons.map((label, i) => (
                        <div key={label} class={'button' + (zone === 'buttons' && i === button ? ' is-focused' : '')}>
                            {label}
                        </div>
                    ))}
                </div>
            </aside>

            <div class="detail__list">
                {list.length === 0 ? <p class="detail__empty">Nothing here yet.</p> : null}
                <div class="detail__list-track" style={{ transform: 'translateY(' + -shift + 'px)' }}>
                    {list.map((entry, i) => (
                        <div
                            key={entry.kind + entry.item.slug}
                            class={'list-row' + (zone === 'list' && i === index ? ' is-focused' : '')}
                        >
                            <span class="list-row__number">{entry.kind === 'track' ? i + 1 : ''}</span>
                            <img class="list-row__art" src={entry.item.cover_url} alt="" />
                            <span class="list-row__text">
                                <span class="list-row__title">{entry.item.title}</span>
                                <span class="list-row__subtitle">
                                    {entry.kind === 'album' ? subtitleFor(entry.item, 'album') : subtitleFor(entry.item, 'track')}
                                </span>
                            </span>
                            <span class="list-row__end">
                                {entry.kind === 'track' ? formatTime(entry.item.duration) : 'Open'}
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
