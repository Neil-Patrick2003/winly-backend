import { ArrowDownRight, ArrowUpRight } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

const formats = {
    whole: new Intl.NumberFormat(undefined, { maximumFractionDigits: 0 }),
    decimal: new Intl.NumberFormat(undefined, {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }),
};

type Props = {
    label: string;
    icon: LucideIcon;
    value: number | null;
    /** How to render the number. */
    format?: keyof typeof formats;
    suffix?: string;
    change?: number | null;
    /**
     * What the change is expressed in. A rate moves in percentage points, not
     * percent — "engagement up 4%" and "up 4pp" are different claims.
     */
    changeUnit?: '%' | 'pp';
    isLoading: boolean;
    error: string | null;
    onRetry: () => void;
};

/**
 * One figure on the console: a label and a number, and nothing else.
 *
 * The explanatory line under each figure came out — four tiles each carrying a
 * sentence read as a paragraph broken into boxes, and the number is the thing
 * the row exists to show. What the figure is measured over belongs in the
 * panels below, which have the room to say it once.
 */
export function StatTile({
    label,
    icon: Icon,
    value,
    format = 'whole',
    suffix,
    change,
    changeUnit = '%',
    isLoading,
    error,
    onRetry,
}: Props) {
    return (
        <div className="panel px-4 py-3.5">
            <div className="flex items-center justify-between gap-2">
                <span className="text-caption font-medium text-muted-foreground">
                    {label}
                </span>
                <Icon
                    className="size-3.5 shrink-0 text-muted-foreground"
                    aria-hidden
                />
            </div>

            {error ? (
                <Button
                    size="sm"
                    variant="outline"
                    onClick={onRetry}
                    className="mt-2"
                >
                    Try again
                </Button>
            ) : isLoading || value === null ? (
                <Skeleton className="mt-2 h-7 w-16" />
            ) : (
                <div className="mt-1.5 flex items-baseline gap-2">
                    <span className="font-display text-2xl leading-none font-semibold tabular-nums">
                        {formats[format].format(value)}
                        {suffix}
                    </span>
                    {change !== null && change !== undefined && (
                        <ChangeBadge change={change} unit={changeUnit} />
                    )}
                </div>
            )}
        </div>
    );
}

/**
 * Movement against the previous window.
 *
 * Flat is its own case: an arrow either way on a zero would read as a direction
 * the number did not move in.
 */
function ChangeBadge({ change, unit }: { change: number; unit: string }) {
    const isFlat = change === 0;
    const isUp = change > 0;
    const Arrow = isUp ? ArrowUpRight : ArrowDownRight;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-0.5 text-caption font-medium tabular-nums',
                isFlat && 'text-muted-foreground',
                !isFlat && isUp && 'text-primary',
                !isFlat && !isUp && 'text-destructive',
            )}
        >
            {!isFlat && <Arrow className="size-3" aria-hidden />}
            {isUp && '+'}
            {change}
            {unit}
        </span>
    );
}
