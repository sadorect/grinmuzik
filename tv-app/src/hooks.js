import { useEffect, useRef, useState } from 'preact/hooks';
import { pushKeyHandler } from './keys';
import { getState, subscribe } from './player';

/**
 * Handle remote keys while `active`. Screens further down the stack stay
 * mounted (so Back returns to where you were) but stop listening.
 * Return true from the handler to mark a key as dealt with.
 */
export function useKeys(handler, active) {
    const ref = useRef(handler);
    const activeRef = useRef(active);
    ref.current = handler;
    activeRef.current = active !== false;

    useEffect(() => pushKeyHandler((key) => (activeRef.current ? ref.current(key) : false)), []);
}

/** Re-render whenever the player changes. */
export function usePlayer() {
    const [, setTick] = useState(0);

    useEffect(() => subscribe(() => setTick((tick) => tick + 1)), []);

    return getState();
}

export function formatTime(seconds) {
    const total = Math.max(0, Math.floor(seconds || 0));
    const minutes = Math.floor(total / 60);
    const rest = total % 60;

    return minutes + ':' + (rest < 10 ? '0' : '') + rest;
}
