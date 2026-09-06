<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\NumberReset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The one row of workspace settings, and the way everything else reaches it.
 *
 * **Singleton by convention, not by constraint.** The tenant seeder creates the row when a
 * workspace is provisioned, and {@see current()} is the only way anything reads it — so
 * nothing has to handle its absence, and a workspace provisioned before this table existed
 * still gets sensible answers rather than a null dereference on the orders screen.
 *
 * Money settings only, deliberately. The company's address, its e-invoice identity and its
 * logo belong to the same row eventually, but they belong to phase 7's screen and adding
 * the columns now would be guessing at their shape.
 *
 * @property int $id
 * @property string $base_currency
 * @property list<string> $currencies
 * @property string $tax_rate
 * @property string $tax_label
 * @property string $purchase_order_prefix
 * @property string $purchase_return_prefix
 * @property string $sales_order_prefix
 * @property string $sales_return_prefix
 * @property NumberReset $number_reset
 * @property int $financial_year_start_month
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class BusinessSetting extends Model
{
    /**
     * Written only by the settings screen, through a validated request that names each
     * field — so nothing is mass-assignable from anywhere else.
     *
     * @var list<string>
     */
    protected $fillable = [
        'base_currency',
        'currencies',
        'tax_rate',
        'tax_label',
        'purchase_order_prefix',
        'purchase_return_prefix',
        'sales_order_prefix',
        'sales_return_prefix',
        'number_reset',
        'financial_year_start_month',
    ];

    /**
     * The workspace's settings, creating the row if a workspace predates the table.
     *
     * Not cached: this is one primary-key read against a one-row table on a connection
     * that is already open, and a cache would have to be invalidated on the settings
     * screen and would be wrong for exactly the request that changed it.
     */
    public static function current(): self
    {
        $settings = static::query()->firstOrCreate([], ['currencies' => self::defaultCurrencies()]);

        // Refreshed after a create, and that is not optional. The column defaults live in
        // the migration, so MySQL fills them in on insert — but the model Eloquent hands
        // back holds only the attributes it was given, which means a freshly provisioned
        // workspace would read an empty `base_currency` and a null `number_reset` from an
        // object whose row on disk is perfectly correct. The very first order raised in a
        // workspace is exactly when that would bite.
        return $settings->wasRecentlyCreated ? $settings->refresh() : $settings;
    }

    /**
     * What a new workspace may raise orders in.
     *
     * v1's list, minus the disagreement it shipped with: its `business_settings` table
     * defaulted `currency` to USD while its settings class defaulted to MYR, so a fresh
     * workspace's orders and its settings screen said different things.
     *
     * @return list<string>
     */
    public static function defaultCurrencies(): array
    {
        return ['MYR', 'SGD', 'USD', 'EUR', 'CNY'];
    }

    /**
     * Currencies an order may actually be raised in.
     *
     * The base currency is always allowed whatever the list says — a workspace that keeps
     * its books in a currency it cannot raise an order in is a state no screen should have
     * to render.
     *
     * @return list<string>
     */
    public function allowedCurrencies(): array
    {
        return array_values(array_unique([$this->base_currency, ...$this->currencies]));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'currencies' => 'array',
            // decimal:4, like every other rate and quantity in this schema — the tax rate
            // is a percentage, so 6% is stored as 6.0000 rather than 0.06.
            'tax_rate' => 'decimal:4',
            'number_reset' => NumberReset::class,
            'financial_year_start_month' => 'integer',
        ];
    }
}
