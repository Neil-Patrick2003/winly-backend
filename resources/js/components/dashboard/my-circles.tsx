import { Link } from '@inertiajs/react';
import { PanelFooterLink } from '@/components/dashboard/panel-header';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import {
    index as circlesIndex,
    members as circleMembers,
} from '@/routes/circles';
import type { MyCirclesPayload } from '@/types/dashboard';

type Props = {
    payload: MyCirclesPayload | null;
    isLoading: boolean;
    error: string | null;
    onRetry: () => void;
};

/**
 * The circles this person owns, largest first.
 */
export function MyCircles({ payload, isLoading, error, onRetry }: Props) {
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
                            <Skeleton className="size-8 shrink-0 rounded-md" />
                            <div className="min-w-0 flex-1 space-y-2">
                                <Skeleton className="h-3.5 w-32" />
                                <Skeleton className="h-3 w-16" />
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
                    Circles you start will be listed here.
                </p>
            ) : (
                <ul className="flex-1 py-1">
                    {payload.data.map((circle) => (
                        <li key={circle.id}>
                            <Link
                                href={circleMembers(circle.id)}
                                className="flex items-center gap-3 px-5 py-2 transition-colors hover:bg-accent/40"
                            >
                                <span
                                    className="flex size-9 shrink-0 items-center justify-center rounded-md text-sm font-semibold text-white"
                                    style={{
                                        backgroundColor: circle.color_hex,
                                    }}
                                    aria-hidden
                                >
                                    {circle.icon_initial}
                                </span>

                                <div className="min-w-0 flex-1">
                                    <span className="block truncate text-sm font-medium">
                                        {circle.name}
                                    </span>
                                    <span className="text-caption text-muted-foreground">
                                        {circle.members_count}{' '}
                                        {circle.members_count === 1
                                            ? 'member'
                                            : 'members'}
                                    </span>
                                </div>
                            </Link>
                        </li>
                    ))}
                </ul>
            )}

            {hasMore && (
                <PanelFooterLink href={circlesIndex()}>
                    See all {payload.total}
                </PanelFooterLink>
            )}
        </div>
    );
}
