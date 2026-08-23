<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The units a material or a product can be counted in.
 *
 * An enum rather than v1's free-text column, and the reason is conversion. "kg" and "KG"
 * typed into a text box are two different units to the database; harmless in a catalog,
 * and not harmless at all in a stock engine that adds quantities together. Fixing that
 * after the fact means reconciling every row somebody has already typed, so it is fixed
 * here, before there are any.
 *
 * Each case carries the two things a conversion needs — a {@see Dimension} and a factor
 * to that dimension's base unit — so `g → kg` is arithmetic the enum can do and
 * `kg → L` is a question it refuses to answer.
 *
 * **What this deliberately does NOT model: pack sizes.** "One box = 24 pieces" is a fact
 * about a particular material, not about the word "box" — a box of screws and a box of
 * paper hold different amounts. That belongs on the material as its own field if it is
 * ever wanted; putting it here would make every box in the workspace the same size.
 *
 * `#[TypeScript]` emits `App.Enums.Unit`, so a unit the browser does not know about is a
 * tsc error rather than a value that reaches the database. The user-facing names and
 * symbols are not here — they are strings like any other and live in
 * `lang/{locale}/units.php`, keyed by these codes.
 *
 * {@see convert()} and {@see isConvertibleTo()} have no caller yet. Their consumer is
 * the stock engine in phase 4; they are written now because they are the entire reason
 * this is an enum, and because the numbers below are physical constants rather than a
 * guess at a shape.
 */
#[TypeScript]
enum Unit: string
{
    // Mass, base gram.
    case Gram = 'g';
    case Kilogram = 'kg';
    case Tonne = 't';

    // Volume, base millilitre.
    case Millilitre = 'ml';
    case Litre = 'l';

    // Length, base millimetre.
    case Millimetre = 'mm';
    case Centimetre = 'cm';
    case Metre = 'm';

    // Count. No universal factor between any two of these — see Dimension::Count.
    case Piece = 'pcs';
    case Box = 'box';
    case Roll = 'roll';
    case Sheet = 'sheet';
    case Pair = 'pair';
    case Set = 'set';

    /** What this unit measures. Conversion is only ever within one dimension. */
    public function dimension(): Dimension
    {
        return match ($this) {
            self::Gram, self::Kilogram, self::Tonne => Dimension::Mass,
            self::Millilitre, self::Litre => Dimension::Volume,
            self::Millimetre, self::Centimetre, self::Metre => Dimension::Length,
            self::Piece, self::Box, self::Roll,
            self::Sheet, self::Pair, self::Set => Dimension::Count,
        };
    }

    /**
     * How many of the dimension's base unit make one of this — gram for mass,
     * millilitre for volume, millimetre for length.
     *
     * Integers, not floats, and every one of them exact. A decimal factor here would put
     * a rounding error inside every quantity that passed through it, which is the one
     * class of bug this whole enum exists to prevent.
     *
     * Count units return 1 and never actually get used for arithmetic; see below.
     */
    public function factor(): int
    {
        return match ($this) {
            self::Gram => 1,
            self::Kilogram => 1_000,
            self::Tonne => 1_000_000,

            self::Millilitre => 1,
            self::Litre => 1_000,

            self::Millimetre => 1,
            self::Centimetre => 10,
            self::Metre => 1_000,

            self::Piece, self::Box, self::Roll,
            self::Sheet, self::Pair, self::Set => 1,
        };
    }

    /**
     * Whether a quantity in this unit can be restated in `$other`.
     *
     * Same dimension, and never across two different count units: a roll is not a
     * number of sheets, and answering as though it were would be worse than refusing.
     */
    public function isConvertibleTo(self $other): bool
    {
        if ($this === $other) {
            return true;
        }

        if ($this->dimension() !== $other->dimension()) {
            return false;
        }

        return $this->dimension() !== Dimension::Count;
    }

    /**
     * Restate `$quantity` of this unit in `$other`, or null when that is not a question
     * with an answer.
     *
     * Null rather than an exception on purpose: "these two units are not comparable" is
     * an ordinary thing for a caller to ask and handle, not an exceptional one.
     */
    public function convert(float $quantity, self $other): ?float
    {
        if (! $this->isConvertibleTo($other)) {
            return null;
        }

        return $quantity * $this->factor() / $other->factor();
    }

    /**
     * The codes grouped by what they measure, in declaration order, for the picker.
     *
     * The grouping is the server's because the dimensions are: the browser gets the
     * shape, and looks the words up in `lang/{locale}/units.php` like everything else.
     *
     * @return array<string, list<string>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $unit) {
            $groups[$unit->dimension()->value][] = $unit->value;
        }

        return $groups;
    }
}
