// The one audio element and the queue around it.
//
// Audio URLs are signed and expire, so tracks are queued as plain track
// records and each one's URL is fetched from /tracks/{slug}/play when it
// starts. That response also brings the site's "up next" picks, which are
// appended when the queue runs out, as on the website.
import { request } from './api';

const audio = new Audio();
audio.preload = 'auto';

const listeners = [];

const state = {
    queue: [],
    index: -1,
    playing: false,
    loading: false,
    currentTime: 0,
    duration: 0,
    error: null,
};

let upNext = [];
let threshold = 30;
let listened = 0;
let lastTick = 0;
let reported = false;
let retried = false;
let loadToken = 0;

function emit() {
    for (let i = 0; i < listeners.length; i++) {
        listeners[i](state);
    }
}

function set(changes) {
    Object.keys(changes).forEach((key) => {
        state[key] = changes[key];
    });
    emit();
}

export function subscribe(listener) {
    listeners.push(listener);

    return () => {
        const at = listeners.indexOf(listener);

        if (at !== -1) {
            listeners.splice(at, 1);
        }
    };
}

export function getState() {
    return state;
}

export function currentTrack() {
    return state.queue[state.index] || null;
}

function report(completed) {
    const track = currentTrack();

    if (!track || reported) {
        return;
    }

    reported = true;
    request('POST', '/tracks/' + track.slug + '/play', {
        seconds_played: Math.round(listened),
        completed,
    }).catch(() => {});
}

function load(resumeAt) {
    const track = currentTrack();

    if (!track) {
        return;
    }

    const token = ++loadToken;

    if (!resumeAt) {
        listened = 0;
        reported = false;
        retried = false;
    }

    set({ loading: true, error: null, currentTime: resumeAt || 0, duration: track.duration || 0 });

    request('GET', '/tracks/' + track.slug + '/play')
        .then((result) => {
            if (token !== loadToken) {
                return; // Superseded by a later skip.
            }

            if (result.status !== 200) {
                throw new Error('unavailable');
            }

            upNext = result.data.up_next || [];
            threshold = result.data.play_count_min_seconds || threshold;
            audio.src = result.data.audio_url;

            if (resumeAt) {
                audio.addEventListener('loadedmetadata', function seek() {
                    audio.removeEventListener('loadedmetadata', seek);
                    audio.currentTime = resumeAt;
                });
            }

            const started = audio.play();

            // Chrome 47 returns nothing from play(); newer engines a promise.
            if (started && started.catch) {
                started.catch(() => {});
            }
        })
        .catch(() => {
            if (token === loadToken) {
                set({ loading: false, playing: false, error: "This song can't be played right now." });
            }
        });
}

/** Play these tracks from the given position. */
export function playQueue(tracks, startIndex) {
    report(false);
    upNext = [];
    set({ queue: tracks.slice(), index: startIndex || 0 });
    load();
}

export function playAt(index) {
    if (index < 0 || index >= state.queue.length) {
        return;
    }

    report(false);
    set({ index });
    load();
}

export function next() {
    if (state.index + 1 >= state.queue.length) {
        // Out of queue: carry on with the site's picks for the last song.
        const queued = {};
        state.queue.forEach((track) => {
            queued[track.slug] = true;
        });
        const fresh = upNext.filter((track) => !queued[track.slug]);

        if (fresh.length === 0) {
            audio.pause();
            set({ playing: false });
            return;
        }

        set({ queue: state.queue.concat(fresh) });
    }

    playAt(state.index + 1);
}

export function previous() {
    if (audio.currentTime > 3 || state.index === 0) {
        audio.currentTime = 0;
        return;
    }

    playAt(state.index - 1);
}

export function toggle() {
    if (!currentTrack()) {
        return;
    }

    if (state.error) {
        load();
    } else if (audio.paused) {
        play();
    } else {
        audio.pause();
    }
}

export function play() {
    const started = audio.play();

    if (started && started.catch) {
        started.catch(() => {});
    }
}

export function pause() {
    audio.pause();
}

export function stop() {
    audio.pause();
    audio.currentTime = 0;
}

export function seekBy(seconds) {
    if (!currentTrack() || !audio.duration) {
        return;
    }

    audio.currentTime = Math.max(0, Math.min(audio.duration - 1, audio.currentTime + seconds));
}

audio.addEventListener('playing', () => {
    lastTick = audio.currentTime;
    set({ playing: true, loading: false, error: null });
});

audio.addEventListener('pause', () => set({ playing: false }));
audio.addEventListener('waiting', () => set({ loading: true }));
audio.addEventListener('durationchange', () => {
    if (audio.duration && isFinite(audio.duration)) {
        set({ duration: audio.duration });
    }
});

audio.addEventListener('timeupdate', () => {
    const now = audio.currentTime;
    const step = now - lastTick;

    // Count only real listening, not jumps from seeking.
    if (step > 0 && step < 2) {
        listened += step;
    }

    lastTick = now;

    if (!reported && listened >= threshold) {
        report(false);
    }

    // Re-render about once a second, not on every timeupdate.
    if (Math.floor(now) !== Math.floor(state.currentTime)) {
        set({ currentTime: now });
    }
});

audio.addEventListener('ended', () => {
    report(true);
    next();
});

audio.addEventListener('error', () => {
    if (!currentTrack() || !audio.src) {
        return;
    }

    // Most likely the signed URL expired during a long pause: get a new one
    // once and carry on from the same spot.
    if (!retried) {
        retried = true;
        load(audio.currentTime || state.currentTime);
        return;
    }

    set({ playing: false, loading: false, error: "This song can't be played right now." });
});
