import { Flame } from 'lucide-react';
import { winTypeMeta } from '@/components/win-type-badge';
import { cn } from '@/lib/utils';
import type { WinType } from '@/types';

const PILLARS: WinType[] = ['meditation', 'learning', 'movement'];

/** A circle mid-week: who showed up, for what, and how long they have kept it. */
const MEMBERS: {
    initials: string;
    name: string;
    streak: number;
    counts: Record<WinType, number>;
}[] = [
    {
        initials: 'AR',
        name: 'Ana Reyes',
        streak: 21,
        counts: { meditation: 7, learning: 6, movement: 7 },
    },
    {
        initials: 'MC',
        name: 'Maya Cruz',
        streak: 12,
        counts: { meditation: 5, learning: 4, movement: 6 },
    },
    {
        initials: 'JD',
        name: 'Jon Diaz',
        streak: 7,
        counts: { meditation: 3, learning: 2, movement: 5 },
    },
];

/**
 * A circle's tracker, shown to people who have not signed in yet.
 *
 * The point of the screen it stands for: your own streak is private business,
 * but a circle's is shared — you can see who is carrying the week and who has
 * gone quiet, which is the whole reason the energy travels.
 *
 * Composed from theme tokens rather than captured as a screenshot, so the
 * pillars keep their hues and the whole thing follows light and dark.
 * Decorative, and hidden from screen readers: the prose beside it says the
 * same thing.
 */
export function CircleTrackerPreview({ className }: { className?: string }) {
    return (
        <div
            aria-hidden
            className={cn(
                'w-full max-w-sm overflow-hidden rounded-sheet border border-border bg-card shadow-raised select-none',
                className,
            )}
        >
            <div className="flex items-center justify-between gap-2 border-b border-border bg-wash-mint px-4 py-2.5">
                <span className="font-display text-card-title font-semibold text-wash-mint-ink">
                    Morning Movers
                </span>
                <span className="text-label tracking-wide text-wash-mint-ink/70 uppercase">
                    Last 7 days
                </span>
            </div>

            <ul className="divide-y divide-border">
                {MEMBERS.map(({ initials, name, streak, counts }) => (
                    <li
                        key={initials}
                        className="flex items-center gap-3 px-4 py-2.5"
                    >
                        <span className="flex size-7 shrink-0 items-center justify-center rounded-full bg-secondary text-label font-semibold text-secondary-foreground">
                            {initials}
                        </span>

                        <span className="flex-1 truncate text-caption font-medium text-foreground">
                            {name}
                        </span>

                        <span className="inline-flex items-center gap-0.5 font-numeric text-caption font-semibold text-movement-icon">
                            <Flame className="size-3" />
                            {streak}
                        </span>

                        <span className="flex items-center gap-1.5">
                            {PILLARS.map((type) => (
                                <span
                                    key={type}
                                    className={cn(
                                        'flex size-5 items-center justify-center rounded-sm font-numeric text-label font-semibold',
                                        winTypeMeta[type].tint,
                                        winTypeMeta[type].ink,
                                    )}
                                >
                                    {counts[type]}
                                </span>
                            ))}
                        </span>
                    </li>
                ))}
            </ul>

            <div className="flex items-center justify-between gap-2 border-t border-border bg-muted/50 px-4 py-2">
                <span className="flex items-center gap-1.5">
                    {PILLARS.map((type) => (
                        <span
                            key={type}
                            className="flex items-center gap-1 text-label text-muted-foreground"
                        >
                            <span
                                className={cn(
                                    'size-1.5 rounded-full',
                                    winTypeMeta[type].dot,
                                )}
                            />
                            {winTypeMeta[type].label}
                        </span>
                    ))}
                </span>
                <span className="font-numeric text-label font-semibold text-foreground">
                    45 acts of care
                </span>
            </div>
        </div>
    );
}
