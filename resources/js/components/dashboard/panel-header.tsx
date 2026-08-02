import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { ReactNode } from 'react';

type Props = {
    title: string;
    /** A qualifier, kept on the title line rather than under it. */
    meta?: ReactNode;
};

/**
 * A panel's title, inside the card it belongs to.
 *
 * Titles used to sit above the cards, which meant a panel with a longer
 * heading pushed its card lower than the one beside it. Inside the card the
 * heading cannot shift anything, so a row of panels lines up whatever they are
 * called.
 */
export function PanelHeader({ title, meta }: Props) {
    return (
        <div className="flex items-baseline justify-between gap-3 px-4 pt-3.5 pb-2">
            <h2 className="font-display text-sm font-semibold">{title}</h2>
            {meta && (
                <span className="shrink-0 text-caption text-muted-foreground">
                    {meta}
                </span>
            )}
        </div>
    );
}

/**
 * The way out of a panel that is only showing the first few of something.
 */
export function PanelFooterLink({
    href,
    children,
}: {
    href: InertiaLinkProps['href'];
    children: ReactNode;
}) {
    return (
        <Link
            href={href}
            className="px-4 py-2.5 text-caption font-medium text-primary transition-colors hover:bg-accent/40"
        >
            {children}
        </Link>
    );
}
