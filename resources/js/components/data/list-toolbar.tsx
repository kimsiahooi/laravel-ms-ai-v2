import { Search, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';

type Props = {
    /** What the server is currently showing, not what is being typed. */
    search: string;
    placeholder: string;
    onSearch: (search: string) => void;
    /** Per-resource controls: a status filter, a date range. */
    extra?: ReactNode;
};

/**
 * The top band: search, and whatever else this resource filters by.
 *
 * Page size deliberately lives in the footer beside the pagination, not here — how
 * many rows to show is a question about the same thing as which page you are on, and
 * keeping the two together leaves this band for narrowing the result set.
 *
 * Issues no requests of its own: it reports what the user asked for and
 * {@see DataTable} decides what that means, so the rule about resetting to page 1
 * has exactly one home.
 */
export function ListToolbar({ search, placeholder, onSearch, extra }: Props) {
    const { t } = useTranslation();
    const [value, setValue] = useState(search);

    // The server's answer is the source of truth for the box: if a redirect clears the
    // search, a stale term must not stay on screen hiding the row just created. This
    // also covers the back button.
    useEffect(() => {
        setValue(search);
    }, [search]);

    useEffect(() => {
        // "Is there anything new to ask for?" — false on mount, and false again once a
        // search lands, which is what stops the answer re-triggering the question.
        if (value === search) {
            return;
        }

        const timer = setTimeout(() => onSearch(value), 300);

        return () => clearTimeout(timer);
    }, [value, search, onSearch]);

    return (
        <div className="flex flex-col gap-3 border-b px-4 py-3 sm:flex-row sm:items-center">
            <div className="relative flex-1 sm:max-w-sm">
                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    type="search"
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    placeholder={placeholder}
                    aria-label={placeholder}
                    className="h-9 pr-9 pl-9"
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

            {extra && (
                <div className="flex items-center gap-2 sm:ml-auto">
                    {extra}
                </div>
            )}
        </div>
    );
}
