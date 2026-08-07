import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { Panel } from '@/components/admin/panel';
import { Button } from '@/components/ui/button';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { useStat } from '@/hooks/use-stat';
import type { ActivityOverview } from '@/types/dashboard';

/**
 * The three pillars, in the brand's emerald → blue → violet order.
 *
 * Fixed and never cycled: a series keeps its colour whatever else is on the
 * chart, so a reader who learns "violet is movement" once keeps it — here and
 * on the owner console, which draws the same three.
 */
const config = {
    meditation: { label: 'Meditation', color: 'var(--series-meditation)' },
    learning: { label: 'Learning', color: 'var(--series-learning)' },
    movement: { label: 'Movement', color: 'var(--series-movement)' },
} satisfies ChartConfig;

const series = ['meditation', 'learning', 'movement'] as const;

function shortDate(iso: string): string {
    return new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

/**
 * Meditation, learning and movement over time, across everybody.
 *
 * Stacked areas rather than three overlapping washes: the question staff ask of
 * this chart is "how much is happening and what is it made of", and stacking
 * answers both at once where overlaid curves answer neither well — three
 * translucent fills over each other produce colours that are in no legend.
 */
export function PillarsPanel({
    url,
    className,
}: {
    url: string;
    className?: string;
}) {
    const mix = useStat<ActivityOverview>(url);

    if (mix.error) {
        return (
            <Panel title="The three pillars" className={className}>
                <div className="flex h-full flex-col panel p-4">
                    <p className="text-caption text-destructive">{mix.error}</p>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={mix.reload}
                        className="mt-2 self-start"
                    >
                        Try again
                    </Button>
                </div>
            </Panel>
        );
    }

    return (
        <Panel
            title="The three pillars"
            hint="people logging each, per day"
            className={className}
        >
            <div className="h-full panel p-5">
                {mix.isLoading || !mix.data ? (
                    <Skeleton className="h-[232px] w-full" />
                ) : (
                    <ChartContainer
                        config={config}
                        className="h-[232px] w-full"
                    >
                        <AreaChart
                            data={mix.data.points}
                            margin={{ left: -20, right: 8, top: 4 }}
                        >
                            <CartesianGrid
                                vertical={false}
                                strokeOpacity={0.4}
                            />

                            <XAxis
                                dataKey="date"
                                tickLine={false}
                                axisLine={false}
                                tickMargin={8}
                                minTickGap={24}
                                tickFormatter={shortDate}
                            />

                            <YAxis
                                tickLine={false}
                                axisLine={false}
                                tickMargin={4}
                                width={48}
                                allowDecimals={false}
                            />

                            <ChartTooltip
                                content={
                                    <ChartTooltipContent
                                        labelFormatter={(value) =>
                                            shortDate(String(value))
                                        }
                                    />
                                }
                            />

                            {series.map((key) => (
                                <Area
                                    key={key}
                                    dataKey={key}
                                    type="monotone"
                                    stackId="pillars"
                                    stroke={config[key].color}
                                    fill={config[key].color}
                                    fillOpacity={0.18}
                                    strokeWidth={2}
                                />
                            ))}

                            <ChartLegend content={<ChartLegendContent />} />
                        </AreaChart>
                    </ChartContainer>
                )}
            </div>
        </Panel>
    );
}
