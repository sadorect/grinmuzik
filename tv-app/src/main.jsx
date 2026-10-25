import './styles.css';
import { render } from 'preact';
import { useEffect, useRef, useState } from 'preact/hooks';
import { getSession } from './api';
import { pushKeyHandler, startKeys } from './keys';
import { currentTrack, next, pause, play, previous, seekBy, stop, toggle } from './player';
import { MiniPlayer } from './components/MiniPlayer';
import { Home } from './screens/Home';
import { Detail } from './screens/Detail';
import { NowPlaying } from './screens/NowPlaying';
import { Search } from './screens/Search';
import { SignIn } from './screens/SignIn';
import { Account } from './screens/Account';
import { ExitDialog } from './screens/ExitDialog';

let nextId = 1;

/**
 * A stack of screens. Screens below the top stay mounted but hidden, so Back
 * returns to exactly where you were; only the top one listens to the remote.
 */
function App() {
    const [stack, setStack] = useState([{ id: nextId++, type: 'home' }]);
    const [session, setSession] = useState(getSession());
    const [sessionVersion, setSessionVersion] = useState(0);
    const [exiting, setExiting] = useState(false);
    const stackRef = useRef(stack);
    stackRef.current = stack;

    const nav = {
        open(screen) {
            const top = stackRef.current[stackRef.current.length - 1];

            // Opening the screen you're already on does nothing.
            if (top.type === screen.type && top.slug === screen.slug) {
                return;
            }

            setStack(stackRef.current.concat([Object.assign({ id: nextId++ }, screen)]));
        },
        back() {
            if (stackRef.current.length > 1) {
                setStack(stackRef.current.slice(0, -1));
            } else {
                setExiting(true);
            }
        },
        signedIn() {
            setSession(getSession());
            setSessionVersion(sessionVersion + 1);
            setStack(stackRef.current.slice(0, 1));
        },
        signedOut() {
            setSession(null);
            setSessionVersion(sessionVersion + 1);
            setStack(stackRef.current.slice(0, 1));
        },
    };

    // Lowest-priority handler: Back and the media keys, whatever screen is up.
    useEffect(
        () =>
            pushKeyHandler((key) => {
                switch (key.name) {
                    case 'back':
                        nav.back();
                        return true;
                    case 'playpause':
                        toggle();
                        return true;
                    case 'play':
                        play();
                        return true;
                    case 'pause':
                        pause();
                        return true;
                    case 'stop':
                        stop();
                        return true;
                    case 'ff':
                        seekBy(10);
                        return true;
                    case 'rw':
                        seekBy(-10);
                        return true;
                    case 'next':
                        if (currentTrack()) next();
                        return true;
                    case 'prev':
                        if (currentTrack()) previous();
                        return true;
                    default:
                        return false;
                }
            }),
        []
    );

    return (
        <div class="app">
            {stack.map((screen, i) => {
                const active = i === stack.length - 1 && !exiting;
                const props = { nav, active, session };

                return (
                    <div key={screen.id} class="layer" style={{ display: i === stack.length - 1 ? 'block' : 'none' }}>
                        {screen.type === 'home' ? <Home {...props} sessionVersion={sessionVersion} /> : null}
                        {screen.type === 'album' || screen.type === 'artist' || screen.type === 'playlist' ? (
                            <Detail {...props} type={screen.type} slug={screen.slug} title={screen.title} />
                        ) : null}
                        {screen.type === 'nowplaying' ? <NowPlaying {...props} /> : null}
                        {screen.type === 'search' ? <Search {...props} /> : null}
                        {screen.type === 'signin' ? <SignIn {...props} /> : null}
                        {screen.type === 'account' ? <Account {...props} /> : null}
                    </div>
                );
            })}
            {stack[stack.length - 1].type !== 'nowplaying' ? <MiniPlayer /> : null}
            {exiting ? <ExitDialog onCancel={() => setExiting(false)} /> : null}
        </div>
    );
}

startKeys();
render(<App />, document.getElementById('app'));
