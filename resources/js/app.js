import "./bootstrap";

/**
 * GrinPlayer — persistent audio singleton.
 * Lives outside the DOM so it survives Livewire wire:navigate page swaps.
 * Alpine's miniPlayer() binds to this object; on each re-init it re-attaches
 * event listeners without interrupting playback.
 */
if (!window.GrinPlayer) {
    window.GrinPlayer = {
        audio: new Audio(),
        track: null, // current track object
        queue: [], // array of track IDs (for dispatch)
        queueTracks: [], // array of full track objects (for display)
        queueIndex: -1,
        shuffleEnabled: false,
        repeatMode: "off",
        shuffleHistory: [],
        shufflePool: [],
        volume: 80,
        muted: false,
    };
    window.GrinPlayer.audio.preload = "metadata";
}

const PLAYER_STORAGE_KEY = "grinmuzik-player-state";

const readPersistedPlayerState = () => {
    try {
        const rawState = window.sessionStorage.getItem(PLAYER_STORAGE_KEY);

        if (!rawState) {
            return null;
        }

        const parsedState = JSON.parse(rawState);

        return parsedState && typeof parsedState === "object"
            ? parsedState
            : null;
    } catch (_) {
        return null;
    }
};

const persistPlayerState = () => {
    try {
        const player = window.GrinPlayer;

        if (!player) {
            return;
        }

        const hasTrack = Boolean(player.track?.audio_url);
        const hasQueue = Array.isArray(player.queue) && player.queue.length > 0;

        if (!hasTrack && !hasQueue) {
            window.sessionStorage.removeItem(PLAYER_STORAGE_KEY);
            return;
        }

        window.sessionStorage.setItem(
            PLAYER_STORAGE_KEY,
            JSON.stringify({
                track: player.track,
                queue: Array.isArray(player.queue) ? player.queue : [],
                queueTracks: Array.isArray(player.queueTracks)
                    ? player.queueTracks
                    : [],
                queueIndex:
                    typeof player.queueIndex === "number" ? player.queueIndex : -1,
                shuffleEnabled: Boolean(player.shuffleEnabled),
                repeatMode: player.repeatMode || "off",
                shuffleHistory: Array.isArray(player.shuffleHistory)
                    ? player.shuffleHistory
                    : [],
                shufflePool: Array.isArray(player.shufflePool)
                    ? player.shufflePool
                    : [],
                volume: typeof player.volume === "number" ? player.volume : 80,
                muted: Boolean(player.muted),
                currentTime: Number.isFinite(player.audio.currentTime)
                    ? player.audio.currentTime
                    : 0,
                playing:
                    Boolean(player.audio.src) &&
                    !player.audio.paused &&
                    !player.audio.ended,
            }),
        );
    } catch (_) {
    }
};

const hydratePlayerState = () => {
    const persistedState = readPersistedPlayerState();

    if (!persistedState) {
        return;
    }

    const player = window.GrinPlayer;

    player.track = persistedState.track ?? null;
    player.queue = Array.isArray(persistedState.queue) ? persistedState.queue : [];
    player.queueTracks = Array.isArray(persistedState.queueTracks)
        ? persistedState.queueTracks
        : [];
    player.queueIndex =
        typeof persistedState.queueIndex === "number"
            ? persistedState.queueIndex
            : -1;
    player.shuffleEnabled = Boolean(persistedState.shuffleEnabled);
    player.repeatMode = persistedState.repeatMode || "off";
    player.shuffleHistory = Array.isArray(persistedState.shuffleHistory)
        ? persistedState.shuffleHistory
        : [];
    player.shufflePool = Array.isArray(persistedState.shufflePool)
        ? persistedState.shufflePool
        : [];
    player.volume =
        typeof persistedState.volume === "number" ? persistedState.volume : 80;
    player.muted = Boolean(persistedState.muted);
    player.audio.volume = player.volume / 100;
    player.audio.muted = player.muted;

    if (!player.track?.audio_url) {
        return;
    }

    player.audio.src = player.track.audio_url;

    const restorePlaybackPosition = () => {
        if (
            Number.isFinite(persistedState.currentTime) &&
            persistedState.currentTime > 0
        ) {
            try {
                player.audio.currentTime = persistedState.currentTime;
            } catch (_) {
            }
        }
    };

    if (player.audio.readyState >= 1) {
        restorePlaybackPosition();
    } else {
        player.audio.addEventListener("loadedmetadata", restorePlaybackPosition, {
            once: true,
        });
    }

    if (persistedState.playing) {
        const resumePlayback = () => {
            player.audio.play().catch(() => {});
        };

        if (player.audio.readyState >= 1) {
            resumePlayback();
        } else {
            player.audio.addEventListener("loadedmetadata", resumePlayback, {
                once: true,
            });
        }
    }
};

