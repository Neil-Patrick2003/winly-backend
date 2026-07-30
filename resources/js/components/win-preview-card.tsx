import { Flame } from 'lucide-react';
import { winTypeMeta } from '@/components/win-type-badge';
import { cn } from '@/lib/utils';
import type { WinType } from '@/types';

const PILLARS: WinType[] = ['meditation', 'learning', 'movement'];

/**
 * A sample post, shown to people who have not signed in yet.
 *
 * Drawn from the theme rather than dropped in as a screenshot: the pillars keep
 * the hues they carry everywhere else in the app, the type comes off the same
 * scale, and the whole thing follows light and dark. A flat image would do none
 * of that, and would be stale the day the feed changed.
 *
 * Decorative, so it is hidden from screen readers — everything it says is said
 * again in the prose beside it.
 */
export function WinPreviewCard({ className }: { className?: string }) {
    return (
        <div
            aria-hidden
            className={cn('w-full max-w-sm select-none', className)}
        >
            <div className="rounded-sheet border border-border bg-card p-4 shadow-raised">
                <div className="flex items-center gap-2.5">
                    <span className="flex size-8 items-center justify-center rounded-full bg-brand-gradient text-caption font-semibold text-white">
                        MC
                    </span>
                    <div className="grid flex-1">
                        <span className="text-card-title font-medium text-foreground">
                            Maya Cruz
                        </span>
                        <span className="text-label text-muted-foreground">
                            2 hours ago
                        </span>
                    </div>
                    <span className="inline-flex items-center gap-1 rounded-full bg-movement-bg px-2 py-0.5 font-numeric text-caption font-semibold text-movement-icon">
                        <Flame className="size-3" />
                        12
                    </span>
                </div>

                <p className="mt-3 text-section text-foreground">
                    Three small wins today. Showed up for all of them.
                </p>

                <div className="mt-3 flex flex-wrap gap-1.5">
                    {PILLARS.map((type) => {
                        const {
                            label,
                            icon: Icon,
                            tint,
                            ink,
                        } = winTypeMeta[type];

                        return (
                            <span
                                key={type}
                                className={cn(
                                    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-caption font-medium',
                                    tint,
                                    ink,
                                )}
                            >
                                <Icon className="size-3" />
                                {label}
                            </span>
                        );
                    })}
                </div>
            </div>

            {/*
             * Offset and overlapping, so the pair reads as a stack of real
             * cards rather than as one boxed illustration.
             */}
            <div className="mx-4 -mt-2 flex items-center justify-between rounded-card border border-border bg-card/80 px-3 py-2 shadow-card backdrop-blur-sm">
                <span className="text-caption text-muted-foreground">
                    This week
                </span>
                <span className="font-numeric text-card-title font-semibold text-foreground">
                    17 wins · 6 days
                </span>
            </div>
        </div>
    );
}
