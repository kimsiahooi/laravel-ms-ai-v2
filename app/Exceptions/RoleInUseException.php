<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Actions\DeleteRole;
use DomainException;

/**
 * A role somebody still holds cannot be deleted — and how many people that is.
 *
 * The count travels on the exception because the sentence needs it: "3 people still hold
 * this role" is an answer, while "this role is in use" leaves somebody opening the Users
 * screen to find out who. The same shape {@see InsufficientStockForOrderException} uses to
 * carry its shortfall rows out of an Action and into a message.
 *
 * Thrown by {@see DeleteRole}, which counts under the same lock it deletes under.
 */
final class RoleInUseException extends DomainException
{
    /**
     * @param  int  $holders  how many people hold the role, deactivated colleagues included.
     *                        Always at least one, or there would be nothing to refuse.
     */
    public function __construct(public readonly int $holders)
    {
        parent::__construct('A role that people still hold cannot be deleted.');
    }
}
