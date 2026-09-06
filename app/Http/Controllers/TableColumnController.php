<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\TableKey;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Models\CentralUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Which columns somebody looks at: saving one list, and clearing every list.
 *
 * Central (not tenant-prefixed) is not an option here the way it is for the language
 * switcher: this writes to the signed-in user's row, and a workspace's users live in the
 * workspace's own database. Hence two routes, one per area — see routes/tenant.php for
 * why a workspace cannot simply post to the central one.
 *
 * One controller serves both areas, because Laravel's `Authenticate` middleware calls
 * `shouldUse()` on the guard that passes: inside an `auth:central` route `$request->user()`
 * already *is* the CentralUser. No area sniffing needed.
 *
 * **Validated inline, with no FormRequest and no zod schema** — the same call
 * {@see LocaleController} makes, for the same reason. This is a background save, not a
 * form: nothing is typed, nothing is submitted, and there is no field for an error to land
 * under. A FormRequest here would exist only to be added to `check:validation`'s exempt
 * list, which is ceremony rather than safety.
 *
 * **Answers 204, and does not redirect back.** This is the one place the parallel with
 * {@see LocaleController} breaks, and deliberately: a language switch is a navigation and
 * should re-render, while this is a save nobody is waiting for. Returning `back()` made it
 * an Inertia visit, which meant every failure went through Inertia's page lifecycle — a
 * 500 or an expired session threw a full-screen error overlay in front of somebody who had
 * ticked a checkbox, saying nothing about columns, while a dropped connection said nothing
 * at all. An empty response lets the browser decide what a failure means; see
 * use-column-layout.ts, which turns one into a sentence about columns.
 */
final class TableColumnController extends Controller
{
    use RespondsWithToast;

    /** Save one list's layout. */
    public function update(Request $request): Response
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
            // A console user, or a guard that resolved to neither. Nothing to write, and
            // nothing worth telling the browser about a preference it cannot store.
            return response()->noContent();
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

        return response()->noContent();
    }

    /**
     * Put every list back to the columns it declares.
     *
     * The counterpart to the panel's own Reset, which only ever knows about the list it is
     * open on. Somebody who has rearranged six screens and wants to start over would
     * otherwise have to visit six screens to do it.
     *
     * Unlike {@see update} this *is* a navigation — somebody pressed a button and is
     * waiting — so it redirects with a toast rather than answering 204. Reachable only
     * from the tenant settings screen; a super-admin has no settings area, and with two
     * lists the per-panel Reset covers them.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User || $user instanceof CentralUser) {
            $user->forceFill(['table_columns' => null])->save();
        }

        $this->toast(__('settings.columns.toast'));

        return back();
    }
}
