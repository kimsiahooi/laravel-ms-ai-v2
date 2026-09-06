<?php

declare(strict_types=1);

namespace App\Models;

use App\Actions\OpenStockTake;
use App\Actions\PostStockTake;
use App\Enums\StockTakeStatus;
use App\Models\Concerns\RecordsCreator;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A physical count of one warehouse — see the migration for why the ledger alone cannot
 * say this.
 *
 * The one mutable document in Phase 4, and only while it is a {@see StockTakeStatus::Draft}:
 * counts arrive one at a time as somebody walks the shelves, and the sheet is worth
 * nothing until it is posted. Every other check in this module reduces to
 * `status === Draft`, because both other states are terminal.
 *
 * No `#[Fillable]` and `$guarded` left at its default, deliberately, for the reason
 * {@see StockMovement} gives: a row that ends up moving stock is never mass-assigned
 * from a request. {@see OpenStockTake} and {@see PostStockTake} are the only things that
 * write one, and they name every column.
 *
 * The `creator` relation is spelled out here rather than taken from
 * {@see RecordsCreator}: the trait stamps whoever happens to be
 * authenticated at `creating` time, and a stock take has two actors written at two
 * different moments. Naming both columns at the point they are set keeps them symmetric
 * and keeps a console-opened count honestly authorless.
 *
 * @property int $id
 * @property int $warehouse_id
 * @property StockTakeStatus $status
 * @property int|null $created_by
 * @property int|null $posted_by
 * @property Carbon|null $posted_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Warehouse $warehouse
 * @property-read Collection<int, StockTakeItem> $items
 * @property-read User|null $creator
 * @property-read User|null $poster
 */
class StockTake extends Model
{
    use SoftDeletes;

    /**
     * What "find a stock take" means.
     *
     * A count has almost no words of its own — an id, a status code and whatever note
     * somebody left — so searching only this table would answer nothing. What a person
     * actually remembers is the place: "the Penang count", "the raw goods store".
     *
     * The site's name is searched here even though {@see Warehouse} deliberately leaves
     * it out of its own search. The reasoning does not carry across: a warehouse list is
     * many warehouses on one site and a site term there behaves like a hidden filter,
     * whereas a take names exactly one warehouse and the site is simply the longer way of
     * saying which one.
     *
     * The status is deliberately not searched: it is a code like `draft`, and matching
     * English against it would work in one locale and silently not in the other two. The
     * status filter is the control for that question.
     *
     * @param  Builder<StockTake>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $group) use ($like): void {
            $group
                ->where('notes', 'like', $like)
                ->orWhereHas(
                    'warehouse',
                    fn (Builder $warehouse) => $warehouse
                        ->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like)
                        ->orWhereHas('location', fn (Builder $site) => $site->where('name', 'like', $like)),
                );
        });
    }

    /**
     * Where this was counted.
     *
     * `withTrashed`, because the warehouse FK cascades but a *soft*-deleted warehouse
     * leaves its takes standing — and a count sheet that cannot name the building it
     * counted is a puzzle rather than a record.
     *
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class)->withTrashed();
    }

    /** @return HasMany<StockTakeItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(StockTakeItem::class);
    }

    /**
     * Who opened the sheet. Null for a count started by a console command or a seeder.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Who posted it — a different person from the creator often enough that v1's single
     * overwritten column lost the answer. Null while the take is still a draft.
     *
     * @return BelongsTo<User, $this>
     */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StockTakeStatus::class,
            'posted_at' => 'datetime',
        ];
    }
}
