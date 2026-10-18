/**
 * Casting for GrinPlayer — Chromecast (Google Cast) and AirPlay.
 *
 * AirPlay needs almost nothing from us: Safari routes the existing audio
 * element to the Apple TV / speaker itself, so all that is added is the
 * picker button and a "playing on AirPlay" flag.
 *
 * Chromecast is different. The TV fetches the track URL and plays it on its
 * own; the page only sends commands. Rather than teach every caller of
 * GrinPlayer.audio (the Alpine mini-player, play counting, session
 * persistence, the lockscreen MediaSession) about a second player, the audio
 * element is wrapped in a Proxy. With no cast session the proxy passes every
 * read, write and method call straight through to the real element. While a
 * session is live it answers the playback surface — src, play(), pause(),
 * currentTime, duration, paused, ended — from the TV, and replays the TV's
 * state changes to the same listeners as ordinary media events. Volume and
 * mute stay local on purpose: the TV keeps its own remote and level, and the
 * mini-player re-applies its slider on every navigation, which would
 * otherwise keep yanking the TV volume back.
 *
 * Uses Google's Default Media Receiver, so there is no receiver app of our
 * own to host or register.
 */

const CAST_SDK_SRC =
    "https://www.gstatic.com/cv/js/sender/v1/cast_sender.js?loadCastFramework=1";

const REMOTE_PROPS = new Set([
    "src",
    "currentSrc",
    "currentTime",
    "duration",
    "paused",
    "ended",
    "readyState",
    "play",
    "pause",
    "load",
    "fastSeek",
]);

const CONTENT_TYPES = {
    mp3: "audio/mpeg",
    m4a: "audio/mp4",
    mp4: "audio/mp4",
    aac: "audio/aac",
    wav: "audio/wav",
    ogg: "audio/ogg",
    oga: "audio/ogg",
    flac: "audio/flac",
    webm: "audio/webm",
};

const contentTypeFor = (url) => {
    try {
        const extension = new URL(url, window.location.href).pathname
            .split(".")
            .pop()
            .toLowerCase();

        return CONTENT_TYPES[extension] || "audio/mpeg";
    } catch (_) {
        return "audio/mpeg";
    }
};

// Signed URLs differ in their query string on every mint, so compare the
// object path when deciding whether the TV is already playing our track.
const samePath = (a, b) => {
    try {
        return (
            new URL(a, window.location.href).pathname ===
            new URL(b, window.location.href).pathname
        );
    } catch (_) {
        return false;
    }
};

