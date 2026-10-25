import { useEffect, useState } from 'preact/hooks';
import qrcode from 'qrcode-generator';
import { request, saveSession } from '../api';
import { isTizen } from '../keys';

function qrSvg(text) {
    const qr = qrcode(0, 'M');
    qr.addData(text);
    qr.make();

    return qr.createSvgTag({ cellSize: 8, margin: 2, scalable: true });
}

/**
 * Shows a code to approve at grinmuzik.com/tv on a phone, and waits.
 * A fresh code is fetched automatically if one expires.
 */
export function SignIn({ nav }) {
    const [code, setCode] = useState(null);
    const [status, setStatus] = useState('Getting a code…');

    useEffect(() => {
        let stopped = false;
        let timer = null;
        let interval = 5;
        let deviceCode = null;

        function start() {
            setCode(null);
            setStatus('Getting a code…');
            request('POST', '/device-code', { device_name: isTizen() ? 'Samsung TV' : 'TV browser' })
                .then((result) => {
                    if (stopped) return;
                    if (result.status !== 200) throw new Error('no code');

                    deviceCode = result.data.device_code;
                    interval = result.data.interval || 5;
                    setCode(result.data);
                    setStatus('Waiting for you to approve on your phone…');
                    schedule();
                })
                .catch(() => {
                    if (stopped) return;
                    setStatus("Can't reach GrinMuzik. Trying again…");
                    timer = setTimeout(start, 10000);
                });
        }

        function schedule() {
            timer = setTimeout(poll, interval * 1000);
        }

        function poll() {
            request('POST', '/token', { device_code: deviceCode })
                .then((result) => {
                    if (stopped) return;

                    if (result.status === 200) {
                        saveSession(result.data.access_token, result.data.user);
                        nav.signedIn();
                        return;
                    }

                    const error = result.data && result.data.error;

                    if (error === 'authorization_pending') schedule();
                    else if (error === 'slow_down') {
                        interval += 5;
                        schedule();
                    } else if (error === 'access_denied') {
                        setStatus('Sign-in was declined on the phone. Getting a new code…');
                        timer = setTimeout(start, 4000);
                    } else {
                        // Expired or already used: start over with a new code.
                        start();
                    }
                })
                .catch(() => {
                    if (!stopped) schedule();
                });
        }

        start();

        return () => {
            stopped = true;
            clearTimeout(timer);
        };
    }, []);

    return (
        <div class="screen signin">
            <div class="signin__steps">
                <h1>Sign in to GrinMuzik</h1>
                <p class="signin__lead">Signing in brings your liked songs, playlists and history to the TV. You can listen without it.</p>
                <ol class="signin__list">
                    <li>
                        On your phone, go to <strong>{code ? code.verification_uri.replace(/^https?:\/\//, '') : 'grinmuzik.com/tv'}</strong>, or scan the code.
                    </li>
                    <li>Sign in there if you need to.</li>
                    <li>Enter this code and press Approve:</li>
                </ol>
                <div class="signin__code">{code ? code.user_code : '····-····'}</div>
                <p class="signin__status">{status}</p>
                <p class="signin__back">Press Back to cancel.</p>
            </div>
            <div class="signin__qr" dangerouslySetInnerHTML={{ __html: code ? qrSvg(code.verification_uri_complete) : '' }} />
        </div>
    );
}
