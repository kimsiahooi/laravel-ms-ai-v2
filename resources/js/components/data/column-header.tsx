import type { ReactNode } from 'react';
import type { ColumnMeta } from '@/components/data/table';
import { useTranslation } from '@/hooks/use-translation';
import type { TranslationKey } from '@/types/lang';

/**
 * A translated column heading.
 *
 * Columns are declared at module scope — TanStack treats the array as an input, and
 * rebuilding it each render rebuilds every column instance — so a header cannot close
 * over the page's `t`. This reads the locale from the page props the way every other
 * component does, which also means a header follows a language change without the
 * column definitions being touched.
 *
 * `srOnly` is for a column whose heading exists only for screen readers, such as the
 * one holding row actions.
 */
export function ColumnHeader({
    label,
    srOnly,
}: {
    label: TranslationKey;
    srOnly?: boolean;
}) {
    const { t } = useTranslation();

    if (srOnly) {
        return <span className="sr-only">{t(label)}</span>;
    }

    return <>{t(label)}</>;
}

/**
 * A column's heading and its entry in the Columns panel, from one translation key.
 *
 *     column.accessor('sku', {
 *         ...heading('products.column.sku', { hideBelow: 'sm' }),
 *         cell: ({ row }) => …,
 *     })
 *
 * **The key is written once on purpose.** Rendering the header takes a `HeaderContext`
 * the panel does not have, so a name declared only inside `header` is unreachable from a
 * menu — and the alternative, repeating the key in `meta.label`, is two copies of one
 * truth with nothing to keep them equal. This sets both from the same argument.
 *
 * Spread it *before* any other key, and pass presentation through the second parameter
 * rather than a sibling `meta:` — a later `meta:` would replace the label rather than
 * merge with it, and the column would quietly drop out of the panel.
 */
export function heading(
    label: TranslationKey,
    meta?: Omit<ColumnMeta, 'label'>,
): { header: () => ReactNode; meta: ColumnMeta } {
    return {
        header: () => <ColumnHeader label={label} />,
        meta: { ...meta, label },
    };
}
