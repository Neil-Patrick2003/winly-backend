import { Link } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { CircleBadge } from '@/components/circle-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { manage, members, posts, tracker } from '@/routes/circles';
import type { CircleHeader } from '@/types';

/**
 * The circle header and its tabs.
 *
 * Each tab is its own URL rather than local state, so a tab can be linked to,
 * reloaded, and reached with the back button — and each one loads only the
 * rows it shows instead of every tab's data on every visit.
 */
export default function CircleLayout({
    circle,
    children,
}: PropsWithChildren<{ circle: CircleHeader }>) {
    const { isCurrentUrl } = useCurrentUrl();

    const tabs = [
        { title: 'Members', href: members(circle.id) },
        { title: 'Posts', href: posts(circle.id) },
        { title: 'Tracker', href: tracker(circle.id) },
    ];

    return (
        <div className="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6">
            <header className="flex flex-wrap items-start gap-4">
                <CircleBadge
                    initial={circle.icon_initial}
                    color={circle.color_hex}
                    size="lg"
                />

                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="truncate text-xl font-semibold tracking-tight">
                            {circle.name}
                        </h1>
                        {circle.tag && <Badge variant="secondary">{circle.tag}</Badge>}
                    </div>

                    {circle.description && (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {circle.description}
                        </p>
                    )}

                    <p className="mt-1 text-caption text-muted-foreground tabular-nums">
                        {circle.members_count}{' '}
                        {circle.members_count === 1 ? 'member' : 'members'}
                    </p>
                </div>

                {circle.can_manage && (
                    <Button variant="outline" size="sm" asChild>
                        <Link href={manage(circle.id)} prefetch>
                            <Settings2 className="size-4" />
                            Manage
                        </Link>
                    </Button>
                )}
            </header>

            <nav
                aria-label="Circle sections"
                className="mt-6 flex gap-1 rounded-lg bg-muted p-1"
            >
                {tabs.map((tab) => {
                    const isActive = isCurrentUrl(tab.href);

                    return (
                        <Link
                            key={tab.title}
                            href={tab.href}
                            prefetch
                            aria-current={isActive ? 'page' : undefined}
                            className={cn(
                                'flex-1 rounded-md px-3 py-1.5 text-center text-sm font-medium transition-colors',
                                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                isActive
                                    ? 'bg-background text-foreground shadow-float'
                                    : 'text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.title}
                        </Link>
                    );
                })}
            </nav>

            <div className="mt-6">{children}</div>
        </div>
    );
}
