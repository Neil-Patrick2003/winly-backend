import { Ban, CircleSlash, Clock, UserCheck } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useCallback, useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { JoinPoint, MemberOverview as Overview } from '@/types/dashboard';

const PLOT_HEIGHT = 132;
const PADDING = { top: 12, right: 8, bottom: 20, left: 8 };

/** Matched to the activity chart, where the same reasoning applies. */
const TENSION = 0.07;

/**
 * The standing counts, in the order somebody works through them: who is in,
 * who has been asked, who said no, who is barred.
 */
const STATUSES: Array<{
    key: keyof Overview['statuses'];
    label: string;
    icon: LucideIcon;
    tone: string;
}> = [
    {
        key: 'accepted',
        label: 'Accepted',
        icon: UserCheck,
        tone: 'text-primary',
    },
    { key: 'pending', label: 'Pending', icon: Clock, tone: 'text-foreground' },
    {
        key: 'declined',
        label: 'Declined',
        icon: CircleSlash,
        tone: 'text-muted-foreground',
    },
    { key: 'blocked', label: 'Blocked', icon: Ban, tone: 'text-destructive' },
];

function useElementWidth() {
    const [width, setWidth] = useState(0);
    const observer = useRef<ResizeObserver | null>(null);

    const ref = useCallback((node: HTMLDivElement | null) => {
        observer.current?.disconnect();

        if (!node) {
            return;
        }

        observer.current = new ResizeObserver(([entry]) =>
            setWidth(entry.contentRect.width),
        );

        observer.current.observe(node);
    }, []);

    return [ref, width] as const;
}

type Point = [x: number, y: number];

function curveThrough(points: Point[]): string {
    if (points.length === 0) {
        return '';
    }

    let path = `M ${points[0][0]},${points[0][1]}`;

    for (let i = 0; i < points.length - 1; i++) {
        const previous = points[i - 1] ?? points[i];
        const start = points[i];
        const end = points[i + 1];
        const next = points[i + 2] ?? end;

        const c1x = start[0] + (end[0] - previous[0]) * TENSION;
        const c1y = start[1] + (end[1] - previous[1]) * TENSION;
        const c2x = end[0] - (next[0] - start[0]) * TENSION;
        const c2y = end[1] - (next[1] - start[1]) * TENSION;

        path += ` C ${c1x},${c1y} ${c2x},${c2y} ${end[0]},${end[1]}`;
    }

    return path;
}

