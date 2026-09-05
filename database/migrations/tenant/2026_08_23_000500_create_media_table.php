<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-medialibrary's own table, and it lives in `database/migrations/tenant/`
 * rather than the central set on purpose.
 *
 * Media rows are `morphs('model')` — a model class and an id. Both halves only mean
 * anything inside one workspace's database, because ids restart at 1 in each of them. A
 * central media table would file product 7's photo under "product 7" for every
 * workspace at once.
 *
 * The consequence is that media ids also restart per workspace, so the files cannot
 * share a folder either — see App\Support\Media\TenantPathGenerator, which namespaces
 * every path under the workspace slug.
 *
 * The columns are the package's stub verbatim, deliberately: this is its table, and it
 * queries these names. Keeping it a copy is what makes the next upgrade a readable diff.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table): void {
            $table->id();

            $table->morphs('model');
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();

            $table->nullableTimestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
