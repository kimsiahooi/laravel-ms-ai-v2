<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * A `search($term)` scope over the columns a model nominates as searchable.
 *
 * The columns live on the model rather than in the controller because they describe
 * the data, not the request: "a workspace is findable by its name or its address" is
 * true of a workspace wherever it is being listed from.
 *
 * A blank term is a no-op, so the scope always composes — an index query can call it
 * unconditionally and get the unfiltered list back when nobody has typed anything.
 *
 * The columns are an abstract method rather than v1's `$searchable` property. That is
 * not a style preference: PHP fatals when a class redeclares a trait property with a
 * different default, so the trait cannot declare one, which is why v1 reads
 * `$this->searchable ?? []` and suppresses the resulting undefined-property error. An
 * abstract method has neither problem and PHPStan checks every implementation.
 */
trait Searchable
{
    /**
     * Columns a LIKE runs against. Real columns only — this list is interpolated into
     * SQL as identifiers, exactly like the sort allow-list, so nothing derived from a
     * request may reach it.
     *
     * @return array<int, string>
     */
    abstract protected function searchableColumns(): array;

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        $columns = $this->searchableColumns();

        if ($term === '' || $columns === []) {
            return $query;
        }

        // Grouped, so the ORs cannot escape and swallow an outer WHERE — an archived
        // listing must stay archived no matter what was typed into the search box.
        return $query->where(function (Builder $group) use ($term, $columns): void {
            foreach ($columns as $column) {
                $group->orWhere($column, 'like', "%{$term}%");
            }
        });
    }
}