function shortDate(iso: string): string {
    return new Date(`${iso}T00:00:00`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
}

function dayOfMonth(iso: string): number {
    return new Date(`${iso}T00:00:00`).getDate();
}

type Props = {
    overview: Overview | null;
    isLoading: boolean;
    error: string | null;
    onRetry: () => void;
};

/**
 * How the circles filled up, and where everyone around them stands.
 *
 * One series, so no legend — the panel title names it.
 */
export function MemberOverview({ overview, isLoading, error, onRetry }: Props) {
    const [ref, width] = useElementWidth();
    const [hovered, setHovered] = useState<number | null>(null);
    const gradientId = useId();

    const clearHover = useCallback(() => setHovered(null), []);

    if (error) {
        return (
            <div className="flex h-full flex-col panel p-4">
                <p className="text-caption text-destructive">{error}</p>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={onRetry}
                    className="mt-2 self-start"
                >
                    Try again
                </Button>
            </div>
        );
    }

    if (isLoading || overview === null) {
        return (
            <div className="flex h-full flex-col panel p-4">
                <div className="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {STATUSES.map(({ key }) => (
                        <Skeleton key={key} className="h-12" />
                    ))}
                </div>
                <Skeleton className="h-[132px] w-full" />
            </div>
        );
    }

    const points: JoinPoint[] = overview.points;
    const plotWidth = Math.max(0, width - PADDING.left - PADDING.right);
    const innerHeight = PLOT_HEIGHT - PADDING.top - PADDING.bottom;
    const highest = Math.max(1, ...points.map((point) => point.joined));

    const xFor = (index: number) =>
        PADDING.left +
        (points.length <= 1
            ? plotWidth / 2
            : (index / (points.length - 1)) * plotWidth);

    const yFor = (value: number) =>
        PADDING.top + innerHeight - (value / highest) * innerHeight;

    const baseline = PADDING.top + innerHeight;

    const line = points.map((point, index): Point => [
        xFor(index),
        yFor(point.joined),
    ]);

    const curve = curveThrough(line);

    const nearestIndex = (clientX: number, bounds: DOMRect) => {
        const ratio = (clientX - bounds.left - PADDING.left) / (plotWidth || 1);

        return Math.min(
            points.length - 1,
            Math.max(0, Math.round(ratio * (points.length - 1))),
        );
    };

    const active = hovered === null ? null : points[hovered];

    return (
        <div className="flex h-full flex-col panel p-4">
            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {STATUSES.map(({ key, label, icon: Icon, tone }) => (
                    <div key={key} className="rounded-md bg-muted/50 px-3 py-2">
                        <dt className="flex items-center gap-1.5 text-caption text-muted-foreground">
                            <Icon className="size-3.5 shrink-0" aria-hidden />
                            {label}
                        </dt>
                        <dd
                            className={cn(
                                'mt-0.5 font-display text-lg leading-none font-semibold tabular-nums',
                                tone,
                            )}
                        >
                            {overview.statuses[key]}
                        </dd>
                    </div>
                ))}
            </dl>

            <p className="mt-4 mb-1 text-caption text-muted-foreground">
                Members joined per day
            </p>

            <div ref={ref} className="relative">
                {width > 0 && (
                    <svg
                        width={width}
                        height={PLOT_HEIGHT}
                        role="img"
                        aria-label="Members joined per day"
                        onMouseLeave={clearHover}
                        onMouseMove={(event) =>
                            setHovered(
                                nearestIndex(
                                    event.clientX,
                                    event.currentTarget.getBoundingClientRect(),
                                ),
                            )
                        }
                    >
                        <defs>
                            <linearGradient
                                id={gradientId}
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop
                                    offset="0%"
                                    stopColor="var(--series-learning)"
                                    stopOpacity={0.16}
                                />
                                <stop
                                    offset="100%"
                                    stopColor="var(--series-learning)"
                                    stopOpacity={0}
                                />
                            </linearGradient>
                        </defs>

                        {points.map((point, index) => (
                            <text
                                key={point.date}
                                x={xFor(index)}
                                y={PLOT_HEIGHT - 5}
                                textAnchor="middle"
                                className="fill-muted-foreground text-[9px] tabular-nums"
                            >
                                {dayOfMonth(point.date)}
                            </text>
                        ))}

                        <path
                            d={`${curve} L ${line[line.length - 1]?.[0] ?? 0},${baseline} L ${line[0]?.[0] ?? 0},${baseline} Z`}
                            fill={`url(#${gradientId})`}
                        />
                        <path
                            d={curve}
                            fill="none"
                            stroke="var(--series-learning)"
                            strokeWidth={2}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />

                        {active && hovered !== null && (
                            <circle
                                cx={xFor(hovered)}
                                cy={yFor(active.joined)}
                                r={4}
                                fill="var(--series-learning)"
                                className="stroke-card"
                                strokeWidth={2}
                            />
                        )}
                    </svg>
                )}

                {active && hovered !== null && (
                    <div
                        className="pointer-events-none absolute top-0 z-10 rounded-md bg-popover px-2.5 py-1.5 shadow-raised"
                        style={{
                            left: Math.min(
                                Math.max(xFor(hovered) - 44, 0),
                                Math.max(width - 96, 0),
                            ),
                        }}
                    >
                        <p className="text-[11px] text-muted-foreground">
                            {shortDate(active.date)}
                        </p>
                        <p className="text-[11px] font-medium text-popover-foreground tabular-nums">
                            {active.joined}{' '}
                            {active.joined === 1 ? 'joined' : 'joined'}
                        </p>
                    </div>
                )}
            </div>

            <table className="sr-only">
                <caption>Members joined per day</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Joined</th>
                    </tr>
                </thead>
                <tbody>
                    {points.map((point) => (
                        <tr key={point.date}>
                            <th scope="row">{shortDate(point.date)}</th>
                            <td>{point.joined}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
