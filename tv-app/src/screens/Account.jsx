import { useState } from 'preact/hooks';
import { request, clearSession } from '../api';
import { useKeys } from '../hooks';

export function Account({ nav, active, session }) {
    const [busy, setBusy] = useState(false);

    useKeys((key) => {
        if (key.name === 'enter' && !busy) {
            setBusy(true);
            // Sign out even if the network is down: the TV forgets the token
            // either way, and it can be revoked from grinmuzik.com/tv.
            request('DELETE', '/token')
                .catch(() => {})
                .then(() => {
                    clearSession();
                    nav.signedOut();
                });
            return true;
        }
        return false;
    }, active);

    const user = session && session.user;

    return (
        <div class="screen account">
            {user && user.avatar_url ? <img class="account__avatar" src={user.avatar_url} alt="" /> : null}
            <h1>{user ? user.name : 'Signed in'}</h1>
            <p>This TV is signed in to your GrinMuzik account.</p>
            <div class="button is-focused">{busy ? 'Signing out…' : 'Sign out of this TV'}</div>
            <p class="account__hint">You can also sign TVs out at grinmuzik.com/tv on your phone. Press Back to return.</p>
        </div>
    );
}
