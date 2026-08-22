import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { Paginated } from '@/types';

type Props = {
    href: string;
    // Only the counts are needed; the row type is irrelevant here.
    page: Pick<
        Paginated<unknown>,
        'current_page' | 'last_page' | 'from' | 'to' | 'total'
    >;
    /** Filters to carry across page changes, so paging never drops a search. */
    params: Record<string, string | number | undefined>;
};

/**
 * Prev/next paging with a live count. A single page still shows the count — knowing
 * how many results a search returned is the point, not the buttons.
 */
export function PaginationBar({ href, page, params }: Props) {
    const { t } = useTranslation();

    const go = (to: number) => {
        router.get(
            href,
            { ...params, page: to },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <div className="flex flex-col gap-3 border-t px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-muted-foreground text-sm">
                {page.total === 0
                    ? t('common.pagination.no_results')
                    : t('common.pagination.showing', {
                          from: page.from ?? 0,
                          to: page.to ?? 0,
                          total: page.total,
                      })}
            </p>

            {page.last_page > 1 && (
                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={page.current_page <= 1}
                        onClick={() => go(page.current_page - 1)}
                    >
                        <ChevronLeft className="size-4" />
                        {t('common.pagination.previous')}
                    </Button>
                    <span className="px-1 text-muted-foreground text-sm tabular-nums">
                        {t('common.pagination.page_of', {
                            current: page.current_page,
                            last: page.last_page,
                        })}
                    </span>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        disabled={page.current_page >= page.last_page}
                        onClick={() => go(page.current_page + 1)}
                    >
                        {t('common.pagination.next')}
                        <ChevronRight className="size-4" />
                    </Button>
                </div>
            )}
        </div>
    );
}
