import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { TranslationKey } from '@/types/lang';

/**
 * The same three choices the header's ThemeToggle offers, reading the same keys. They
 * were spelled out in English here, which meant the appearance page said "Light" while
 * the menu two inches above it said "Cerah".
 *
 * Module scope, like ThemeToggle's: it is a static descriptor, and rebuilding it on
 * every render gives each button a new object for no reason.
 */
const TABS: { value: Appearance; icon: LucideIcon; label: TranslationKey }[] = [
    { value: 'light', icon: Sun, label: 'common.theme.light' },
    { value: 'dark', icon: Moon, label: 'common.theme.dark' },
    { value: 'system', icon: Monitor, label: 'common.theme.system' },
];

export default function AppearanceToggleTab({
    className = '',
    ...props
}: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();
    const { t } = useTranslation();

    return (
        <div
            className={cn(
                'inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800',
                className,
            )}
            {...props}
        >
            {TABS.map(({ value, icon: Icon, label }) => (
                <button
                    type="button"
                    key={value}
                    onClick={() => updateAppearance(value)}
                    className={cn(
                        'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                        appearance === value
                            ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                            : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                    )}
                >
                    <Icon className="-ml-1 h-4 w-4" />
                    <span className="ml-1.5 text-sm">{t(label)}</span>
                </button>
            ))}
        </div>
    );
}
