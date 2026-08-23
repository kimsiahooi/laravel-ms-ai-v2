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
     * Characters people put inside a phone number that carry no information.
     *
     * Stripped from both the stored value and the typed one, so `+60 3 1234 5678` and
     * `0312345678` are the same number as far as searching is concerned.
     *
     * @var list<literal-string>
     */
    private const NOISE = [' ', '-', '(', ')', '+', '.'];

    /**
     * Columns a LIKE runs against. Real columns only — this list is interpolated into
     * SQL as identifiers, exactly like the sort allow-list, so nothing derived from a
     * request may reach it.
     *
     * @return array<int, string>
     */
    abstract protected function searchableColumns(): array;

    /**
     * Columns holding a phone number, matched on digits alone.
     *
     * Separate from {@see searchableColumns()} because a plain LIKE on a phone column
     * barely works: the value is stored the way somebody typed it — `+60 3 1234 5678`
     * — and the person searching types `0312345678`. Neither is a substring of the
     * other, so the column looks searchable and finds nothing.
     *
     * Stripping the noise from both sides fixes that, and fragment matching, which is
     * a nuisance elsewhere, is the point here: the last four digits are how people
     * recognise a number.
     *
     * Empty by default. A model with no phone column overrides nothing.
     *
     * `literal-string`, and not merely for tidiness: it is what lets PHPStan prove the
     * raw SQL below is built from source code alone. A column name that came from a
     * request would fail to type-check rather than reaching the database.
     *
     * @return list<literal-string>
     */
    protected function searchableDigitColumns(): array
    {
        return [];
    }

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
        // Only worth touching the phone columns when something was typed that could be
        // part of a number. A search for "acme" should not scan them.
        $digits = preg_replace('/\D+/', '', $term) ?? '';
        $digitColumns = $digits === '' ? [] : $this->searchableDigitColumns();

        // Grouped, so the ORs cannot escape and swallow an outer WHERE — an archived
        // listing must stay archived no matter what was typed into the search box.
        return $query->where(function (Builder $group) use ($term, $columns, $digits, $digitColumns): void {
            foreach ($columns as $column) {
                $group->orWhere($column, 'like', "%{$term}%");
            }

            foreach ($digitColumns as $column) {
                $group->orWhereRaw(
                    self::digitsOnly($column).' like ?',
                    ["%{$digits}%"],
                );
            }
        });
    }

    /**
     * SQL that strips {@see NOISE} out of a column, so what is compared is the digits.
     *
     * Nested REPLACE rather than REGEXP_REPLACE, which would read better and would tie
     * this to MySQL 8. The column name comes from the model and the characters are
     * literals here, so nothing from a request reaches the string; the search term
     * itself is bound.
     *
     * No index can serve this — it is a function of the column, and the LIKE leads with
     * a wildcard regardless. Both are fine at the scale a workspace's contacts reach,
     * and neither would be fine on a table of movements.
     *
     * @param  literal-string  $column
     * @return literal-string
     */
    private static function digitsOnly(string $column): string
    {
        $expression = $column;

        foreach (self::NOISE as $character) {
            $expression = "REPLACE({$expression}, '{$character}', '')";
        }

        return $expression;
    }
}
