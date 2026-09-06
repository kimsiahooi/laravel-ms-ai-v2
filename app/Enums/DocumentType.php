<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The documents that carry a number of their own.
 *
 * Each case knows which setting holds its prefix, which is the whole reason this exists
 * rather than callers passing a string. v1 had no such mapping: `DocumentNumberGenerator`
 * took a type *and* a prefix as arguments, and exactly one caller ever passed them —
 * `purchase_order_prefix` and `invoice_prefix` were declared in settings, seeded, and
 * covered by a test, while no line of code ever read them. Purchase order numbers were
 * typed by hand.
 *
 * With the pair named here a document type cannot be numbered with another's prefix, and
 * adding one is a case rather than a convention somebody has to remember.
 *
 * The `value` is what lands in `document_sequences.type`, so it is data and cannot be
 * renamed once a workspace has allocated against it. The prefix, by contrast, is a
 * setting somebody may change tomorrow — which is exactly why the sequence is keyed on
 * this and not on that.
 */
enum DocumentType: string
{
    case PurchaseOrder = 'purchase_order';
    case PurchaseReturn = 'purchase_return';
    case SalesOrder = 'sales_order';
    case SalesReturn = 'sales_return';

    /** The `business_settings` column holding this type's prefix. */
    public function prefixSetting(): string
    {
        return $this->value.'_prefix';
    }
}
