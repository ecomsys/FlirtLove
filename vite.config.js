// 1. Vite is Laravel 12's default asset bundler — defineConfig + the
//    laravel-vite-plugin wire your Blade @vite directive to a dev /
//    build pipeline.
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

// 2. Tailwind v4 ships as a first-class Vite plugin — no PostCSS config
//    file needed, no tailwind.config.js required.
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        // 3. Register the CSS + JS entry points Vite watches. `refresh: true`
        //    triggers a Livewire-aware page-reload on Blade / route changes.
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }),
        // 4. Activate Tailwind's compiler — utilities are scanned out of
        //    your views at build time.
        tailwindcss(),
    ],
    resolve: {
        alias: {
            'prosemirror-state': path.resolve(__dirname, 'node_modules/prosemirror-state'),
            'prosemirror-view': path.resolve(__dirname, 'node_modules/prosemirror-view'),
            'prosemirror-transform': path.resolve(__dirname, 'node_modules/prosemirror-transform'),
            'prosemirror-model': path.resolve(__dirname, 'node_modules/prosemirror-model'),
            'prosemirror-commands': path.resolve(__dirname, 'node_modules/prosemirror-commands'),
            'prosemirror-keymap': path.resolve(__dirname, 'node_modules/prosemirror-keymap'),
        }
    }
});
