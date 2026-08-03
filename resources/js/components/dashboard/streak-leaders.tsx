import { Flame } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { UserAvatar } from '@/components/user-avatar';
import { cn } from '@/lib/utils';
import type { StreakLeaderboard } from '@/types/dashboard';

type Props = {
    board: StreakLeaderboard | null;
    isLoading: boolean;
    error: string | null;
    onRetry: () => void;
};

/**
 * Who in the owner's circles is on the longest run.
 *
 * A run with nothing logged today is called out rather than left to the number:
 * that is the one still worth a nudge.
 */
export function StreakLeaders({ board, isLoading, error, onRetry }: Props) {
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

    if (isLoading || board === null) {
        return (
            <div className="flex h-full flex-col panel">
                <div className="space-y-4 p-5">
                    {Array.from({ length: 4 }, (_, row) => (
                        <div key={row} className="flex items-center gap-3">
                            <Skeleton className="size-8 shrink-0 rounded-full" />
                            <Skeleton className="h-3.5 flex-1" />
                            <Skeleton className="h-3.5 w-10" />
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div className="flex h-full flex-col panel">
            {board.data.length === 0 ? (
                <p className="flex-1 p-5 text-caption text-muted-foreground">
                    A streak starts the second day someone logs a win in a row.
                </p>
            ) : (
                <ul className="flex-1 py-2">
                    {board.data.map((leader, rank) => (
                        <li
                            key={leader.id}
                            className="flex items-center gap-3 px-5 py-2.5"
                        >
                            <span className="w-3 shrink-0 text-caption font-medium text-muted-foreground tabular-nums">
                                {rank + 1}
                            </span>

                            <UserAvatar
                                name={leader.full_name}
                                src={leader.avatar_url}
                            />

                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {leader.full_name}
                                </p>
                                {!leader.logged_today && (
                                    <p className="text-caption text-muted-foreground">
                                        Nothing today
                                    </p>
                                )}
                            </div>

                            <span
                                className={cn(
                                    'flex shrink-0 items-center gap-1 text-sm font-semibold tabular-nums',
                                    leader.logged_today
                                        ? 'text-primary'
                                        : 'text-muted-foreground',
                                )}
                            >
                                <Flame className="size-3.5" aria-hidden />
                                {leader.streak_days}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
