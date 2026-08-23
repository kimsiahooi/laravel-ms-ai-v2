<?php

declare(strict_types=1);

namespace App\Data;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One choice in a picker — an id and the words to show for it.
 *
 * Shared rather than per-resource on purpose: a category, a supplier and a raw material
 * are entirely different things, and a picker of them is the same thing three times. The
 * name travels because it is the row's own data, not a translatable string; that is what
 * separates this from the country and unit pickers, whose labels are looked up in `lang/`.
 */
#[TypeScript]
final class OptionData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}

    /**
     * @param  Model&object{id: int, name: string}  $model
     */
    public static function fromModel(Model $model): self
    {
        return new self(id: $model->id, name: $model->name);
    }
}
