import { Search, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Kept in step with ResolvesPerPage::$perPageOptions. A value outside the server's
 * allow-list does not clamp — it falls back to the smallest — so the two lists
 * disagreeing would silently drop a user from 100 rows to 10.
 */
const PER_PAGE_OPTIONS = [10, 25, 50, 100];

type Props = {
    /** What the server is currently showing, not what is being typed. */
    search: string;
    perPage: number;
    placeholder: string;
    onSearch: (search: string) => void;
    onPerPage: (perPage: number) => void;
    /** Per-resource controls: a status filter, a date range. */
    extra?: ReactNode;
};

/**
 * Search and page size. Deliberately issues no requests of its own — it reports what
 * the user asked for and {@see DataTable} decides what that means for the query, so
 * the rule about resetting to page 1 has exactly one home.
 *
 * Typing is debounced; changing the page size is a deliberate click and goes at once.
 */
export function ListToolbar({
    search,
    perPage,
    placeholder,
    onSearch,
    onPerPage,
    extra,
}: Props) {
    const { t } = useTranslation();
    const [value, setValue] = useState(search);

    // The server's answer is the source of truth for the box: if a redirect clears the
    // search, the stale term must not stay on screen hiding the row that was just
    // created. Re-syncing on `search` also covers the back button.
    useEffect(() => {
        setValue(search);
    }, [search]);

    useEffect(() => {
        // "Is there anything new to ask for?" — false on mount, and false again once a
        // search lands, which is what stops the answer from re-triggering the question.
        if (value === search) {
            return;
        }

        const timer = setTimeout(() => onSearch(value), 300);

        return () => clearTimeout(timer);
    }, [value, search, onSearch]);

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
            <div className="relative flex-1 sm:max-w-xs">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    placeholder={placeholder}
                    aria-label={placeholder}
                    className="pr-9 pl-9"
                />
                {value !== '' && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={t('common.actions.clear_search')}
                        className="absolute top-1/2 right-1 size-7 -translate-y-1/2"
                        onClick={() => setValue('')}
                    >
                        <X className="size-4" />
                    </Button>
                )}
            </div>

            {extra}

            <div className="flex items-center gap-2 sm:ml-auto">
                <span className="text-muted-foreground text-sm">
                    {t('common.list.show')}
                </span>
                <Select
                    value={String(perPage)}
                    onValueChange={(next) => onPerPage(Number(next))}
                >
                    <SelectTrigger
                        className="w-20"
                        aria-label={t('common.list.rows_per_page')}
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {PER_PAGE_OPTIONS.map((option) => (
                            <SelectItem key={option} value={String(option)}>
                                {option}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        </div>
    );
}
