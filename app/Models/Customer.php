<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Country;
use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A customer. Per-tenant, on the default connection — which InitializeTenancyByPath
 * has already repointed at this workspace's database.
 *
 * @property int $id
 * @property string $name
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $tin
 * @property string|null $registration_no
 * @property string|null $sst_registration_no
 * @property string|null $address
 * @property string|null $city
 * @property string|null $postcode
 * @property string|null $state_code
 * @property Country|null $country_code
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creator
 */
#[Fillable([
    'name', 'contact_person', 'email', 'phone',
    'tin', 'registration_no', 'sst_registration_no',
    'address', 'city', 'postcode', 'state_code', 'country_code',
    'notes',
])]
class Customer extends Model
{
    use RecordsCreator;
    use Searchable;
    use SoftDeletes;

    /**
     * What "find a customer" means: the company, the person, how you reach them, and
     * the two identifiers someone would actually paste in from a purchase order.
     *
     * The address parts are absent. A LIKE across `city` and `postcode` turns a search
     * for a name into a search for a region, and the result is a list nobody asked for.
     *
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'contact_person', 'email', 'tin', 'registration_no', 'notes'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        // The column holds 'MY'; everything that reads it gets a Country. Validation
        // already refuses anything else, so the cast can never meet a value it cannot
        // resolve.
        return ['country_code' => Country::class];
    }
}
