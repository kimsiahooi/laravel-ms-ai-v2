<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\StockTakeStatus;
use App\Models\StockTake;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One count, as the list sends it and as the sheet's header reads it. **No lines.**
 *
 * v1 serialised every line into every list row and then hydrated up to five thousand
 * models per row to work out how many had been counted — a page of twenty takes could
 * pull a hundred thousand records to render three numbers. The three numbers travel on
 * their own here, counted by the database, and the lines are fetched only by the screen
 * that shows them ({@see StockTakeItemData}).
 *
 * **No `total_variance`, deliberately.** v1 summed the signed difference across every
 * line, which adds +10 kg of flour to −10 bolts and reports 0. The screen then said the
 * count found nothing while the post applied two adjustments. There is no unit a sum
 * across a warehouse could be in, so what travels instead is how many lines disagree —
 * a number that means the same thing whatever is on the shelves.
 *
 * **Values, not sentences**, like {@see StockMovementData}: the status is an enum case
 * the browser looks up in `lang/`, and the warehouse and its site arrive separately
 * because a warehouse called "Main store" exists at more than one site and the pair is
 * what identifies it.
 *
 * `created_by` and `posted_by` are the two people's *names*, not the id columns they are
 * named after. A list showing who is not offering to filter by them, and the id would
 * only be useful to a screen that had a directory to look it up in.
 */
#[TypeScript]
final class StockTakeData extends Data
{
    public function __construct(
        public int $id,
        public string $warehouse,
        public string $site,
        public StockTakeStatus $status,
        public int $line_count,
        public int $counted_count,
        /** Lines whose count differs from what the system expected. */
        public int $variance_count,
        public ?string $notes,
        /** Who opened the sheet; null for a count started by a console command. */
        public ?string $created_by,
        /** Who posted it; null while it is still a draft, and forever if cancelled. */
        public ?string $posted_by,
        public ?string $posted_at,
        public string $created_at,
    ) {}

    /**
     * The three counts are read off the query, not off the lines.
     *
     * They arrive as `withCount` aliases named exactly like the fields they fill —
     * `line_count`, `counted_count`, `variance_count` — so the query that produces them
     * and the row that shows them cannot drift apart under a rename. `getAttribute`
     * rather than property access because they are not columns: nothing about the model
     * promises them, and reading them through the accessor says so at the call site.
     *
     * A caller that forgets the aliases gets zeros. That is the trade for keeping the
     * signature to one argument, and it fails loudly on screen — a take listing "0 of 0
     * counted" next to a sheet full of lines is not a bug anybody can miss.
     */
    public static function fromStockTake(StockTake $take): self
    {
        return new self(
            id: $take->id,
            // The warehouse relation is loaded withTrashed, so both of these always
            // resolve — a count outlives the building it counted.
            warehouse: $take->warehouse->name,
            site: $take->warehouse->location->name,
            status: $take->status,
            line_count: (int) $take->getAttribute('line_count'),
            counted_count: (int) $take->getAttribute('counted_count'),
            variance_count: (int) $take->getAttribute('variance_count'),
            notes: $take->notes,
            created_by: $take->creator?->name,
            posted_by: $take->poster?->name,
            posted_at: $take->posted_at?->toIso8601String(),
            created_at: $take->created_at->toIso8601String(),
        );
    }
}
