import type { LucideIcon } from 'lucide-react';

export type Metric = {
    label: string;
    value: number | string;
    unit?: string;
    icon: LucideIcon;
};

/**
 * A single bordered strip of key figures, divided rather than boxed.
 *
 * One surface instead of three keeps the page reading as a document, and the
 * numbers stay comparable because they share a baseline.
 */
export default function MetricStrip({ metrics }: { metrics: Metric[] }) {
    return (
        <dl className="grid divide-y divide-border overflow-hidden rounded-card border border-border bg-card shadow-card sm:grid-cols-3 sm:divide-x sm:divide-y-0">
            {metrics.map(({ label, value, unit, icon: Icon }) => (
                <div key={label} className="flex items-center gap-3 px-4 py-3">
                    <Icon className="size-4 shrink-0 text-muted-foreground" />

                    <div className="min-w-0">
                        <dt className="text-[11px] font-medium tracking-[0.06em] text-muted-foreground uppercase">
                            {label}
                        </dt>

                        <dd className="font-numeric text-[17px] leading-tight font-bold tabular-nums">
                            {value}
                            {unit && (
                                <span className="ml-1 text-[12px] font-medium text-muted-foreground">
                                    {unit}
                                </span>
                            )}
                        </dd>
                    </div>
                </div>
            ))}
        </dl>
    );
}
