<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DocumentType;
use App\Enums\NumberReset;
use App\Models\BusinessSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Allocates the next number for a document, once, to one caller.
 *
 * **Why a counter table and not `MAX(number) + 1`.** That query reads a column two
 * transactions can read at the same instant, and hands both of them the same answer — so
 * two purchase orders raised in the same second get the same number, and nothing notices
 * until somebody reconciles. A row can be locked; a maximum cannot.
 *
 * **`insertOrIgnore` then `lockForUpdate`, in that order.** The first call for a
 * (type, period) has no row to lock, and `firstOrCreate` would race itself into two. The
 * insert is made safe by the unique index on `document_sequences`; the loser of that race
 * is ignored rather than raised, and then both transactions queue on the same row.
 *
 * Three things v1 got wrong, and this does not:
 *
 * 1. **Only sales orders were ever numbered.** v1's generator took a type *and* a prefix
 *    as arguments and had exactly one caller. `purchase_order_prefix` and `invoice_prefix`
 *    sat in settings, seeded and tested, read by nothing. Here the prefix is looked up
 *    from {@see DocumentType}, so a caller cannot forget to pass one.
 * 2. **No unique index on the number itself.** Uniqueness lived only in a FormRequest, so
 *    an auto number could collide with one somebody typed, and v1 papered over it with a
 *    retry loop. Every document table here puts a unique index on its `number` column.
 * 3. **The financial-year label was a private method on a controller**, so only that
 *    controller could agree with it. It is {@see periodFor()} now.
 */
final class DocumentNumberGenerator
{
    /** `PO-2026-0001` — four digits is 9,999 documents of one type in one year. */
    private const PAD = 4;

    /**
     * The next number for this document type, formatted and reserved.
     *
     * **Must be called inside a transaction.** The lock it takes is released when that
     * transaction commits, and without one it is released immediately — which is the race
     * this class exists to prevent. It refuses rather than allocating a number it cannot
     * promise is unique.
     */
    public function next(DocumentType $type): string
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException(
                'DocumentNumberGenerator::next() must run inside a transaction, or the row lock it takes is released before the document is written.',
            );
        }

        $settings = BusinessSetting::current();
        $period = $this->periodFor($settings);

        DB::table('document_sequences')->insertOrIgnore([
            'type' => $type->value,
            'period' => $period,
            'next_number' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = DB::table('document_sequences')
            ->where('type', $type->value)
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        $number = $row === null ? 1 : (int) $row->next_number;

        DB::table('document_sequences')
            ->where('type', $type->value)
            ->where('period', $period)
            ->update(['next_number' => $number + 1, 'updated_at' => now()]);

        $prefix = (string) $settings->getAttribute($type->prefixSetting());

        return $this->format($prefix, $period, $number);
    }

    /**
     * The financial year this moment falls in, as a label — or `''` when numbering never
     * resets, which is what keeps one counter running forever.
     *
     * Labelled by the year the year *started* in: a book year running April 2025 to March
     * 2026 is "2025" throughout, so a document's number does not change meaning in
     * January. Businesses that label by the closing year exist; this picks one and says so
     * rather than leaving it to whoever reads the number.
     */
    public function periodFor(BusinessSetting $settings): string
    {
        if ($settings->number_reset === NumberReset::Never) {
            return '';
        }

        $now = Carbon::now();
        $startMonth = (int) $settings->financial_year_start_month;

        // Before the year has started, we are still inside the one that began last year.
        return (string) ($now->month < $startMonth ? $now->year - 1 : $now->year);
    }

    private function format(string $prefix, string $period, int $number): string
    {
        $padded = str_pad((string) $number, self::PAD, '0', STR_PAD_LEFT);

        return $period === ''
            ? sprintf('%s-%s', $prefix, $padded)
            : sprintf('%s-%s-%s', $prefix, $period, $padded);
    }
}