export const createCastableAudio = (element) => {
    const state = {
        castAvailable: false,
        castConnected: false,
        airplayAvailable: false,
        airplayActive: false,
        deviceName: "",
    };

    // Playback state while the TV is in charge.
    const remote = {
        active: false,
        src: "",
        currentTime: 0,
        duration: NaN,
        paused: true,
        ended: false,
        pending: null,
        media: null,
    };

    let proxy = null;
    let castContext = null;
    let remotePlayer = null;
    let remoteController = null;
    let urlResolver = null;

    const publish = () => {
        window.dispatchEvent(
            new CustomEvent("grin-cast-state", { detail: { ...state } }),
        );
    };

    // ── Listener registry ───────────────────────────────────────────────
    // Every listener goes onto the real element through a wrapper that drops
    // local events while casting (the paused local element would otherwise
    // report "pause" over the TV's "play"), and is kept here so remote state
    // changes can be delivered to it too.
    const listeners = new Map();

    const entriesFor = (type) => {
        if (!listeners.has(type)) {
            listeners.set(type, []);
        }

        return listeners.get(type);
    };

    const forget = (type, entry) => {
        const entries = entriesFor(type);
        const index = entries.indexOf(entry);

        if (index !== -1) {
            entries.splice(index, 1);
        }
    };

    const addListener = (type, fn, options) => {
        if (typeof fn !== "function") {
            return;
        }

        const capture =
            typeof options === "boolean" ? options : Boolean(options?.capture);
        const once = typeof options === "object" && Boolean(options?.once);
        const entries = entriesFor(type);

        if (entries.some((entry) => entry.fn === fn && entry.capture === capture)) {
            return;
        }

        const entry = { fn, capture, once, wrapper: null };

        entry.wrapper = (event) => {
            if (once) {
                forget(type, entry);
            }

            if (!remote.active) {
                fn.call(proxy, event);
            }
        };

        entries.push(entry);
        element.addEventListener(type, entry.wrapper, options);
    };

    const removeListener = (type, fn, options) => {
        const capture =
            typeof options === "boolean" ? options : Boolean(options?.capture);
        const entry = entriesFor(type).find(
            (candidate) => candidate.fn === fn && candidate.capture === capture,
        );

        if (!entry) {
            return;
        }

        forget(type, entry);
        element.removeEventListener(type, entry.wrapper, options);
    };

    const emit = (type) => {
        const event = new Event(type);

        [...entriesFor(type)].forEach((entry) => {
            if (entry.once) {
                forget(type, entry);
                element.removeEventListener(type, entry.wrapper, {
                    capture: entry.capture,
                });
            }

            try {
                entry.fn.call(proxy, event);
            } catch (error) {
                console.error(error);
            }
        });
    };

    // ── Remote playback ─────────────────────────────────────────────────
    const session = () => castContext?.getCurrentSession() ?? null;

    const setPaused = (paused) => {
        if (remote.paused === paused) {
            return;
        }

        remote.paused = paused;
        emit(paused ? "pause" : "play");
    };

    const setCurrentTime = (seconds) => {
        if (!Number.isFinite(seconds)) {
            return;
        }

        remote.currentTime = seconds;
        emit("timeupdate");
    };

    const onFinished = () => {
        remote.media = null;
        remote.ended = true;

        if (Number.isFinite(remote.duration)) {
            setCurrentTime(remote.duration);
        }

        // Repeat-track is element.loop locally; the TV has no equivalent for
        // a single item, so replay it ourselves.
        if (element.loop && remote.src) {
            loadRemote({ url: remote.src, start: 0, autoplay: true }).catch(() => {});
            return;
        }

        setPaused(true);
        emit("ended");
    };

    const watchMedia = (media) => {
        remote.media = media;

        if (!media) {
            return;
        }

        media.addUpdateListener((isAlive) => {
            if (remote.media !== media || isAlive) {
                return;
            }

            if (media.idleReason === window.chrome.cast.media.IdleReason.FINISHED) {
                onFinished();
            } else {
                remote.media = null;
            }
        });
    };

    const loadRemote = ({ url, start = 0, autoplay = false }) => {
        const castSession = session();

        if (!castSession || !url) {
            return Promise.reject(new Error("No cast session"));
        }

        const { chrome } = window;
        const track = window.GrinPlayer?.track ?? null;
        const info = new chrome.cast.media.MediaInfo(url, contentTypeFor(url));
        const metadata = new chrome.cast.media.MusicTrackMediaMetadata();

        metadata.title = track?.title || "Unknown track";
        metadata.artist = track?.artist || "Unknown artist";
        metadata.albumName = "GrinMuzik";
        metadata.images = track?.cover ? [new chrome.cast.Image(track.cover)] : [];

        info.metadata = metadata;
        info.streamType = chrome.cast.media.StreamType.BUFFERED;

        if (Number(track?.duration) > 0) {
            info.duration = Number(track.duration);
        }

        const request = new chrome.cast.media.LoadRequest(info);
        request.currentTime = start;
        request.autoplay = autoplay;

        remote.src = url;
        remote.ended = false;
        remote.media = null;
        setCurrentTime(start);

        return castSession.loadMedia(request).then(() => {
            const media = castSession.getMediaSession();

            watchMedia(media);
            remote.duration =
                Number(media?.media?.duration) || Number(track?.duration) || NaN;
            emit("durationchange");
            emit("loadedmetadata");
            setPaused(!autoplay);
        });
    };

    // Several writes arrive back to back when a track is loaded (src, then
    // currentTime, then play()), so they are batched into one load request.
    const queueLoad = (url) => {
        const pending = { url, start: 0, autoplay: false };

        pending.promise = new Promise((resolve, reject) => {
            pending.resolve = resolve;
            pending.reject = reject;
        });
        pending.promise.catch(() => {});

        remote.pending = pending;
        remote.src = url;

        queueMicrotask(() => {
            if (remote.pending !== pending) {
                return;
            }

            remote.pending = null;
            loadRemote(pending).then(pending.resolve, (error) => {
                console.error("GrinCast: could not load track on the TV.", error);
                pending.reject(error);
            });
        });
    };

    const remotePlay = () => {
        if (remote.pending) {
            remote.pending.autoplay = true;
            return remote.pending.promise;
        }

        if (!remote.media) {
            // Finished or never loaded: start the item again on the TV.
            return loadRemote({
                url: remote.src,
                start: remote.ended ? 0 : remote.currentTime,
                autoplay: true,
            });
        }

        if (remotePlayer.isPaused) {
            remoteController.playOrPause();
        }

        setPaused(false);

        return Promise.resolve();
    };

    const remotePause = () => {
        if (remote.pending) {
            remote.pending.autoplay = false;
            return;
        }

        if (remote.media && !remotePlayer.isPaused) {
            remoteController.playOrPause();
        }

        setPaused(true);
    };

    const remoteSeek = (seconds) => {
        if (!Number.isFinite(seconds)) {
            return;
        }

        if (remote.pending) {
            remote.pending.start = seconds;
            return;
        }

        if (remote.media) {
            remotePlayer.currentTime = seconds;
            remoteController.seek();
        }

        remote.ended = false;
        setCurrentTime(seconds);
    };

    const remoteGet = (prop) => {
        switch (prop) {
            case "src":
            case "currentSrc":
                return remote.src;
            case "currentTime":
                return remote.currentTime;
            case "duration":
                return remote.duration;
            case "paused":
                return remote.paused;
            case "ended":
                return remote.ended;
            case "readyState":
                return Number.isFinite(remote.duration) ? 4 : 0;
            case "play":
                return remotePlay;
            case "pause":
                return remotePause;
            case "fastSeek":
                return remoteSeek;
            case "load":
                return () => {};
            default:
                return undefined;
        }
    };

    const remoteSet = (prop, value) => {
        if (prop === "src") {
            queueLoad(String(value || ""));
            return true;
        }

        if (prop === "currentTime") {
            remoteSeek(Number(value));
            return true;
        }

        return false;
    };

    const freshUrlFor = async (track) => {
        if (typeof urlResolver === "function" && track?.id) {
            try {
                const url = await urlResolver(track.id);

                if (url) {
                    return url;
                }
            } catch (_) {
                // Fall back to the URL we already hold.
            }
        }

        return track?.audio_url || "";
    };

    const beginRemote = async ({ resumed }) => {
        const castSession = session();

        if (!castSession || remote.active) {
            return;
        }

        const wasPlaying = Boolean(element.src) && !element.paused && !element.ended;
        const position = Number.isFinite(element.currentTime) ? element.currentTime : 0;
        const track = window.GrinPlayer?.track ?? null;

        state.castConnected = true;
        state.deviceName = castSession.getCastDevice()?.friendlyName || "TV";
        publish();

        // Flip first, so the local pause below is not reported to listeners.
        remote.active = true;
        remote.paused = !wasPlaying;
        remote.currentTime = position;
        remote.duration = Number.isFinite(element.duration) ? element.duration : NaN;
        remote.src = element.src;
        element.pause();

        const existing = castSession.getMediaSession();

        // Rejoining a session that is already playing our track (a full page
        // load while casting): take over its state instead of restarting it.
        if (
            resumed &&
            existing &&
            (!track?.audio_url || samePath(existing.media?.contentId, track.audio_url))
        ) {
            watchMedia(existing);
            remote.src = existing.media?.contentId || remote.src;
            remote.duration = Number(existing.media?.duration) || remote.duration;
            emit("loadedmetadata");
            setCurrentTime(existing.getEstimatedTime());
            setPaused(
                existing.playerState !== window.chrome.cast.media.PlayerState.PLAYING &&
                    existing.playerState !== window.chrome.cast.media.PlayerState.BUFFERING,
            );
            return;
        }

        if (!track?.audio_url) {
            return;
        }

        // The URL in hand may have been minted long ago (a paused track, a
        // restored session); the TV needs one that is good for the whole song.
        const url = await freshUrlFor(track);

        if (!remote.active) {
            return;
        }

        loadRemote({ url, start: position, autoplay: wasPlaying }).catch((error) => {
            console.error("GrinCast: could not start the track on the TV.", error);
            setPaused(true);
        });
    };

    const endRemote = () => {
        if (!remote.active) {
            return;
        }

        const position = remote.currentTime;
        const src = remote.src;

        remote.active = false;
        remote.pending = null;
        remote.media = null;
        state.castConnected = false;
        state.deviceName = "";
        publish();

        // Carry on from where the TV stopped, paused: stopping the cast should
        // not suddenly start the song out of the phone's speaker.
        if (src && src !== element.src) {
            element.src = src;
        }

        try {
            element.currentTime = position;
        } catch (_) {
            // Not seekable yet; the track simply starts from the top.
        }

        emit("pause");
        emit("timeupdate");
    };

    // ── Google Cast SDK ─────────────────────────────────────────────────
    const setUpCast = () => {
        const { cast, chrome } = window;

        castContext = cast.framework.CastContext.getInstance();
        castContext.setOptions({
            receiverApplicationId: chrome.cast.media.DEFAULT_MEDIA_RECEIVER_APP_ID,
            autoJoinPolicy: chrome.cast.AutoJoinPolicy.ORIGIN_SCOPED,
        });

        remotePlayer = new cast.framework.RemotePlayer();
        remoteController = new cast.framework.RemotePlayerController(remotePlayer);

        const { RemotePlayerEventType, CastContextEventType, CastState, SessionState } =
            cast.framework;

        remoteController.addEventListener(
            RemotePlayerEventType.CURRENT_TIME_CHANGED,
            () => {
                if (remote.active && remote.media && !remote.pending) {
                    setCurrentTime(remotePlayer.currentTime);
                }
            },
        );

        remoteController.addEventListener(RemotePlayerEventType.IS_PAUSED_CHANGED, () => {
            if (remote.active && remote.media && !remote.pending) {
                setPaused(remotePlayer.isPaused);
            }
        });

        remoteController.addEventListener(RemotePlayerEventType.DURATION_CHANGED, () => {
            if (remote.active && remotePlayer.duration > 0) {
                remote.duration = remotePlayer.duration;
                emit("durationchange");
            }
        });

        castContext.addEventListener(CastContextEventType.CAST_STATE_CHANGED, (event) => {
            state.castAvailable = event.castState !== CastState.NO_DEVICES_AVAILABLE;
            publish();
        });

        castContext.addEventListener(
            CastContextEventType.SESSION_STATE_CHANGED,
            (event) => {
                if (event.sessionState === SessionState.SESSION_STARTED) {
                    beginRemote({ resumed: false });
                } else if (event.sessionState === SessionState.SESSION_RESUMED) {
                    beginRemote({ resumed: true });
                } else if (event.sessionState === SessionState.SESSION_ENDED) {
                    endRemote();
                }
            },
        );

        state.castAvailable =
            castContext.getCastState() !== CastState.NO_DEVICES_AVAILABLE;
        publish();
    };

    const loadCastSdk = () => {
        const userAgent = window.navigator.userAgent;

        // The web sender only works in Chromium browsers; elsewhere skip the
        // download entirely.
        if (
            !window.isSecureContext ||
            !window.chrome ||
            /iphone|ipad|ipod/i.test(userAgent)
        ) {
            return;
        }

        window.__onGCastApiAvailable = (isAvailable) => {
            if (isAvailable && window.cast?.framework) {
                setUpCast();
            }
        };

        const script = document.createElement("script");
        script.src = CAST_SDK_SRC;
        script.async = true;
        document.head.appendChild(script);
    };

    // ── AirPlay (Safari) ────────────────────────────────────────────────
    const setUpAirPlay = () => {
        if (!window.WebKitPlaybackTargetAvailabilityEvent) {
            return;
        }

        element.setAttribute("x-webkit-airplay", "allow");

        element.addEventListener("webkitplaybacktargetavailabilitychanged", (event) => {
            state.airplayAvailable = event.availability === "available";
            publish();
        });

        element.addEventListener("webkitcurrentplaybacktargetiswirelesschanged", () => {
            state.airplayActive = Boolean(element.webkitCurrentPlaybackTargetIsWireless);
            publish();
        });
    };

    proxy = new Proxy(element, {
        get(target, prop) {
            if (prop === "addEventListener") {
                return addListener;
            }

            if (prop === "removeEventListener") {
                return removeListener;
            }

            if (remote.active && REMOTE_PROPS.has(prop)) {
                return remoteGet(prop);
            }

            const value = target[prop];

            return typeof value === "function" ? value.bind(target) : value;
        },

        set(target, prop, value) {
            if (remote.active && remoteSet(prop, value)) {
                return true;
            }

            target[prop] = value;

            return true;
        },
    });

    window.GrinCast = {
        get state() {
            return { ...state };
        },

        // Called by the mini-player with a function that mints a fresh signed
        // audio URL for a track id.
        setUrlResolver(resolver) {
            urlResolver = resolver;
        },

        // One button for both: Safari offers AirPlay, Chrome offers Cast.
        // While connected the Cast dialog doubles as "stop casting".
        open() {
            if (state.airplayAvailable && element.webkitShowPlaybackTargetPicker) {
                element.webkitShowPlaybackTargetPicker();
                return;
            }

            if (castContext) {
                castContext.requestSession().catch(() => {
                    // Dismissed the device picker.
                });
            }
        },
    };

    setUpAirPlay();

    if (document.readyState === "complete") {
        loadCastSdk();
    } else {
        window.addEventListener("load", loadCastSdk, { once: true });
    }

    return proxy;
};