window.GrinPlayerPersist = window.GrinPlayerPersist || {
    save: persistPlayerState,
    clear: () => window.sessionStorage.removeItem(PLAYER_STORAGE_KEY),
};

hydratePlayerState();

["timeupdate", "play", "pause", "loadedmetadata", "volumechange", "ended"].forEach(
    (eventName) => {
        window.GrinPlayer.audio.addEventListener(eventName, persistPlayerState);
    },
);

window.addEventListener("pagehide", persistPlayerState);
window.addEventListener("beforeunload", persistPlayerState);

const THEME_STORAGE_KEY = "grinmuzik-theme";
const THEME_DARK = "dark";
const THEME_LIGHT = "light";
const themeMediaQuery = window.matchMedia("(prefers-color-scheme: dark)");

const getStoredTheme = () => {
    const storedTheme = window.localStorage.getItem(THEME_STORAGE_KEY);

    return storedTheme === THEME_DARK || storedTheme === THEME_LIGHT
        ? storedTheme
        : null;
};

const resolveTheme = () =>
    getStoredTheme() ?? (themeMediaQuery.matches ? THEME_DARK : THEME_LIGHT);

const syncThemeToggleUi = (theme) => {
    document.querySelectorAll("[data-theme-toggle]").forEach((button) => {
        const label = button.querySelector("[data-theme-label]");
        const moonIcon = button.querySelector('[data-theme-icon="moon"]');
        const sunIcon = button.querySelector('[data-theme-icon="sun"]');
        const toggleToLight = theme === THEME_DARK;

        button.setAttribute(
            "aria-label",
            toggleToLight ? "Switch to light mode" : "Switch to dark mode",
        );

        if (label) {
            label.textContent = toggleToLight ? "Light mode" : "Dark mode";
        }

        moonIcon?.classList.toggle("hidden", toggleToLight);
        sunIcon?.classList.toggle("hidden", !toggleToLight);
    });
};

const applyTheme = (theme = resolveTheme()) => {
    const isDark = theme === THEME_DARK;

    document.documentElement.classList.toggle("dark", isDark);
    document.documentElement.dataset.theme = isDark ? THEME_DARK : THEME_LIGHT;
    document.documentElement.style.colorScheme = isDark
        ? THEME_DARK
        : THEME_LIGHT;

    syncThemeToggleUi(theme);

    window.dispatchEvent(
        new CustomEvent("grin-theme-changed", {
            detail: { theme },
        }),
    );

    return theme;
};

window.GrinTheme = window.GrinTheme || {
    apply: () => applyTheme(resolveTheme()),
    set: (theme) => {
        if (theme !== THEME_DARK && theme !== THEME_LIGHT) {
            return;
        }

        window.localStorage.setItem(THEME_STORAGE_KEY, theme);
        applyTheme(theme);
    },
    toggle: () => {
        const nextTheme = document.documentElement.classList.contains("dark")
            ? THEME_LIGHT
            : THEME_DARK;

        window.localStorage.setItem(THEME_STORAGE_KEY, nextTheme);
        applyTheme(nextTheme);
    },
    clear: () => {
        window.localStorage.removeItem(THEME_STORAGE_KEY);
        applyTheme(resolveTheme());
    },
};

themeMediaQuery.addEventListener?.("change", () => {
    if (!getStoredTheme()) {
        applyTheme(resolveTheme());
    }
});

const INSTALL_VISIT_THRESHOLD = 3;
const INSTALL_DISMISS_DAYS = 14;
const INSTALL_VISIT_STORAGE_KEY = "grinmuzik-install-visits";
const INSTALL_SESSION_STORAGE_KEY = "grinmuzik-install-session-counted";
const INSTALL_DISMISSED_AT_KEY = "grinmuzik-install-dismissed-at";
const INSTALL_INSTALLED_KEY = "grinmuzik-installed";

const installDismissMs = INSTALL_DISMISS_DAYS * 24 * 60 * 60 * 1000;

const isStandaloneApp = () =>
    window.matchMedia("(display-mode: standalone)").matches ||
    window.navigator.standalone === true;

const isIosDevice = () =>
    /iphone|ipad|ipod/i.test(window.navigator.userAgent);

const isSafariBrowser = () =>
    /^((?!chrome|android).)*safari/i.test(window.navigator.userAgent);

const canManuallyInstallOnIos = () =>
    isIosDevice() && isSafariBrowser() && !isStandaloneApp();

const getStoredVisitCount = () =>
    Number(window.localStorage.getItem(INSTALL_VISIT_STORAGE_KEY) || "0");

