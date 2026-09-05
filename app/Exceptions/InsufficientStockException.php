<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\StockService;
use RuntimeException;

/**
 * Thrown when a movement would drive on-hand below zero.
 *
 * The one invariant {@see StockService} exists to hold. It is a refusal
 * rather than a fault — somebody asked to issue more than is there — so callers turn it
 * into an error toast on the screen it was pressed from, and nothing reports it.
 *
 * It carries the numbers as well as the sentence, because "not enough stock" is not
 * actionable and "you have 4.5, this would take 7" is. Both are decimal strings, for
 * the same reason the service works in them — see the class.
 */
final class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $available,
        public readonly string $requested,
        string $message,
    ) {
        parent::__construct($message);
    }
}
