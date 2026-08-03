import { PanelFooterLink } from '@/components/dashboard/panel-header';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { UserAvatar } from '@/components/user-avatar';
import { index as circlesIndex } from '@/routes/circles';
import type { ActivityFeedPayload } from '@/types/dashboard';

const relativeTime = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

/**
 * Thresholds walked largest-first to pick the coarsest unit that still fits.
 */
const units: Array<[Intl.RelativeTimeFormatUnit, number]> = [
    ['day', 86_400],
    ['hour', 3_600],
    ['minute', 60],
];

/**
 * How long ago something happened, in the coarsest unit that still reads true.
 */
function ago(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const seconds = (Date.parse(iso) - Date.now()) / 1000;

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return relativeTime.format(Math.round(seconds / size), unit);
        }
    }

    return 'just now';
}

type Props = {
    payload: ActivityFeedPayload | null;
    isLoading: boolean;
    error: string | null;
    onRetry: () => void;
};

/**
 * The most recent wins shared into the owner's circles.
 */
export function ActivityFeed({ payload, isLoading, error, onRetry }: Props) {
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

    if (isLoading || payload === null) {
        return (
            <div className="flex h-full flex-col panel">
                <div className="space-y-3 p-4">
                    {Array.from({ length: 2 }, (_, row) => (
                        <div key={row} className="flex items-center gap-3">
                            <Skeleton className="size-8 shrink-0 rounded-full" />
                            <div className="min-w-0 flex-1 space-y-2">
                                <Skeleton className="h-3.5 w-40" />
                                <Skeleton className="h-3 w-24" />
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    const hasMore = payload.total > payload.data.length;

    return (
        <div className="flex h-full flex-col panel">
            {payload.data.length === 0 ? (
                <p className="flex-1 p-5 text-caption text-muted-foreground">
                    Wins shared into your circles show up here as they land.
                </p>
            ) : (
                <ul className="flex-1 py-1">
                    {payload.data.map((entry) => (
                        <li
                            key={entry.id}
                            className="flex items-start gap-3 px-5 py-2"
                        >
                            <UserAvatar
                                name={entry.user.full_name}
                                src={entry.user.avatar_url}
                            />

                            <div className="min-w-0 flex-1">
                                <div className="flex items-baseline justify-between gap-2">
                                    <span className="truncate text-sm font-medium">
                                        {entry.user.full_name}
                                    </span>
                                    <span className="shrink-0 text-caption text-muted-foreground">
                                        {ago(entry.created_at)}
                                    </span>
                                </div>

                                <p className="truncate text-caption text-muted-foreground">
                                    {entry.wins.join(' · ') || 'Shared a win'}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {hasMore && (
                <PanelFooterLink href={circlesIndex()}>See all</PanelFooterLink>
            )}
        </div>
    );
}
