<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customers — who the workspace sells to.
 *
 * Wider than suppliers, and for one reason: an invoice has to be addressed to a legal
 * entity, not to a name. The tax identity (TIN, registration number, SST/GST) and the
 * broken-out address exist because MyInvois (MY) and InvoiceNow (SG) both demand the
 * buyer's details as separate fields — a single free-text address cannot be mapped to
 * either payload.
 *
 * All of it is optional. Most customers are added mid-conversation, long before anyone
 * knows their TIN, and a form that refused to save without one would just be filled
 * with rubbish.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('contact_person')->nullable();
            // Nullable AND unique — MySQL permits any number of NULLs in a unique index.
            $table->string('email')->nullable()->unique();
            $table->string('phone', 50)->nullable();

            // Tax identity, for the e-invoice payload.
            $table->string('tin', 100)->nullable();                 // Tax Identification Number
            $table->string('registration_no', 100)->nullable();     // SSM (MY) / UEN (SG)
            $table->string('sst_registration_no', 100)->nullable(); // SST (MY) / GST (SG)

            // Address, broken out because the e-invoice standards want it that way.
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('state_code', 10)->nullable();
            // ISO 3166-1 alpha-2, constrained to App\Enums\Country by validation.
            $table->string('country_code', 2)->nullable();

            $table->text('notes')->nullable();
            $table->foreignIdFor(User::class, 'created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            // Sales orders and invoices will reference customers; deleting one must not
            // orphan the documents that name it.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