const getProjectedVisitCount = () =>
    window.sessionStorage.getItem(INSTALL_SESSION_STORAGE_KEY) === "1"
        ? getStoredVisitCount()
        : getStoredVisitCount() + 1;

const isInstallDismissed = () => {
    const dismissedAt = Number(
        window.localStorage.getItem(INSTALL_DISMISSED_AT_KEY) || "0",
    );

    return dismissedAt > 0 && Date.now() - dismissedAt < installDismissMs;
};

const publishInstallState = () => {
    window.dispatchEvent(
        new CustomEvent("grin-install-state", {
            detail: {
                deferredPrompt: window.GrinInstall?.deferredPrompt ?? null,
                visitCount: getStoredVisitCount(),
                installed:
                    window.localStorage.getItem(INSTALL_INSTALLED_KEY) === "1" ||
                    isStandaloneApp(),
                dismissed: isInstallDismissed(),
                canManuallyInstallOnIos: canManuallyInstallOnIos(),
            },
        }),
    );
};

const recordInstallVisit = () => {
    if (window.sessionStorage.getItem(INSTALL_SESSION_STORAGE_KEY) === "1") {
        return getStoredVisitCount();
    }

    const visits = getStoredVisitCount() + 1;
    window.localStorage.setItem(INSTALL_VISIT_STORAGE_KEY, String(visits));
    window.sessionStorage.setItem(INSTALL_SESSION_STORAGE_KEY, "1");

    return visits;
};

window.GrinInstall = window.GrinInstall || {
    deferredPrompt: null,
};

window.pwaInstallPrompt = () => ({
    open: false,
    canInstall: false,
    installAvailable: false,
    showIosInstructions: false,
    visitCount: 0,
    init() {
        const refresh = () => {
            const standalone = isStandaloneApp();
            const installed =
                standalone ||
                window.localStorage.getItem(INSTALL_INSTALLED_KEY) === "1";

            this.visitCount = getStoredVisitCount();
            this.installAvailable = Boolean(window.GrinInstall.deferredPrompt);
            this.showIosInstructions = canManuallyInstallOnIos();
            this.canInstall =
                !installed &&
                !isInstallDismissed() &&
                this.visitCount >= INSTALL_VISIT_THRESHOLD &&
                (this.installAvailable || this.showIosInstructions);

            this.open = this.canInstall;
        };

        this.refreshInstallPrompt = refresh;
        refresh();

        window.addEventListener("grin-install-state", this.refreshInstallPrompt);
        window
            .matchMedia("(display-mode: standalone)")
            .addEventListener?.("change", this.refreshInstallPrompt);
    },
    dismiss() {
        window.localStorage.setItem(
            INSTALL_DISMISSED_AT_KEY,
            String(Date.now()),
        );
        this.open = false;
        publishInstallState();
    },
    async install() {
        if (!window.GrinInstall.deferredPrompt) {
            this.dismiss();
            return;
        }

        const prompt = window.GrinInstall.deferredPrompt;
        window.GrinInstall.deferredPrompt = null;

        await prompt.prompt();
        const { outcome } = await prompt.userChoice;

        if (outcome === "accepted") {
            window.localStorage.setItem(INSTALL_INSTALLED_KEY, "1");
            window.localStorage.removeItem(INSTALL_DISMISSED_AT_KEY);
            this.open = false;
        } else {
            this.dismiss();
        }

        publishInstallState();
    },
});

window.addEventListener("beforeinstallprompt", (event) => {
    // Always capture the browser's prompt for later use (e.g. the menu's
    // manual "Install App" button) — it only fires once, so failing to
    // store it here means losing the ability to trigger install at all.
    // The visit-count gate (see pwaInstallPrompt's refresh()) only
    // controls whether the modal auto-opens, not whether we keep the
    // prompt around.
    event.preventDefault();
    window.GrinInstall.deferredPrompt = event;
    publishInstallState();
});

window.addEventListener("appinstalled", () => {
    window.localStorage.setItem(INSTALL_INSTALLED_KEY, "1");
    window.localStorage.removeItem(INSTALL_DISMISSED_AT_KEY);
    window.GrinInstall.deferredPrompt = null;
    publishInstallState();
});

document.addEventListener("DOMContentLoaded", () => {
    window.GrinTheme?.apply();
    recordInstallVisit();
    publishInstallState();

    if ("serviceWorker" in navigator && window.isSecureContext) {
        navigator.serviceWorker.register("/service-worker.js").catch(() => {});
    }
});

document.addEventListener("livewire:navigated", () => {
    window.GrinTheme?.apply();
});
