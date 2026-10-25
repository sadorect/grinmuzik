import { useState } from 'preact/hooks';
import { useKeys } from '../hooks';
import { exitApp } from '../keys';

export function ExitDialog({ onCancel }) {
    const [choice, setChoice] = useState(1);

    useKeys((key) => {
        if (key.name === 'left') setChoice(0);
        else if (key.name === 'right') setChoice(1);
        else if (key.name === 'back') onCancel();
        else if (key.name === 'enter') {
            if (choice === 0) exitApp();
            else onCancel();
        } else return false;
        return true;
    });

    return (
        <div class="dialog">
            <div class="dialog__box">
                <h1>Exit GrinMuzik?</h1>
                <p>Music stops when you exit.</p>
                <div class="dialog__buttons">
                    <div class={'button' + (choice === 0 ? ' is-focused' : '')}>Exit</div>
                    <div class={'button' + (choice === 1 ? ' is-focused' : '')}>Keep listening</div>
                </div>
            </div>
        </div>
    );
}
