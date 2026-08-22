<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            // The key is a supplied slug ("acme"), not a UUID: it is both the URL
            // segment (/acme/…) and the tenant database name suffix.
            $table->string('id')->primary();

            // Real columns must be listed in Tenant::getCustomColumns(); everything
            // else overflows into `data`.
            $table->string('name');
            $table->string('locale', 12)->default('en');

            $table->timestamps();
            $table->softDeletes();
            $table->json('data')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
