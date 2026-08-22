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
