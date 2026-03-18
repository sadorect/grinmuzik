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
        volume: 80,
        muted: false,
    };
    window.GrinPlayer.audio.preload = "metadata";
}

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
    recordInstallVisit();
    publishInstallState();

    if ("serviceWorker" in navigator && window.isSecureContext) {
        navigator.serviceWorker.register("/service-worker.js").catch(() => {});
    }
});
