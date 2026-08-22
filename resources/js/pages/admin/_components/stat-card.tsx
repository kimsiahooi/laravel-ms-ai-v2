import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';

type Props = {
    label: string;
    value: number | string;
    hint?: string;
    icon: LucideIcon;
};

export function StatCard({ label, value, hint, icon: Icon }: Props) {
    return (
        <Card>
            <CardContent className="flex items-start justify-between gap-4">
                <div className="min-w-0 space-y-1">
                    <p className="text-muted-foreground text-sm">{label}</p>
                    <p className="font-semibold text-2xl tabular-nums">
                        {value}
                    </p>
                    {hint && (
                        <p className="truncate text-muted-foreground text-xs">
                            {hint}
                        </p>
                    )}
                </div>
                <div className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <Icon className="size-4" />
                </div>
            </CardContent>
        </Card>
    );
}
