<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Data\BusinessSettingsData;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Requests\Tenant\SettingsUpdateRequest;
use App\Models\BusinessSetting;
use App\Support\DocumentNumberGenerator;
use App\Support\TenantPermissions;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The workspace's own settings — what its books are kept in, what it charges, and what
 * its documents are called.
 *
 * **One row, so no index and no store.** There is nothing to list and nothing to
 * create: {@see BusinessSetting::current()} either finds the row or makes it, and this
 * screen only ever edits that one. The read route is still named `settings.index`
 * because {@see TenantPermissions} maps that name to `settings.view` and is already
 * seeded across every workspace — a better-reading name would be an unmapped route,
 * and an unmapped route is open to any signed-in user rather than refused.
 *
 * Nothing here interprets the settings. Reading them is {@see DocumentNumberGenerator}
 * and the order screens' job; this controller hands the row to the form and hands the
 * form's answer back to the row.
 */
final class BusinessSettingsController
{
    use RespondsWithToast;

    public function index(): Response
    {
        return Inertia::render('business-settings/index', [
            'settings' => BusinessSettingsData::fromSettings(BusinessSetting::current()),
            // The catalog to choose from, sent rather than hard-coded in the page, so
            // the list the browser offers cannot drift from the list the request will
            // accept — both are this one method.
            'currencies' => BusinessSetting::defaultCurrencies(),
        ]);
    }

    /**
     * Every field at once, because they are read together and half of them only make
     * sense beside another: a prefix without its reset mode does not describe a
     * document number, and a tax rate without its label does not describe a tax.
     *
     * `back()` rather than a redirect to the route. The screen is the only thing that
     * ever posts here, and returning to it by name would drop the scroll position on a
     * form long enough to have one.
     */
    public function update(SettingsUpdateRequest $request): RedirectResponse
    {
        BusinessSetting::current()->update($request->validated());

        $this->toast(__('business-settings.toast.saved'));

        return back();
    }
}
