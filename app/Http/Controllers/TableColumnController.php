<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TableKey;
use App\Models\CentralUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Saves which columns somebody looks at on one list.
 *
 * Central (not tenant-prefixed) is not an option here the way it is for the language
 * switcher: this writes to the signed-in user's row, and a workspace's users live in the
 * workspace's own database. Hence two routes, one per area — see routes/tenant.php for
 * why a workspace cannot simply post to the central one.
 *
 * One `__invoke` serves both, because Laravel's `Authenticate` middleware calls
 * `shouldUse()` on the guard that passes: inside an `auth:central` route `$request->user()`
 * already *is* the CentralUser. No area sniffing needed.
 *
 * **Validated inline, with no FormRequest and no zod schema** — the same call
 * {@see LocaleController} makes, for the same reason. This is a background save, not a
 * form: nothing is typed, nothing is submitted, and there is no field for an error to land
 * under. A FormRequest here would exist only to be added to `check:validation`'s exempt
 * list, which is ceremony rather than safety.
 */
final class TableColumnController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        // Every rule below has a translated message. `required_with` deliberately does
        // not appear: it has none, and `check:i18n` only reads app/Http/Requests, so an
        // inline rule that fired would have said its piece in English in all three
        // locales with nothing reporting it.
        $validated = $request->validate([
            'table' => ['required', Rule::enum(TableKey::class)],
            // Absent or null means "back to default" — the entry is removed rather than
            // storing a copy of what the columns already declare.
            'layout' => ['nullable', 'array'],
            // Bounded on both axes. This is the only thing stopping a signed-in user
            // growing their own row without limit.
            'layout.order' => ['array', 'max:40'],
            'layout.order.*' => ['string', 'max:64'],
            'layout.hidden' => ['array', 'max:40'],
            'layout.hidden.*' => ['string', 'max:64'],
        ]);

        $user = $request->user();

        if (! $user instanceof User && ! $user instanceof CentralUser) {
            return back();
        }

        $stored = is_array($user->table_columns) ? $user->table_columns : [];
        $table = $validated['table'];
        $layout = $validated['layout'] ?? null;

        if ($layout === null) {
            unset($stored[$table]);
        } else {
            $stored[$table] = [
                'order' => array_values($layout['order'] ?? []),
                'hidden' => array_values($layout['hidden'] ?? []),
            ];
        }

        // forceFill, because `table_columns` is not in #[Fillable] — it is written from
        // here and nowhere else, exactly as `locale` is.
        $user->forceFill(['table_columns' => $stored === [] ? null : $stored])->save();

        return back();
    }
}
