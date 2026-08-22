import { router } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
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

const PER_PAGE_OPTIONS = [10, 25, 50, 100];

type Props = {
    href: string;
    search: string;
    perPage: number;
    placeholder: string;
};

/**
 * Search + page size for a server-paginated list. Both write straight to the query
 * string, so the current view is a URL a person can bookmark or share.
 *
 * Typing is debounced and replaces the history entry, so a search does not bury the
 * previous page in the back button. Changing the page size is a deliberate click, so
 * it goes immediately. Neither sends `page`, which resets the list to the first one —
 * page 7 of a different result set is meaningless.
 */
export function ListToolbar({ href, search, perPage, placeholder }: Props) {
    const [value, setValue] = useState(search);

    useEffect(() => {
        // `search` is what the server is currently showing, so this is "is there
        // anything new to ask for?". It is false on mount, and false again once a
        // search lands — which is also what stops a page-size change (which sends its
        // own request below) from firing a second, duplicate one.
        if (value === search) {
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                href,
                { search: value || undefined, per_page: perPage },
                { preserveState: true, preserveScroll: true, replace: true },
            );
        }, 300);

        return () => clearTimeout(timer);
    }, [value, search, perPage, href]);

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
                        aria-label="Clear search"
                        className="absolute top-1/2 right-1 size-7 -translate-y-1/2"
                        onClick={() => setValue('')}
                    >
                        <X className="size-4" />
                    </Button>
                )}
            </div>

            <div className="flex items-center gap-2 sm:ml-auto">
                <span className="text-muted-foreground text-sm">Show</span>
                <Select
                    value={String(perPage)}
                    onValueChange={(next) =>
                        router.get(
                            href,
                            {
                                search: value || undefined,
                                per_page: Number(next),
                            },
                            { preserveState: true, preserveScroll: true },
                        )
                    }
                >
                    <SelectTrigger className="w-20" aria-label="Rows per page">
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
