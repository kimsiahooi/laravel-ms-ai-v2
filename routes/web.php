<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central (landlord) routes
|--------------------------------------------------------------------------
|
| Only what lives outside a tenant workspace. Every application route is tenant-
| scoped and lives in routes/tenant.php, loaded by TenancyServiceProvider.
|
*/

Route::inertia('/', 'welcome')->name('home');
