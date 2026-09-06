<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessSetting;
use Illuminate\Database\Seeder;

/**
 * The workspace's one settings row.
 *
 * Idempotent, like every seeder here: {@see BusinessSetting::current()} creates the row
 * only when it is missing, so running this across existing tenants adds what they lack and
 * touches nothing they already chose.
 *
 * The model would create the row on first read anyway. Seeding it is what makes a
 * provisioned workspace complete rather than merely self-healing — the settings screen has
 * something to show before anybody has raised an order.
 */
final class BusinessSettingsSeeder extends Seeder
{
    public function run(): void
    {
        BusinessSetting::current();
    }
}
