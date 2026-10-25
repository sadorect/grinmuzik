export function subtitleFor(item, kind) {
    if (kind === 'track') {
        return item.artist ? item.artist.name : '';
    }

    if (kind === 'album') {
        const type = item.type === 'ep' ? 'EP' : 'Album';
        return [item.artist ? item.artist.name : null, item.release_year ? type + ' · ' + item.release_year : type]
            .filter(Boolean)
            .join(' — ');
    }

    if (kind === 'playlist') {
        return item.track_count ? item.track_count + (item.track_count === 1 ? ' song' : ' songs') : 'Playlist';
    }

    return 'Artist';
}

/** One cover in a row. Images are dropped for rows far off screen to spare the TV's memory. */
export function Tile({ item, kind, focused, showImage }) {
    const round = kind === 'artist';
    const image = round ? item.image_url : item.cover_url;

    return (
        <div class={'tile' + (focused ? ' is-focused' : '') + (round ? ' tile--round' : '')}>
            <div class="tile__art">{showImage && image ? <img src={image} alt="" /> : null}</div>
            <div class="tile__title">{round ? item.name : item.title || item.name}</div>
            <div class="tile__subtitle">{subtitleFor(item, kind)}</div>
        </div>
    );
}
