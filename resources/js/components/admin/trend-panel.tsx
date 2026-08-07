import { Panel } from '@/components/admin/panel';
import { TrendChart } from '@/components/dashboard/trend-chart';
import type { TrendPoint } from '@/components/dashboard/trend-chart';
import { useStat } from '@/hooks/use-stat';

type Series = { points: TrendPoint[] };

/**
 * One measure over time, fetching itself.
 *
 * Each series has its own endpoint for the same reason each has its own plot:
 * they are different quantities at different scales, and neither the request
 * nor the axes should be shared.
 */
export function TrendPanel({
    title,
    hint,
    label,
    color,
    url,
}: {
    title: string;
    hint?: string;
    label: string;
    color: string;
    url: string;
}) {
    const series = useStat<Series>(url);

    return (
        <Panel title={title} hint={hint}>
            <TrendChart
                points={series.data?.points ?? null}
                label={label}
                color={color}
                isLoading={series.isLoading}
                error={series.error}
                onRetry={series.reload}
            />
        </Panel>
    );
}
