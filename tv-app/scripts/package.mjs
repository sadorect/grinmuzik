// After `vite build`: lay out the two places the TV app goes.
//   build/tizen/        dist/ + config.xml + icon.png, ready for Tizen Studio to sign
//   ../public/tv-app/   the same app on the website, to try in a browser
import { cpSync, mkdirSync, rmSync } from 'node:fs';

const root = new URL('..', import.meta.url).pathname;
const targets = { tizen: root + 'build/tizen', site: root + '../public/tv-app' };
const which = process.argv[2];

if (which === 'tizen' || which === 'all') {
    rmSync(targets.tizen, { recursive: true, force: true });
    mkdirSync(targets.tizen, { recursive: true });
    cpSync(root + 'dist', targets.tizen, { recursive: true });
    cpSync(root + 'tizen/config.xml', targets.tizen + '/config.xml');
    cpSync(root + 'tizen/icon.png', targets.tizen + '/icon.png');
    console.log('✓ Tizen package folder: build/tizen');
}

if (which === 'site' || which === 'all') {
    rmSync(targets.site, { recursive: true, force: true });
    cpSync(root + 'dist', targets.site, { recursive: true });
    console.log('✓ Website copy: public/tv-app');
}
