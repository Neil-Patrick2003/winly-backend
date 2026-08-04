import type { LucideIcon } from 'lucide-react';
import { StatTile } from '@/components/dashboard/stat-tile';
import { useStat } from '@/hooks/use-stat';

/** Every stat endpoint answers at least this much. */
type Stat = {
    value: number;
    change?: number | null;
    [key: string]: unknown;
};

type Props = {
    label: string;
    icon: LucideIcon;
    /** The endpoint this one figure comes from. */
    url: string;
    format?: 'whole' | 'decimal';
    suffix?: string;
    changeUnit?: '%' | 'pp';
};

/**
 * One figure, fetching itself.
 *
 * Each card owns its request rather than the page handing out slices of a
 * shared payload: a slow aggregate then holds up its own number instead of the
 * whole console, and a failure shows in the tile it belongs to with its own
 * retry. It also means adding a statistic is one line on the page and one
 * endpoint behind it, with nothing in between to keep in step.
 */
export function StatCard({
    label,
    icon,
    url,
    format,
    suffix,
    changeUnit,
}: Props) {
    const stat = useStat<Stat>(url);

    return (
        <StatTile
            label={label}
            icon={icon}
            value={stat.data?.value ?? null}
            format={format}
            suffix={suffix}
            change={stat.data?.change}
            changeUnit={changeUnit}
            isLoading={stat.isLoading}
            error={stat.error}
            onRetry={stat.reload}
        />
    );
}
