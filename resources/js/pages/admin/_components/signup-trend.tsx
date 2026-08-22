import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

export type SignupDay = {
    date: string;
    label: string;
    count: number;
};

/**
 * New workspaces per day over the last 30 days, drawn with plain elements rather
 * than a charting library — one series of small integers does not justify the
 * dependency, and the bars are pure functions of the props, so SSR and hydration
 * render identically.
 */
export function SignupTrend({ days }: { days: SignupDay[] }) {
    const peak = Math.max(1, ...days.map((day) => day.count));
    const total = days.reduce((sum, day) => sum + day.count, 0);

    return (
        <Card>
            <CardHeader>
                <CardTitle>New workspaces</CardTitle>
                <CardDescription>
                    {total === 0
                        ? 'None created in the last 30 days.'
                        : `${total} created in the last 30 days.`}
                </CardDescription>
            </CardHeader>
            <CardContent className="space-y-2">
                <div className="flex h-28 items-end gap-1">
                    {days.map((day) => (
                        <Tooltip key={day.date}>
                            <TooltipTrigger asChild>
                                <div className="group flex h-full flex-1 items-end">
                                    <div
                                        className="w-full rounded-sm bg-primary/20 transition-colors group-hover:bg-primary"
                                        style={{
                                            // Always a sliver, so an empty day is
                                            // visibly a day rather than a gap.
                                            height: `${Math.max(4, (day.count / peak) * 100)}%`,
                                        }}
                                    />
                                </div>
                            </TooltipTrigger>
                            <TooltipContent>
                                {day.label}: {day.count}
                            </TooltipContent>
                        </Tooltip>
                    ))}
                </div>
                <div className="flex justify-between text-muted-foreground text-xs">
                    <span>{days[0]?.label}</span>
                    <span>{days[days.length - 1]?.label}</span>
                </div>
            </CardContent>
        </Card>
    );
}
