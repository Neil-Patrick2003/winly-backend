import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

/**
 * A titled section of the console.
 *
 * One heading style and one gap, so two panels side by side start at the same
 * point down the page however different their contents are. The card takes the
 * room left after the heading rather than the section's full height — a card
 * set to 100% would measure a box that already contains that heading and hang
 * over whatever follows by exactly the height of the title above it.
 */
export function Panel({
    title,
    hint,
    className,
    children,
}: {
    title: string;
    /** The unit or qualifier, in a lighter weight beside the title. */
    hint?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <section className={cn('flex flex-col', className)}>
            <div className="mb-2 flex items-baseline gap-2">
                <h2 className="font-display text-sm font-semibold text-foreground">
                    {title}
                </h2>
                {hint && (
                    <span className="text-caption text-muted-foreground">
                        {hint}
                    </span>
                )}
            </div>

            <div className="flex-1">{children}</div>
        </section>
    );
}
