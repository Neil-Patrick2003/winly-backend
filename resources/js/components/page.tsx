import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const widths = {
    /** Forms and settings, where a long line is hard to read. */
    narrow: 'max-w-2xl',
    /** Tables and detail views. */
    default: 'max-w-4xl',
    /** Card grids, which want the room. */
    wide: 'max-w-6xl',
} as const;

type Props = {
    children: ReactNode;
    width?: keyof typeof widths;
    className?: string;
};

/**
 * The frame every page sits in.
 *
 * One place decides the gutters and the top and bottom room, so pages cannot
 * drift a few pixels apart from each other. Only the measure changes, and only
 * to one of three settled widths.
 */
export function Page({ children, width = 'default', className }: Props) {
    return (
        <div
            className={cn(
                'mx-auto w-full px-6 py-8 sm:px-8',
                widths[width],
                className,
            )}
        >
            {children}
        </div>
    );
}

/**
 * A page's title, its line of explanation, and whatever it is chiefly for.
 *
 * Every page wears the same one, so a title sits at the same size and the same
 * distance from the top wherever you land. `back` and `leading` are the only
 * things that vary: a way out of a nested page, and a mark before the title.
 */
export function PageHeader({
    title,
    description,
    action,
    back,
    leading,
    className,
}: {
    title: string;
    description?: string;
    action?: ReactNode;
    /** A way back out, shown above the title. */
    back?: { href: NonNullable<InertiaLinkProps['href']>; label: string };
    /** A badge or avatar set before the title. */
    leading?: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('space-y-4', className)}>
            {back && (
                <Button variant="ghost" size="sm" asChild className="-ml-2.5">
                    <Link href={back.href} prefetch>
                        <ArrowLeft className="size-4" />
                        {back.label}
                    </Link>
                </Button>
            )}

            <header className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex min-w-0 items-start gap-3">
                    {leading}

                    <div className="min-w-0">
                        <h1 className="truncate text-heading font-semibold tracking-tight">
                            {title}
                        </h1>

                        {description && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                </div>

                {action && (
                    <div className="flex shrink-0 items-center gap-3">{action}</div>
                )}
            </header>
        </div>
    );
}
