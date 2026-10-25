import { useState } from 'preact/hooks';
import { formatTime, useKeys, usePlayer } from '../hooks';
import { currentTrack, next, playAt, previous, toggle } from '../player';

const CONTROLS = ['previous', 'toggle', 'next'];
const QUEUE_ROW = 104;

/** Full-screen player: big art, progress, controls, and what's coming up. */
export function NowPlaying({ nav, active }) {
    const state = usePlayer();
    const track = currentTrack();
    const [zone, setZone] = useState('controls');
    const [control, setControl] = useState(1);
    const [queueIndex, setQueueIndex] = useState(0);

    const upcoming = state.queue.slice(state.index + 1);

    useKeys((key) => {
        if (!track) {
            return false;
        }

        if (zone === 'controls') {
            if (key.name === 'left') setControl(Math.max(0, control - 1));
            else if (key.name === 'right') {
                if (control === CONTROLS.length - 1 && upcoming.length) {
                    setZone('queue');
                    setQueueIndex(0);
                } else setControl(Math.min(CONTROLS.length - 1, control + 1));
            } else if (key.name === 'enter') {
                if (CONTROLS[control] === 'previous') previous();
                if (CONTROLS[control] === 'toggle') toggle();
                if (CONTROLS[control] === 'next') next();
            } else return false;
            return true;
        }

        if (key.name === 'up') setQueueIndex(Math.max(0, queueIndex - 1));
        else if (key.name === 'down') setQueueIndex(Math.min(upcoming.length - 1, queueIndex + 1));
        else if (key.name === 'left') setZone('controls');
        else if (key.name === 'enter') {
            playAt(state.index + 1 + queueIndex);
            setQueueIndex(0);
        } else return false;
        return true;
    }, active);

    if (!track) {
        return (
            <div class="screen message">
                <h1>Nothing playing</h1>
                <p>Pick a song from Home or Search.</p>
            </div>
        );
    }

    const percent = state.duration ? Math.min(100, (state.currentTime / state.duration) * 100) : 0;
    const queueShift = zone === 'queue' && queueIndex > 3 ? (queueIndex - 3) * QUEUE_ROW : 0;

    return (
        <div class="screen playing">
            <div class="playing__art">
                <img src={track.artwork_url || track.cover_url} alt="" />
            </div>

            <div class="playing__main">
                <div class="playing__label">{state.loading ? 'Loading…' : state.playing ? 'Now playing' : 'Paused'}</div>
                <h1 class="playing__title">{track.title}</h1>
                <p class="playing__artist">{track.artist ? track.artist.name : ''}</p>
                {state.error ? <p class="playing__error">{state.error} Press OK on play to try again.</p> : null}

                <div class="progress">
                    <div class="progress__bar">
                        <div class="progress__fill" style={{ width: percent + '%' }} />
                    </div>
                    <div class="progress__times">
                        <span>{formatTime(state.currentTime)}</span>
                        <span>{formatTime(state.duration)}</span>
                    </div>
                </div>

                <div class="controls">
                    {CONTROLS.map((name, i) => (
                        <div
                            key={name}
                            class={'control control--' + name + (zone === 'controls' && i === control ? ' is-focused' : '')}
                        >
                            {name === 'previous' ? 'Previous' : name === 'next' ? 'Next' : state.playing ? 'Pause' : 'Play'}
                        </div>
                    ))}
                </div>
                <p class="playing__hint">The play, pause, rewind and fast-forward keys on your remote work too.</p>
            </div>

            <div class="playing__queue">
                <h2 class="playing__queue-title">Up next</h2>
                {upcoming.length === 0 ? <p class="playing__queue-empty">More picks will follow this song.</p> : null}
                <div class="playing__queue-track" style={{ transform: 'translateY(' + -queueShift + 'px)' }}>
                    {upcoming.map((item, i) => (
                        <div key={item.slug + '-' + i} class={'queue-row' + (zone === 'queue' && i === queueIndex ? ' is-focused' : '')}>
                            <img class="queue-row__art" src={item.cover_url} alt="" />
                            <span class="queue-row__text">
                                <span class="queue-row__title">{item.title}</span>
                                <span class="queue-row__artist">{item.artist ? item.artist.name : ''}</span>
                            </span>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
