<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Actions\DeactivateUser;
use App\Actions\SaveUser;
use App\Data\OptionData;
use App\Data\UserData;
use App\Http\Controllers\Concerns\ReadsQueryValues;
use App\Http\Controllers\Concerns\RendersResourceIndex;
use App\Http\Controllers\Concerns\ResolvesPerPage;
use App\Http\Controllers\Concerns\RespondsWithToast;
use App\Http\Controllers\Concerns\SortsResourceQuery;
use App\Http\Requests\Tenant\UserRequest;
use App\Models\User;
use Closure;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Who may sign in to this workspace, and what each of them can reach.
 *
 * The same shape as the catalog screens — one list, a dialog over it, every write returning
 * `back()`. **Deliberately dialogs rather than form pages**, and not only for consistency: a
 * GET `users.create` page renders every role in the workspace, and until this slice's
 * predecessor taught `routeMap()` to map form pages, such a page would have been reachable by
 * anybody with a login. Three fields do not need a page anyway.
 *
 * **Deactivating is a soft delete, and the word on screen is "deactivate" rather than
 * "delete".** Orders name the person who raised them, so the row has to stay; and what an
 * administrator actually wants is for somebody to stop being able to sign in, which is what
 * this does. Their email stays reserved while they are away — see {@see User}.
 *
 * **Nothing here decides whether an action is safe.** {@see SaveUser} and
 * {@see DeactivateUser} hold the invariant that a workspace keeps at least one administrator,
 * because it is a fact about other rows rather than about a payload, and because the count and
 * the write belong in one transaction. This controller turns their refusal into a sentence.
 */
final class UserController
{
    use ReadsQueryValues;
    use RendersResourceIndex;
    use ResolvesPerPage;
    use RespondsWithToast;
    use SortsResourceQuery;

    /**
     * Columns a listing may be ordered by — the SQL-injection guard for `?sort=`.
     *
     * `role` is absent for the reason {@see ProductController} gives about its category: it
     * lives on another table, reached through two joins that this list has no business
     * writing.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['name', 'email', 'created_at'];

    public function index(Request $request): Response
    {
        $status = $this->statusFilter($request);

        $query = User::query()->with('roles');

        // Active by default. A list that mixes people who can sign in with people who cannot
        // makes "who has access right now" unanswerable at a glance, which is the one question
        // this screen exists to answer.
        match ($status) {
            'deactivated' => $query->onlyTrashed(),
            'all' => $query->withTrashed(),
            default => null,
        };

        ['rows' => $users, 'filters' => $filters] = $this->resourceList(
            request: $request,
            query: $query,
            sortable: self::SORTABLE,
            toData: $this->toData($request),
            searchUsing: self::searchBy(...),
            extra: ['status' => $status],
        );

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => $filters,
            'roles' => self::roleOptions(),
        ]);
    }

    /** Add a colleague, with a password their administrator types for them. */
    public function store(UserRequest $request, SaveUser $save): RedirectResponse
    {
        $user = $save->handle($this->fields($request));

        $this->toast(__('users.toast.created', ['name' => $user->name]));

        return back();
    }

    /**
     * Change one.
     *
     * A refusal from the Action is the double-press race — the ordinary demotion is already
     * refused by {@see UserRequest}, under the field somebody would change.
     */
    public function update(UserRequest $request, User $user, SaveUser $save): RedirectResponse
    {
        try {
            $save->handle($this->fields($request), $user);
        } catch (DomainException) {
            return $this->refuse(__('users.error.last_administrator'));
        }

        $this->toast(__('users.toast.updated', ['name' => $user->name]));

        return back();
    }

    /** Stop somebody signing in. The row, and everything naming it, stays. */
    public function destroy(Request $request, User $user, DeactivateUser $deactivate): RedirectResponse
    {
        try {
            $deactivate->handle($user, self::signedInUser($request));
        } catch (DomainException) {
            return $this->refuse(
                $user->is($request->user())
                    ? __('users.error.not_yourself')
                    : __('users.error.last_administrator'),
            );
        }

        $this->toast(__('users.toast.deactivated', ['name' => $user->name]));

        return back();
    }

    /** Let them back in. */
    public function restore(User $user, DeactivateUser $deactivate): RedirectResponse
    {
        $deactivate->restore($user);

        $this->toast(__('users.toast.restored', ['name' => $user->name]));

        return back();
    }

    /**
     * The validated fields, in the shape {@see SaveUser} declares.
     *
     * A blank password on an edit arrives as `''` and leaves as `null`, which is what the
     * Action reads as "leave theirs alone". The empty string would be a password.
     *
     * @return array{name: string, email: string, role_id: int, password: string|null}
     */
    private function fields(UserRequest $request): array
    {
        $password = (string) $request->validated('password', '');

        return [
            'name' => (string) $request->validated('name'),
            'email' => (string) $request->validated('email'),
            'role_id' => (int) $request->validated('role_id'),
            'password' => $password === '' ? null : $password,
        ];
    }

    /**
     * How each row is shaped, with the administrator count taken once rather than per row.
     *
     * @return Closure(User): UserData
     */
    private function toData(Request $request): Closure
    {
        $actor = self::signedInUser($request);
        $administrators = User::administrators()->count();

        return static fn (User $user): UserData => UserData::fromUser($user, $actor, $administrators);
    }

    /**
     * Which people the screen is asking about: active, deactivated, or both.
     *
     * An unrecognised value is the default rather than an error, and is not echoed back —
     * `?status=nonsense` should not sit in the URL looking as though it did something.
     */
    private function statusFilter(Request $request): string
    {
        $requested = $this->queryValue($request, 'status');

        return in_array($requested, ['deactivated', 'all'], true) ? $requested : '';
    }

    /**
     * Every role a person can be given.
     *
     * `OptionData`, so the picker is the same `ComboboxField` every other row-picker in the
     * app uses. By id rather than by name, because a role's name is something somebody can
     * rename and an id is not — see {@see UserRequest}.
     *
     * The names are **not** translated and must not be: `Administrator` is seeded, but every
     * other role is a word this workspace chose, and there is no locale in which "Stock clerk"
     * becomes something else unless somebody types it.
     *
     * @return list<OptionData>
     */
    private static function roleOptions(): array
    {
        $roles = Role::query()->where('guard_name', 'web')->orderBy('name')->get();

        // Built by hand rather than through `OptionData::fromModel`: spatie types a role's
        // key as `int|string`, and that helper promises an int.
        return array_values(
            $roles->map(static fn (Role $role): OptionData => new OptionData(
                id: (int) $role->getKey(),
                name: $role->name,
            ))->all(),
        );
    }

    /**
     * What searching people means: their name or their email, and nothing else. Not the role —
     * that is a filter, and matching English role names would work in one locale only.
     *
     * @param  Builder<User>  $query
     */
    private static function searchBy(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $group) use ($like): void {
            $group->where('name', 'like', $like)->orWhere('email', 'like', $like);
        });
    }

    /**
     * The signed-in person, or nobody.
     *
     * A console super-admin is a `CentralUser` in another database — see
     * {@see SalesOrderController::signedInUser()} for the same narrowing and the same reason.
     */
    private static function signedInUser(Request $request): ?User
    {
        $signedIn = $request->user();

        return $signedIn instanceof User ? $signedIn : null;
    }

    /** Refuse a lifecycle action in this app's own voice, never a bare 422. */
    private function refuse(string $message): RedirectResponse
    {
        $this->toast($message, 'error');

        return back();
    }
}
