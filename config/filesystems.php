<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim((string) env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Tenant uploads — product photos today, the business logo later.
         *
         * Private and NOT symlinked: `public` puts a file in the docroot, where the URL
         * is the only thing standing between one workspace's photos and anyone who
         * guesses it. Everything here is read back through the auth-gated media route
         * instead, one file at a time.
         *
         * Central and un-suffixed, deliberately: this disk is absent from
         * `tenancy.filesystem.disks`, so stancl never repoints its root per workspace
         * and every tenant shares one tree. What keeps them apart is the path —
         * `assets/{slug}/…`, written by App\Support\Media\TenantPathGenerator, which
         * refuses to generate a path outside a workspace at all. That is also the single
         * folder DeleteTenantAssets removes when a workspace is torn down; a suffixed
         * disk would scatter the same files across per-tenant storage roots.
         *
         * `serve` is off (the default). The `local` disk above sets it, which registers
         * Laravel's own `/storage/{path}` route; a disk holding one workspace's files
         * must not answer on an unauthenticated URL.
         */
        'assets' => [
            'driver' => 'local',
            'root' => storage_path('assets'),
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
