import { useCallback, useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import type { ActivityPoint, WinSeries } from '@/types/dashboard';

/**
 * The three series, in the brand's emerald → blue → violet order.
 *
 * Order is fixed and never cycled: a series keeps its colour whatever else is
 * on the chart, so a reader who learns "violet is movement" once keeps it.
 */
const SERIES: Array<{ key: WinSeries; label: string; color: string }> = [
    {
        key: 'meditation',
        label: 'Meditation',
        color: 'var(--series-meditation)',
    },
    { key: 'learning', label: 'Learning', color: 'var(--series-learning)' },
    { key: 'movement', label: 'Movement', color: 'var(--series-movement)' },
];

const PLOT_HEIGHT = 176;
const PADDING = { top: 14, right: 10, bottom: 22, left: 10 };

/**
 * How far a control point reaches toward its neighbour.
 *
 * Barely more than straight. Enough to take the hard corner off a peak, not
 * enough to bow the run between two days — at any real tension the curve
 * bulges past both readings and invents a value neither day had.
 */
const TENSION = 0.07;

/**
 * The container's width in pixels, so the plot is drawn at its true size.
 *
 * A callback ref rather than a mount effect: the plot does not exist on the
 * first render — the skeleton is standing in its place — so an effect with an
 * empty dependency list would look for the node, find nothing, and never run
 * again once the real one arrived.
 */
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

/**
 * A Catmull-Rom curve through every point, written as cubic béziers.
 *
 * The curve passes through the data rather than near it, so a peak still reads
 * at the value it was.
 */
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
    points: ActivityPoint[] | null;
    isLoading: boolean;
    error: string | null;
    onRetry: () => void;
};

/**
 * Wins per day, one curve per kind.
 *
 * Hand-drawn SVG rather than a charting dependency: three curves and a
 * crosshair do not justify shipping a library, and the series colours stay in
 * the theme where dark mode can restate them.
 *
 * No gridlines. With three curves and a wash under each, ruled lines behind
 * them are a fourth thing competing for the same space — the tooltip carries
 * the exact figures, so the plot only has to carry the shape.
 */
