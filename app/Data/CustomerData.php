<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\Country;
use App\Models\Customer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One customer, as the listing sends it.
 *
 * The whole record travels, not just the columns the table shows: the edit dialog is
 * opened from a row and seeds thirteen fields from exactly this object, so fetching the
 * rest would be a round trip for data already on the page.
 *
 * `country_code` stays a {@see Country} rather than being flattened to a string. The
 * transformer then emits `App.Enums.Country | null` instead of `string | null`, so a
 * typo in the browser is a tsc error rather than a code that reaches an e-invoice.
 */
#[TypeScript]
final class CustomerData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $contact_person,
        public ?string $email,
        public ?string $phone,
        public ?string $tin,
        public ?string $registration_no,
        public ?string $sst_registration_no,
        public ?string $address,
        public ?string $city,
        public ?string $postcode,
        public ?string $state_code,
        public ?Country $country_code,
        public ?string $notes,
        public string $created_at,
        public ?string $creator,
    ) {}

    public static function fromCustomer(Customer $customer): self
    {
        return new self(
            id: $customer->id,
            name: $customer->name,
            contact_person: $customer->contact_person,
            email: $customer->email,
            phone: $customer->phone,
            tin: $customer->tin,
            registration_no: $customer->registration_no,
            sst_registration_no: $customer->sst_registration_no,
            address: $customer->address,
            city: $customer->city,
            postcode: $customer->postcode,
            state_code: $customer->state_code,
            country_code: $customer->country_code,
            notes: $customer->notes,
            created_at: $customer->created_at->toIso8601String(),
            creator: $customer->creator?->name,
        );
    }
}
