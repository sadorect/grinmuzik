// Fails the build if anything newer than ES5 syntax or CSS the 2017 TV
// cannot read slipped into dist/. Babel should have lowered all of it; this
// is the tripwire for when it did not.
import { readdirSync, readFileSync } from 'node:fs';
import { join } from 'node:path';
import * as acorn from 'acorn';

const dir = new URL('../dist/assets/', import.meta.url).pathname;
let failed = false;

// The legacy build injects CSS from JS, so check the stylesheet at its source.
const cssFiles = [[new URL('../src/styles.css', import.meta.url).pathname, 'src/styles.css']];

const files = readdirSync(dir).map((file) => [join(dir, file), file]).concat(cssFiles);

for (const [path, file] of files) {
    const source = readFileSync(path, 'utf8');

    if (file.endsWith('.js')) {
        try {
            acorn.parse(source, { ecmaVersion: 5, sourceType: 'script' });
        } catch (error) {
            failed = true;
            const at = error.pos ?? 0;
            console.error(`✗ ${file}: not ES5 (${error.message}) near: ${source.slice(Math.max(0, at - 60), at + 60)}`);
        }
    }

    if (file.endsWith('.css')) {
        for (const [pattern, why] of [
            [/display:\s*grid/, 'CSS grid (Chrome 57+)'],
            [/var\(--/, 'CSS custom properties (Chrome 49+)'],
            [/\bgap:/, 'flex gap (Chrome 84+)'],
            [/\binset:/, 'inset shorthand (Chrome 87+)'],
            [/aspect-ratio/, 'aspect-ratio (Chrome 88+)'],
        ]) {
            if (pattern.test(source)) {
                failed = true;
                console.error(`✗ ${file}: uses ${why}`);
            }
        }
    }
}

if (failed) {
    process.exit(1);
}

console.log('✓ dist/ is ES5 and uses only CSS a 2017 Samsung TV understands');
