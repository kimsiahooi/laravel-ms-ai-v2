import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        // Where the built SSR bundle listens, baked in at build time. Both values must
        // match `inertia.ssr.url` in config/inertia.php — see the note there.
        //
        // `host` is the one that matters in production: the default is `0.0.0.0`, so an
        // unauthenticated render endpoint would be reachable from the internet on a
        // public box. Laravel only ever posts to it over loopback, so binding it there
        // costs nothing and removes the exposure entirely.
        inertia({ ssr: { host: '127.0.0.1', port: 13714 } }),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
});
