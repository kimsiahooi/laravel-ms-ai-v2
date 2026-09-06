<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The workspace's own settings — one row, and only the money half of them.
 *
 * **Why this arrives with orders rather than in phase 7.** An order has to know what
 * currency it is in, what tax to charge, and what to call itself. All three are settings,
 * and settings were scheduled *after* the orders that need them. Building the orders first
 * would mean a hard-coded currency and a raw `#12` in four modules and then retrofitting
 * all four — so the money half comes forward and the rest (company address, e-invoice
 * identity, logo) stays in phase 7 with the screen that owns it.
 *
 * **One row, not a key-value table.** Every setting here is read on nearly every order
 * screen, they are read together, and each has a distinct type. A `key`/`value` table
 * would turn that into a dozen string casts and lose the schema's ability to say that a
 * tax rate is a decimal. The row is created by the tenant seeder, so no screen has to
 * handle its absence.
 *
 * `base_currency` is what the books are kept in. An order may be raised in another, and
 * carries its own rate — see `purchase_orders.exchange_rate`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_settings', function (Blueprint $table): void {
            $table->id();

            // ISO 4217, three letters. char rather than string: they are all exactly
            // three and the column is compared on every order.
            $table->char('base_currency', 3)->default('MYR');
            // Which currencies an order may be raised in, as a JSON list. A table would
            // be three joins to answer a question whose answer is five short strings,
            // and nothing else ever references a currency by id.
            $table->json('currencies');

            // The default a new order snapshots. Not applied from here at posting time:
            // an order keeps the rate it was raised under, or last year's invoices would
            // change the day somebody edits this field.
            $table->decimal('tax_rate', 8, 4)->default(0);
            // What to call it on a document — "SST", "VAT", "GST". A label, not a rate,
            // and stored rather than translated: it is a legal term on an invoice and
            // does not change with the reader's language.
            $table->string('tax_label', 20)->default('SST');

            // Document number prefixes. One per type rather than one shared, because
            // "PO-2026-0001" and "SO-2026-0001" are different sequences to a person.
            $table->string('purchase_order_prefix', 10)->default('PO');
            $table->string('purchase_return_prefix', 10)->default('PR');
            $table->string('sales_order_prefix', 10)->default('SO');
            $table->string('sales_return_prefix', 10)->default('SR');

            // 'yearly' restarts the count each financial year and puts the year in the
            // number; 'never' counts on forever. See DocumentNumberGenerator.
            $table->string('number_reset', 10)->default('yearly');
            // 1-12. Malaysia's tax year is the calendar year, but a workspace may close
            // its books in another month and its numbering should follow.
            $table->unsignedTinyInteger('financial_year_start_month')->default(1);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_settings');
    }
};
