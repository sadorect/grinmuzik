// Remote-control keys, normalised to names, and a stack of handlers so the
// screen (or dialog) on top gets keys first.
const CODES = {
    37: 'left',
    38: 'up',
    39: 'right',
    40: 'down',
    13: 'enter',
    10009: 'back', // Samsung remote Back / Return
    27: 'back', // Esc, for testing on a computer
    415: 'play',
    19: 'pause',
    10252: 'playpause',
    413: 'stop',
    417: 'ff',
    412: 'rw',
    10233: 'next',
    10232: 'prev',
};

// Media keys reach a Tizen app only once registered; arrows, OK and Back
// always do.
const TIZEN_MEDIA_KEYS = [
    'MediaPlay',
    'MediaPause',
    'MediaPlayPause',
    'MediaStop',
    'MediaFastForward',
    'MediaRewind',
    'MediaTrackNext',
    'MediaTrackPrevious',
];

const handlers = [];

export function pushKeyHandler(handler) {
    handlers.push(handler);

    return () => {
        const at = handlers.indexOf(handler);

        if (at !== -1) {
            handlers.splice(at, 1);
        }
    };
}

function dispatch(key) {
    for (let i = handlers.length - 1; i >= 0; i--) {
        if (handlers[i](key) === true) {
            return true;
        }
    }

    return false;
}

export function startKeys() {
    try {
        window.tizen.tvinputdevice.registerKeyBatch(TIZEN_MEDIA_KEYS);
    } catch (e) {
        // Not on a Samsung TV.
    }

    document.addEventListener('keydown', (event) => {
        let name = CODES[event.keyCode];
        let char = null;

        // A computer keyboard can type into search; a remote never sends these.
        if (!name && event.key && event.key.length === 1 && !event.ctrlKey && !event.metaKey) {
            name = event.key === ' ' ? 'space' : 'char';
            char = event.key;
        }

        if (!name && event.keyCode === 8) {
            name = 'backspace';
        }

        if (!name) {
            return;
        }

        if (dispatch({ name, char })) {
            event.preventDefault();
        }
    });
}

export function exitApp() {
    try {
        window.tizen.application.getCurrentApplication().exit();
    } catch (e) {
        // In a browser there is nothing to exit to.
    }
}

export function isTizen() {
    return typeof window.tizen !== 'undefined';
}
