<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Arithmetic on money, in decimal strings.
 *
 * **Never floats.** v1 computed every order total as a PHP float inside its Data classes —
 * `$total += $item->quantity * $item->unit_cost` — and stored none of them. Two faults in
 * one line: money in binary floating point, and a figure that is re-derived on every read
 * rather than recorded. This is the first of those fixed; storing the result is the second,
 * and belongs to the Action that computes it.
 *
 * {@see StockService} does the same job for quantities and keeps its own private scale.
 * The two are deliberately separate: a quantity is counted and a price is charged, they
 * round at different points, and money has a currency while a quantity has a unit.
 *
 * **Two scales, and the difference matters.** Arithmetic runs at {@see SCALE} — four, the
 * shape every decimal column in this schema uses — so a percentage of a percentage does
 * not lose precision halfway through. A figure only becomes *money* when it is stored as a
 * total or shown to somebody, and there it is rounded to the currency's own scale, because
 * nobody pays a hundredth of a cent.
 */
final class Money
{
    /**
     * The working scale — `decimal(15,4)`, the same shape as every other decimal column.
     *
     * Wide enough that a unit price of `1.2345` survives being multiplied by a quantity
     * and discounted, and narrow enough to store without rounding.
     */
    public const SCALE = 4;

    /**
     * What a currency's minor unit is worth, where it is not a hundredth.
     *
     * Every currency this app has been asked for so far divides by 100, so the map holds
     * only the exceptions and {@see scaleFor()} defaults to two. It exists at all because
     * rounding a yen total to two places invents a precision the currency does not have,
     * and that is a bug nobody notices until a Japanese supplier is added.
     *
     * @var array<string, int>
     */
    private const CURRENCY_SCALE = [
        'JPY' => 0,
        'KRW' => 0,
        'VND' => 0,
        'IDR' => 0,
        'CLP' => 0,
        'ISK' => 0,
        'BHD' => 3,
        'KWD' => 3,
        'OMR' => 3,
        'JOD' => 3,
        'TND' => 3,
    ];

    /** How many decimal places this currency actually has. Two unless it does not. */
    public static function scaleFor(string $currency): int
    {
        return self::CURRENCY_SCALE[strtoupper($currency)] ?? 2;
    }

    public static function zero(): string
    {
        return bcadd('0', '0', self::SCALE);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd(self::decimal($a), self::decimal($b), self::SCALE);
    }

    public static function subtract(string $a, string $b): string
    {
        return bcsub(self::decimal($a), self::decimal($b), self::SCALE);
    }

    public static function multiply(string $a, string $b): string
    {
        return bcmul(self::decimal($a), self::decimal($b), self::SCALE);
    }

    /**
     * `$rate` percent of `$amount` — a discount or a tax, which are the same sum.
     *
     * The division happens last so `33.333%` of a large number keeps its precision until
     * the working scale truncates it, rather than losing it in an intermediate `0.33333`.
     */
    public static function percent(string $amount, string $rate): string
    {
        return bcdiv(
            bcmul(self::decimal($amount), self::decimal($rate), self::SCALE + 4),
            '100',
            self::SCALE,
        );
    }

    /**
     * Round half away from zero, to `$scale` places.
     *
     * **bcmath truncates, it does not round.** `bcadd('1.005', '0', 2)` is `1.00`, which
     * would quietly shave a cent off roughly half of all tax lines. Adding half a unit of
     * the target scale before letting bcmath truncate is the standard fix, and the sign
     * has to be handled explicitly or negative amounts round the wrong way — a credit note
     * would be a cent light rather than a cent heavy.
     */
    public static function round(string $amount, int $scale = 2): string
    {
        $half = self::decimal($scale > 0 ? '0.'.str_repeat('0', $scale).'5' : '0.5');

        return self::isNegative($amount)
            ? bcsub(self::decimal($amount), $half, $scale)
            : bcadd(self::decimal($amount), $half, $scale);
    }

    /** Round to what the currency can actually express. */
    public static function roundTo(string $amount, string $currency): string
    {
        return self::round($amount, self::scaleFor($currency));
    }

    public static function isNegative(string $amount): bool
    {
        return bccomp(self::decimal($amount), '0', self::SCALE) < 0;
    }

    public static function isZero(string $amount): bool
    {
        return bccomp(self::decimal($amount), '0', self::SCALE) === 0;
    }

    /** -1, 0 or 1, as `bccomp` returns, at the working scale. */
    public static function compare(string $a, string $b): int
    {
        return bccomp(self::decimal($a), self::decimal($b), self::SCALE);
    }

    /**
     * Refuse a non-numeric operand at the door, and prove the rest numeric.
     *
     * The same guard {@see \App\Services\StockService} applies to quantities, for the
     * same two reasons. bcmath in PHP 8 throws a `ValueError` naming one of its own
     * arguments, which is a 500 that says nothing about the value somebody actually sent;
     * and it is what lets the static analyser see that every operand really is numeric
     * rather than being told to assume it.
     *
     * @return numeric-string
     */
    private static function decimal(string $value): string
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException(
                sprintf('Money amounts must be numeric, got "%s".', $value),
            );
        }

        return $value;
    }
}
