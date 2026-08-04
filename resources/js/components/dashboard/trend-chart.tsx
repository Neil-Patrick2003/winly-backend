import { useCallback, useId, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';

const PLOT_HEIGHT = 148;
const PADDING = { top: 14, right: 10, bottom: 22, left: 10 };

/**
 * How far a control point reaches toward its neighbour. Barely more than
 * straight — at any real tension the curve bulges past both readings and
 * invents a value neither day had.
 */
const TENSION = 0.07;

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

        path += ` C ${start[0] + (end[0] - previous[0]) * TENSION},${
            start[1] + (end[1] - previous[1]) * TENSION
        } ${end[0] - (next[0] - start[0]) * TENSION},${
            end[1] - (next[1] - start[1]) * TENSION
        } ${end[0]},${end[1]}`;
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

export type TrendPoint = { date: string; value: number };

type Props = {
    points: TrendPoint[] | null;
    /** Names the series, so no legend box is needed for the single line. */
    label: string;
    color: string;
    isLoading: boolean;
    error: string | null;
    onRetry: () => void;
};

/**
 * One measure over time.
 *
 * Deliberately a separate chart per measure rather than two lines on shared
 * axes: signups arrive a handful a day where posts arrive in dozens, and the
 * only way to draw both on one plot is a second vertical scale — which lets any
 * two lines be made to cross wherever the author likes. Two plots stacked on
 * the same dates say the same thing and cannot mislead.
 *
 * No legend: with one series the heading already names it, and a legend box for
 * a single colour is furniture. The running total sits beside the title instead,
 * which is the figure people actually read.
 */
export function TrendChart({
    points,
    label,
    color,
    isLoading,
    error,
    onRetry,
}: Props) {
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
                    <Skeleton className="h-[148px] w-full" />
                </div>
            </div>
        );
    }

    const plotWidth = Math.max(0, width - PADDING.left - PADDING.right);
    const innerHeight = PLOT_HEIGHT - PADDING.top - PADDING.bottom;
    const highest = Math.max(1, ...points.map((point) => point.value));
    const total = points.reduce((sum, point) => sum + point.value, 0);

    const xFor = (index: number) =>
        PADDING.left +
        (points.length <= 1
            ? plotWidth / 2
            : (index / (points.length - 1)) * plotWidth);

    const yFor = (value: number) =>
        PADDING.top + innerHeight - (value / highest) * innerHeight;

    const baseline = PADDING.top + innerHeight;
    const active = hovered === null ? null : points[hovered];

    const line = points.map((point, index): Point => [
        xFor(index),
        yFor(point.value),
    ]);
    const curve = curveThrough(line);

    return (
        <div className="flex h-full flex-col panel">
            <div className="flex items-baseline justify-between gap-3 px-5 pt-5">
                <span className="flex items-center gap-1.5 text-caption text-muted-foreground">
                    <span
                        className="size-2 shrink-0 rounded-full"
                        style={{ backgroundColor: color }}
                        aria-hidden
                    />
                    {label}
                </span>
                <span className="text-card-title font-semibold tabular-nums">
                    {total.toLocaleString()}
                </span>
            </div>

            <div ref={ref} className="relative px-5 pb-4">
                {width > 0 && (
                    <svg
                        width={width}
                        height={PLOT_HEIGHT}
                        role="img"
                        aria-label={`${label} per day`}
                        onMouseLeave={clearHover}
                        onMouseMove={(event) => {
                            const bounds =
                                event.currentTarget.getBoundingClientRect();
                            const ratio =
                                (event.clientX - bounds.left - PADDING.left) /
                                (plotWidth || 1);

                            setHovered(
                                Math.min(
                                    points.length - 1,
                                    Math.max(
                                        0,
                                        Math.round(ratio * (points.length - 1)),
                                    ),
                                ),
                            );
                        }}
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
                                    stopColor={color}
                                    stopOpacity={0.16}
                                />
                                <stop
                                    offset="100%"
                                    stopColor={color}
                                    stopOpacity={0}
                                />
                            </linearGradient>
                        </defs>

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

                        <path
                            d={`${curve} L ${line[line.length - 1]?.[0] ?? 0},${baseline} L ${line[0]?.[0] ?? 0},${baseline} Z`}
                            fill={`url(#${gradientId})`}
                        />
                        <path
                            d={curve}
                            fill="none"
                            stroke={color}
                            strokeWidth={2}
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />

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
                                <circle
                                    cx={xFor(hovered)}
                                    cy={yFor(active.value)}
                                    r={4}
                                    fill={color}
                                    className="stroke-card"
                                    strokeWidth={2}
                                />
                            </>
                        )}
                    </svg>
                )}

                {active && hovered !== null && (
                    <div
                        className="pointer-events-none absolute top-0 z-10 min-w-28 rounded-md bg-popover p-2 shadow-raised"
                        style={{
                            left:
                                16 +
                                Math.min(
                                    Math.max(xFor(hovered) - 48, 0),
                                    Math.max(width - 112, 0),
                                ),
                        }}
                    >
                        <p className="mb-1 text-[11px] font-medium text-popover-foreground">
                            {shortDate(active.date)}
                        </p>
                        <p className="flex items-center gap-1.5 text-[11px] text-muted-foreground">
                            <span
                                className="size-1.5 shrink-0 rounded-full"
                                style={{ backgroundColor: color }}
                                aria-hidden
                            />
                            <span className="flex-1">{label}</span>
                            <span className="font-medium text-popover-foreground tabular-nums">
                                {active.value}
                            </span>
                        </p>
                    </div>
                )}
            </div>

            <table className="sr-only">
                <caption>{label} per day</caption>
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">{label}</th>
                    </tr>
                </thead>
                <tbody>
                    {points.map((point) => (
                        <tr key={point.date}>
                            <th scope="row">{shortDate(point.date)}</th>
                            <td>{point.value}</td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}
