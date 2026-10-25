import { readFileSync, writeFileSync } from 'node:fs';
import { defineConfig } from 'vite';
import legacy from '@vitejs/plugin-legacy';

// The oldest TV we support is a 2017 Samsung (Tizen 3.0, roughly Chrome 47):
// no ES modules, no async/await, no CSS grid or custom properties. The legacy
// plugin emits only the transpiled, polyfilled bundle, loaded without modules.
export default defineConfig({
    base: './',
    esbuild: { jsx: 'automatic', jsxImportSource: 'preact' },
    // Don't pick up the main site's Tailwind/PostCSS config from the parent folder.
    css: { postcss: { plugins: [] } },
    plugins: [
        {
            // A packaged TV app loads from local files, where Chromium refuses
            // scripts marked crossorigin. Nothing here needs CORS mode.
            // The legacy plugin adds the attribute after every HTML hook, so
            // edit the written file instead.
            name: 'strip-crossorigin',
            closeBundle() {
                const file = 'dist/index.html';
                writeFileSync(file, readFileSync(file, 'utf8').replace(/ crossorigin/g, ''));
            },
        },
        legacy({
            // 'ie >= 11' forces every bit of syntax down to ES5. Chrome 47 half
            // supports ES2015 (let/const and classes only in strict code), and
            // "all of it lowered" is easier to trust than "the parts it has".
            targets: ['chrome >= 47', 'ie >= 11'],
            renderModernChunks: false,
        }),
    ],
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        cssTarget: 'chrome47',
        assetsInlineLimit: 0,
    },
});
