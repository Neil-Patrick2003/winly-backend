import { Link } from '@inertiajs/react';
import { Settings2 } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import { CircleBadge } from '@/components/circle-badge';
import { Page, PageHeader } from '@/components/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import {
    index as circlesIndex,
    manage,
    members,
    posts,
    tracker,
} from '@/routes/circles';
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
        <Page width="wide">
            <PageHeader
                title={circle.name}
                description={circle.description ?? undefined}
                back={{ href: circlesIndex(), label: 'All circles' }}
                leading={
                    <CircleBadge
                        initial={circle.icon_initial}
                        color={circle.color_hex}
                        size="lg"
                    />
                }
                action={
                    circle.can_manage && (
                        <Button variant="outline" asChild>
                            <Link href={manage(circle.id)} prefetch>
                                <Settings2 className="size-4" />
                                Manage
                            </Link>
                        </Button>
                    )
                }
            />

            <div className="mt-2 flex flex-wrap items-center gap-2 text-caption text-muted-foreground tabular-nums">
                <span>
                    {circle.members_count}{' '}
                    {circle.members_count === 1 ? 'member' : 'members'}
                </span>
                {circle.tag && (
                    <>
                        <span aria-hidden>·</span>
                        <Badge variant="secondary">{circle.tag}</Badge>
                    </>
                )}
            </div>

            <nav
                aria-label="Circle sections"
                className="mt-6 flex gap-6 border-b border-border"
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
                                '-mb-px border-b-2 pb-3 text-sm font-medium transition-colors',
                                'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                isActive
                                    ? 'border-primary text-primary'
                                    : 'border-transparent text-muted-foreground hover:text-foreground',
                            )}
                        >
                            {tab.title}
                        </Link>
                    );
                })}
            </nav>

            <div className="mt-6">{children}</div>
        </Page>
    );
}
