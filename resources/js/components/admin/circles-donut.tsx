import { Link } from '@inertiajs/react';
import { Cell, Label, Pie, PieChart } from 'recharts';
import { Panel } from '@/components/admin/panel';
import { Button } from '@/components/ui/button';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { Skeleton } from '@/components/ui/skeleton';
import { useStat } from '@/hooks/use-stat';
import { circles as allCircles } from '@/routes/admin';

type CirclesStat = {
    value: number;
    active: number;
    quiet: number;
    ownerless: number;
};

/**
 * The three states a circle can be in, in fixed order.
 *
 * Mutually exclusive and adding up to the total, because a pie draws parts of
 * one whole: an ownerless circle counted again under "quiet" would push the
 * slices past 100% and make the chart a lie.
 *
 * Colours are validated against both surfaces — see `--series-*` in `app.css`.
 * "No owner" wears the attention hue, but never carries the meaning alone: it
 * is labelled in the legend and links to the filter that lists them.
 */
const config = {
    active: { label: 'Posted this week', color: 'var(--series-meditation)' },
    quiet: { label: 'Quiet', color: 'var(--series-movement)' },
    ownerless: { label: 'No owner', color: 'var(--series-attention)' },
} satisfies ChartConfig;

const slices = ['active', 'quiet', 'ownerless'] as const;

export function CirclesDonut({ url }: { url: string }) {
    const stat = useStat<CirclesStat>(url);

    if (stat.error) {
        return (
            <Panel title="Circles" hint="all time">
                <div className="flex h-full flex-col panel p-4">
                    <p className="text-caption text-destructive">
                        {stat.error}
                    </p>
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={stat.reload}
                        className="mt-2 self-start"
                    >
                        Try again
                    </Button>
                </div>
            </Panel>
        );
    }

    if (stat.isLoading || !stat.data) {
        return (
            <Panel title="Circles" hint="all time">
                <div className="flex h-full flex-col items-center justify-center panel p-5">
                    <Skeleton className="size-[168px] rounded-full" />
                </div>
            </Panel>
        );
    }

    const data = slices.map((key) => ({
        key,
        label: config[key].label,
        value: stat.data![key],
        fill: config[key].color,
    }));

    const total = stat.data.value;

    return (
        <Panel title="Circles" hint="all time">
            <div className="flex h-full flex-col panel">
                <ChartContainer
                    config={config}
                    className="mx-auto aspect-square max-h-[188px] w-full"
                >
                    <PieChart>
                        <ChartTooltip
                            cursor={false}
                            content={<ChartTooltipContent hideLabel />}
                        />
                        <Pie
                            data={data}
                            dataKey="value"
                            nameKey="label"
                            innerRadius={54}
                            outerRadius={82}
                            // A hairline of surface between slices, so two
                            // neighbouring colours never touch and blend.
                            paddingAngle={2}
                            strokeWidth={2}
                        >
                            {data.map((slice) => (
                                <Cell key={slice.key} fill={slice.fill} />
                            ))}

                            <Label
                                content={({ viewBox }) =>
                                    viewBox && 'cx' in viewBox ? (
                                        <text
                                            x={viewBox.cx}
                                            y={viewBox.cy}
                                            textAnchor="middle"
                                            dominantBaseline="middle"
                                        >
                                            <tspan
                                                x={viewBox.cx}
                                                y={viewBox.cy}
                                                className="fill-foreground text-xl font-semibold tabular-nums"
                                            >
                                                {total}
                                            </tspan>
                                            <tspan
                                                x={viewBox.cx}
                                                y={(viewBox.cy ?? 0) + 18}
                                                className="fill-muted-foreground text-[11px]"
                                            >
                                                circles
                                            </tspan>
                                        </text>
                                    ) : null
                                }
                            />
                        </Pie>
                    </PieChart>
                </ChartContainer>

                {/*
                 * A legend that carries the figures too, so identity is never
                 * colour alone and the exact numbers do not depend on hovering.
                 */}
                <ul className="space-y-1.5 px-5 pt-1 pb-5">
                    {data.map((slice) => (
                        <li
                            key={slice.key}
                            className="flex items-center gap-2 text-caption"
                        >
                            <span
                                className="size-2 shrink-0 rounded-full"
                                style={{ backgroundColor: slice.fill }}
                                aria-hidden
                            />
                            <span className="flex-1 text-muted-foreground">
                                {slice.key === 'ownerless' &&
                                slice.value > 0 ? (
                                    <Link
                                        href={allCircles({
                                            query: {
                                                filter: { state: 'ownerless' },
                                            },
                                        })}
                                        prefetch
                                        className="underline underline-offset-2 hover:text-foreground"
                                    >
                                        {slice.label}
                                    </Link>
                                ) : (
                                    slice.label
                                )}
                            </span>
                            <span className="font-medium tabular-nums">
                                {slice.value}
                            </span>
                        </li>
                    ))}
                </ul>

                <table className="sr-only">
                    <caption>Circles by state</caption>
                    <tbody>
                        {data.map((slice) => (
                            <tr key={slice.key}>
                                <th scope="row">{slice.label}</th>
                                <td>{slice.value}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Panel>
    );
}