export function ActivityChart({ points, isLoading, error, onRetry }: Props) {
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

    if (isLoading || points === null) {
        return (
            <div className="flex h-full flex-col panel">
                <div className="p-5">
                    <Skeleton className="h-[176px] w-full" />
                </div>
            </div>
        );
    }

    const plotWidth = Math.max(0, width - PADDING.left - PADDING.right);
    const innerHeight = PLOT_HEIGHT - PADDING.top - PADDING.bottom;
    const highest = Math.max(
        1,
        ...points.flatMap((point) => SERIES.map(({ key }) => point[key])),
    );

    const xFor = (index: number) =>
        PADDING.left +
        (points.length <= 1
            ? plotWidth / 2
            : (index / (points.length - 1)) * plotWidth);

    const yFor = (value: number) =>
        PADDING.top + innerHeight - (value / highest) * innerHeight;

    const baseline = PADDING.top + innerHeight;

    const nearestIndex = (clientX: number, bounds: DOMRect) => {
        const ratio = (clientX - bounds.left - PADDING.left) / (plotWidth || 1);

        return Math.min(
            points.length - 1,
            Math.max(0, Math.round(ratio * (points.length - 1))),
        );
    };

    const active = hovered === null ? null : points[hovered];

    return (
        <div className="flex h-full flex-col panel">
            <ul className="flex flex-wrap gap-x-4 gap-y-1 px-5 pt-5 pb-3">
                {SERIES.map(({ key, label, color }) => (
                    <li
                        key={key}
                        className="flex items-center gap-1.5 text-caption text-muted-foreground"
                    >
                        <span
                            className="size-2 shrink-0 rounded-full"
                            style={{ backgroundColor: color }}
                            aria-hidden
                        />
                        {label}
                    </li>
                ))}
            </ul>

            <div ref={ref} className="relative px-5 pb-4">
                {width > 0 && (
                    <svg
                        width={width}
                        height={PLOT_HEIGHT}
                        role="img"
                        aria-label="Wins per day by kind"
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
                            {SERIES.map(({ key, color }) => (
                                <linearGradient
                                    key={key}
                                    id={`${gradientId}-${key}`}
                                    x1="0"
                                    y1="0"
                                    x2="0"
                                    y2="1"
                                >
                                    <stop
                                        offset="0%"
                                        stopColor={color}
                                        stopOpacity={0.14}
                                    />
                                    <stop
                                        offset="100%"
                                        stopColor={color}
                                        stopOpacity={0}
                                    />
                                </linearGradient>
                            ))}
                        </defs>

                        {/*
                         * Every day gets a tick. Day-of-month only, because a
                         * full date on each would collide long before the
                         * fortnight is out; the tooltip carries the month.
                         */}
                        {points.map((point, index) => (
                            <text
                                key={point.date}
                                x={xFor(index)}
                                y={PLOT_HEIGHT - 6}
                                textAnchor="middle"
                                className="fill-muted-foreground text-[9px] tabular-nums"
                            >
                                {dayOfMonth(point.date)}
                            </text>
                        ))}

                        {SERIES.map(({ key, color }) => {
                            const line = points.map((point, index): Point => [
                                xFor(index),
                                yFor(point[key]),
                            ]);

                            const curve = curveThrough(line);
                            const lastX = line[line.length - 1]?.[0] ?? 0;

                            return (
                                <g key={key}>
                                    <path
                                        d={`${curve} L ${lastX},${baseline} L ${line[0]?.[0] ?? 0},${baseline} Z`}
                                        fill={`url(#${gradientId}-${key})`}
                                    />
                                    <path
                                        d={curve}
                                        fill="none"
                                        stroke={color}
                                        strokeWidth={2}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                    />
                                </g>
                            );
                        })}

                        {active && hovered !== null && (
                            <>
                                <line
                                    x1={xFor(hovered)}
                                    x2={xFor(hovered)}
                                    y1={PADDING.top}
                                    y2={baseline}
                                    className="stroke-muted-foreground/30"
                                    strokeWidth={1}
                                />
                                {SERIES.map(({ key, color }) => (
                                    <circle
                                        key={key}
                                        cx={xFor(hovered)}
                                        cy={yFor(active[key])}
                                        r={4}
                                        fill={color}
                                        className="stroke-card"
                                        strokeWidth={2}
                                    />
                                ))}
                            </>
                        )}
                    </svg>
                )}

                {active && hovered !== null && (
                    <div
                        className="pointer-events-none absolute top-0 z-10 min-w-32 rounded-md bg-popover p-2 shadow-raised"
                        style={{
                            left:
                                16 +
                                Math.min(
                                    Math.max(xFor(hovered) - 56, 0),
                                    Math.max(width - 128, 0),
                                ),
                        }}
                    >
                        <p className="mb-1 text-[11px] font-medium text-popover-foreground">
                            {shortDate(active.date)}
                        </p>
                        <ul className="space-y-0.5">
                            {SERIES.map(({ key, label, color }) => (
                                <li
                                    key={key}
                                    className="flex items-center gap-1.5 text-[11px] text-muted-foreground"
                                >
                                    <span
                                        className="size-1.5 shrink-0 rounded-full"
                                        style={{ backgroundColor: color }}
                                        aria-hidden
                                    />
                                    <span className="flex-1">{label}</span>
                                    <span className="font-medium text-popover-foreground tabular-nums">
                                        {active[key]}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>

            <table className="sr-only">
                <caption>Wins per day by kind</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        {SERIES.map(({ key, label }) => (
                            <th key={key} scope="col">
                                {label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {points.map((point) => (
                        <tr key={point.date}>
                            <th scope="row">{shortDate(point.date)}</th>
                            {SERIES.map(({ key }) => (
                                <td key={key}>{point[key]}</td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
