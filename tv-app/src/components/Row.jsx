import { Tile } from './Tile';

export const TILE_STEP = 312; // tile width 280 + margin 32

/** A titled, sideways-scrolling row. The focused tile stays second from the left. */
export function Row({ row, focusedCol, showImages, above }) {
    const shift = focusedCol > 1 ? (focusedCol - 1) * TILE_STEP : 0;

    return (
        <section class={'row' + (focusedCol >= 0 ? ' is-active' : '') + (above ? ' is-above' : '')}>
            <h2 class="row__title">{row.title}</h2>
            <div class="row__track" style={{ transform: 'translateX(' + -shift + 'px)' }}>
                {row.items.map((item, i) => (
                    <Tile
                        key={(item.slug || item.id) + '-' + i}
                        item={item}
                        kind={row.kind}
                        focused={i === focusedCol}
                        showImage={showImages}
                    />
                ))}
            </div>
        </section>
    );
}
