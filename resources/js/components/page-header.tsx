import type { ReactNode } from 'react';

/**
 * The top of every index screen: what this page is, then what you can do here.
 *
 * Deliberately quiet — the eyebrow orients you, the title is the only display
 * type on the page, and the primary action always lands in the same corner.
 */
export default function PageHeader({
    eyebrow,
    title,
    description,
    actions,
}: {
    eyebrow?: string;
    title: string;
    description?: string;
    actions?: ReactNode;
}) {
    return (
        <header className="flex flex-wrap items-start justify-between gap-3 border-b border-border pb-4">
            <div className="space-y-0.5">
                {eyebrow && (
                    <p className="text-[11px] font-semibold tracking-[0.08em] text-muted-foreground uppercase">
                        {eyebrow}
                    </p>
                )}

                <h1 className="text-page-title font-semibold tracking-tight text-balance">
                    {title}
                </h1>

                {description && (
                    <p className="max-w-prose text-[13px] text-muted-foreground">
                        {description}
                    </p>
                )}
            </div>

            {actions && (
                <div className="flex shrink-0 items-center gap-2">
                    {actions}
                </div>
            )}
        </header>
    );
}
