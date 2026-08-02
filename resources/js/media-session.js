/**
 * MediaSession integration for GrinPlayer.
 *
 * This is what gives the player lockscreen art, notification-shade transport
 * controls, headset/steering-wheel buttons and Bluetooth metadata. On the web
 * it is a nicety; inside the Android TWA it is the difference between an app
 * people keep and one that appears to stop working the moment the screen
 * locks, because without a media session Android has no reason to treat the
 * tab as playing media and no surface on which to offer controls.
 *
 * It attaches to window.GrinPlayer — the audio singleton that survives
 * wire:navigate page swaps — rather than to the Alpine component, which is
 * torn down and re-initialised on every navigation.
 *
 * Next/previous cannot be implemented here: queue order, shuffle history and
 * repeat mode all live in the Alpine component. That component registers
 * handlers via registerMediaSessionHandlers() on init, and they are re-pointed
 * on each navigation. If none are registered the actions are unset, so Android
 * greys the buttons out instead of showing controls that silently do nothing.
 */

const SKIP_SECONDS = 10;

const supported = () =>
    typeof navigator !== "undefined" && "mediaSession" in navigator;

/**
 * Artwork for the lockscreen. Several sizes are declared because Android picks
 * per surface (notification chip vs. full lockscreen); the same URL for each is
 * fine, the OS scales it.
 */
const artworkFor = (track) => {
    if (!track?.cover) {
        return [];
    }

    return ["96x96", "192x192", "256x256", "384x384", "512x512"].map((sizes) => ({
        src: track.cover,
        sizes,
        type: "image/png",
    }));
};

export const updateMediaSessionMetadata = () => {
    if (!supported()) {
        return;
    }

    const track = window.GrinPlayer?.track;

    if (!track) {
        navigator.mediaSession.metadata = null;
        return;
    }

    navigator.mediaSession.metadata = new window.MediaMetadata({
        title: track.title || "Unknown track",
        artist: track.artist || "Unknown artist",
        album: "GrinMuzik",
        artwork: artworkFor(track),
    });
};

/**
 * Keep the OS's idea of the playhead in step with ours, so the scrubber in the
 * notification shade tracks real playback instead of sitting at zero.
 */
export const updateMediaSessionPosition = () => {
    if (!supported() || typeof navigator.mediaSession.setPositionState !== "function") {
        return;
    }

    const audio = window.GrinPlayer?.audio;

    if (!audio) {
        return;
    }

    const duration = Number(audio.duration);
    const position = Number(audio.currentTime);

    // Chrome throws if duration is NaN/Infinity (still loading, or a live
    // stream), or if position somehow exceeds it.
    if (!Number.isFinite(duration) || duration <= 0 || !Number.isFinite(position)) {
        return;
    }

    try {
        navigator.mediaSession.setPositionState({
            duration,
            position: Math.min(position, duration),
            playbackRate: audio.playbackRate || 1,
        });
    } catch (_) {
        // A malformed position state is not worth breaking playback over.
    }
};

const setPlaybackState = (state) => {
    if (supported()) {
        navigator.mediaSession.playbackState = state;
    }
};

/**
 * Called by the Alpine mini-player on every init(), since the previous
 * closures point at a component instance that navigation has discarded.
 */
export const registerMediaSessionHandlers = ({ next, previous } = {}) => {
    if (!supported()) {
        return;
    }

    const set = (action, handler) => {
        try {
            navigator.mediaSession.setActionHandler(action, handler);
        } catch (_) {
            // Older Chrome throws on unknown actions rather than ignoring them.
        }
    };

    set("nexttrack", typeof next === "function" ? () => next() : null);
    set("previoustrack", typeof previous === "function" ? () => previous() : null);
};

export const initMediaSession = () => {
    if (!supported()) {
        return;
    }

    const player = window.GrinPlayer;

    if (!player?.audio || player.mediaSessionReady) {
        return;
    }

    player.mediaSessionReady = true;

    const audio = player.audio;

    const set = (action, handler) => {
        try {
            navigator.mediaSession.setActionHandler(action, handler);
        } catch (_) {}
    };

    set("play", () => {
        audio.play().catch(() => {});
    });

    set("pause", () => audio.pause());

    set("stop", () => {
        audio.pause();
        audio.currentTime = 0;
    });

    set("seekbackward", (details) => {
        const offset = details?.seekOffset || SKIP_SECONDS;
        audio.currentTime = Math.max(0, audio.currentTime - offset);
    });

    set("seekforward", (details) => {
        const offset = details?.seekOffset || SKIP_SECONDS;
        audio.currentTime = Math.min(audio.duration || Infinity, audio.currentTime + offset);
    });

    set("seekto", (details) => {
        if (!Number.isFinite(details?.seekTime)) {
            return;
        }

        if (details.fastSeek && typeof audio.fastSeek === "function") {
            audio.fastSeek(details.seekTime);
            return;
        }

        audio.currentTime = details.seekTime;
    });

    audio.addEventListener("play", () => {
        setPlaybackState("playing");
        updateMediaSessionMetadata();
        updateMediaSessionPosition();
    });

    audio.addEventListener("pause", () => setPlaybackState("paused"));

    audio.addEventListener("ended", () => setPlaybackState("none"));

    audio.addEventListener("loadedmetadata", () => {
        updateMediaSessionMetadata();
        updateMediaSessionPosition();
    });

    // timeupdate fires ~4x/second; the position state is cheap but there is no
    // reason to recompute metadata that often, so only the position is synced.
    audio.addEventListener("timeupdate", updateMediaSessionPosition);
    audio.addEventListener("ratechange", updateMediaSessionPosition);

    updateMediaSessionMetadata();
};
