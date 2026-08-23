<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsCreator;
use App\Models\Concerns\Searchable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A supplier. Per-tenant, on the default connection — which InitializeTenancyByPath
 * has already repointed at this workspace's database.
 *
 * @property int $id
 * @property string $name
 * @property string|null $contact_person
 * @property string|null $email
 * @property string|null $tax_id
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User|null $creator
 */
#[Fillable(['name', 'contact_person', 'email', 'tax_id', 'phone', 'address', 'notes'])]
class Supplier extends Model
{
    use RecordsCreator;
    use Searchable;
    use SoftDeletes;

    /**
     * What "find a supplier" means: the company, the person you deal with, the address
     * you reach them at, and whatever was written down about them.
     *
     * `tax_id` and `phone` are absent on purpose — both are looked up by exact value,
     * and a LIKE over them would match a fragment of one number inside another.
     *
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return ['name', 'contact_person', 'email', 'notes'];
    }
}
