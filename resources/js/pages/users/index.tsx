import { Head, setLayoutProps } from '@inertiajs/react';
import { Users } from 'lucide-react';
import { ColumnHeader, heading } from '@/components/data/column-header';
import { DataTable } from '@/components/data/data-table';
import { DateCell } from '@/components/data/date-cell';
import { FilterPanel } from '@/components/data/filter-panel';
import { SelectFilter } from '@/components/data/select-filter';
import { columnsFor } from '@/components/data/table';
import { EmptyState } from '@/components/feedback/empty-state';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { NewUserButton } from '@/pages/users/_components/new-user-button';
import { UserActions } from '@/pages/users/_components/user-actions';
import { UserStatusBadge } from '@/pages/users/_components/user-status-badge';
import { index } from '@/routes/users';
import type { Paginated, ResourceFilters } from '@/types';

/** Generated from App\Data\UserData — `bun run types:generate`. */
type User = App.Data.UserData;

type Props = {
    users: Paginated<User>;
    filters: ResourceFilters;
    /** Every role a person can be given. Also the form's picker. */
    roles: App.Data.OptionData[];
};

/**
 * The status filter's three answers.
 *
 * "Active" is the empty string rather than a value of its own, because it is the default and
 * {@see FilterApi} turns an empty value into a key absent from the URL — so the ordinary view
 * has a clean address and the other two say what they are.
 */
const STATUS_OPTIONS = [
    { value: '', label: 'users.filter.active' as const },
    { value: 'deactivated', label: 'users.filter.deactivated' as const },
    { value: 'all', label: 'users.filter.all' as const },
];

/**
 * Built once at module scope: TanStack treats the array as an input, and a fresh one each
 * render rebuilds every column instance.
 */
const column = columnsFor<User>();

const columns = column.columns([
    column.accessor('name', {
        ...heading('users.column.name', { width: 'max-w-[18rem]' }),
        cell: ({ row }) => (
            <>
                <span className="font-medium">{row.original.name}</span>
                {/* Under the name rather than in its own column at narrow widths: a
                    person is their name and their address, and splitting them costs the
                    space that makes the name readable. */}
                <span className="block truncate text-muted-foreground text-xs sm:hidden">
                    {row.original.email}
                </span>
            </>
        ),
    }),
    column.accessor('email', {
        ...heading('users.column.email', {
            hideBelow: 'sm',
            width: 'max-w-[16rem] truncate',
        }),
        cell: ({ row }) => (
            <span className="text-muted-foreground">{row.original.email}</span>
        ),
    }),
    column.accessor('role', {
        // Not sortable: it lives on another table, reached through two joins this list has
        // no business writing. The filter is the control for that question.
        ...heading('users.column.role'),
        cell: ({ row }) =>
            row.original.role === null ? (
                // A row whose role was deleted from under it. i18n-allow
                <span className="text-muted-foreground">—</span>
            ) : (
                // The role's own name, typed by somebody in this workspace — data, not a
                // string with a locale. i18n-allow
                <Badge variant="secondary">{row.original.role}</Badge>
            ),
    }),
    column.display({
        id: 'status',
        ...heading('users.column.status'),
        cell: ({ row }) => <UserStatusBadge user={row.original} />,
    }),
    column.accessor('created_at', {
        ...heading('users.column.created', { hideBelow: 'lg' }),
        cell: ({ row }) => <DateCell iso={row.original.created_at} />,
    }),
    column.display({
        id: 'actions',
        header: () => (
            <ColumnHeader label="common.list.actions_column" srOnly />
        ),
        cell: ({ row }) => <UserActions user={row.original} />,
        meta: { align: 'end', width: 'w-12' },
    }),
]);

/**
 * Who may sign in to this workspace.
 *
 * **Active people only, unless somebody asks otherwise.** A list that mixes people who can
 * sign in with people who cannot makes "who has access right now" unanswerable at a glance,
 * and that is the one question this screen exists to answer. Deactivated colleagues are a
 * deliberate second look rather than a permanent half of the list.
 *
 * The empty state is reachable exactly once per workspace — the first administrator looking at
 * a list containing only themselves — so it says what to do rather than that there is nothing
 * here.
 */
export default function UsersIndex({ users, filters }: Props) {
    const { t } = useTranslation();

    setLayoutProps({
        breadcrumbs: [{ title: t('users.title'), href: index() }],
    });

    return (
        <>
            <Head title={t('users.title')} />

            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div className="space-y-1">
                    <h1 className="font-semibold text-2xl tracking-tight">
                        {t('users.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('users.subtitle')}
                    </p>
                </div>
                <NewUserButton />
            </div>

            <DataTable
                href={index().url}
                tableKey="users"
                page={users}
                filters={filters}
                columns={columns}
                getRowId={(user) => String(user.id)}
                only={['users']}
                searchPlaceholder={t('users.search_placeholder')}
                toolbar={(filter) => (
                    <FilterPanel filter={filter}>
                        <SelectFilter
                            value={filter.values.status ?? ''}
                            onChange={(status) => filter.set('status', status)}
                            options={STATUS_OPTIONS}
                            label="users.filter.status"
                            allLabel="users.filter.active"
                        />
                    </FilterPanel>
                )}
                noMatch={{
                    title: t('users.no_match.title'),
                    description: t('users.no_match.description', {
                        term: filters.search,
                    }),
                }}
                emptyState={
                    <EmptyState
                        icon={Users}
                        title={t('users.empty.title')}
                        description={t('users.empty.description')}
                        action={<NewUserButton />}
                    />
                }
            />
        </>
    );
}
