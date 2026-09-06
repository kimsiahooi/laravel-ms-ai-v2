<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render each initial request made to your application's pages
    | so that server rendered HTML is delivered for the user's browser.
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    'ssr' => [
        'enabled' => true,

        /*
         * Where Laravel POSTs a page to have it rendered.
         *
         * The port is pinned rather than left to the default, and it has to match
         * `inertia({ ssr: { port } })` in vite.config.ts — that option is what the built
         * bundle binds, and it is baked in at build time. If the two disagree, Laravel
         * posts into a closed port, falls back to client rendering, and **logs nothing**;
         * see docs/CODING-STANDARDS.md.
         *
         * It is deliberate because 13714 is every Inertia app's default, and this server
         * is expected to host a second Laravel app on another subdomain. Two SSR
         * processes cannot hold one loopback port: the second to start fails to bind, and
         * the first keeps answering — so one app would silently render the other app's
         * pages. The next app on this box takes 13715.
         */
        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),

        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | These options configure how Inertia discovers page components on the
    | filesystem. The paths and extensions are used to locate components
    | when rendering responses and during testing assertions.
    |
    */

    'pages' => [

        'paths' => [
            resource_path('js/pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
