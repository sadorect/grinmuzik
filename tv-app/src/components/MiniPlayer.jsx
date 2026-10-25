import { usePlayer, formatTime } from '../hooks';
import { currentTrack } from '../player';

/** The strip along the bottom while something is playing. */
export function MiniPlayer() {
    const state = usePlayer();
    const track = currentTrack();

    if (!track) {
        return null;
    }

    const percent = state.duration ? Math.min(100, (state.currentTime / state.duration) * 100) : 0;

    return (
        <div class="mini">
            <div class="mini__progress" style={{ width: percent + '%' }} />
            <img class="mini__art" src={track.cover_url} alt="" />
            <div class="mini__text">
                <div class="mini__title">{track.title}</div>
                <div class="mini__artist">{state.error || (track.artist ? track.artist.name : '')}</div>
            </div>
            <div class="mini__state">
                {state.loading ? 'Loading…' : state.playing ? 'Playing' : 'Paused'}
                <span class="mini__time">
                    {formatTime(state.currentTime)} / {formatTime(state.duration)}
                </span>
            </div>
        </div>
    );
}
