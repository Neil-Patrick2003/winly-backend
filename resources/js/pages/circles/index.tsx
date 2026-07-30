import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, Users } from 'lucide-react';
import { CircleCard } from '@/components/circle-card';
import { CreateCircleDialog } from '@/components/create-circle-dialog';
import { EmptyState } from '@/components/empty-state';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { Page, PageHeader } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { useEffect, useRef, useState } from 'react';
import { index as circlesIndex } from '@/routes/circles';
import type { CircleFilter, CircleListing } from '@/types';

const tabs: { key: CircleFilter; label: string }[] = [
    { key: 'all', label: 'All circles' },
    { key: 'active', label: 'Active' },
    { key: 'quiet', label: 'Quiet' },
];

export default function CirclesIndex({
    circles,
    filter,
    search,
    counts,
}: {
    circles: CircleListing[];
    filter: CircleFilter;
    search: string | null;
    counts: Record<CircleFilter, number>;
}) {
    const [term, setTerm] = useState(search ?? '');
    const settled = useDebouncedValue(term);
    // The first render is the server's own answer; asking for it again the
    // moment the page mounts would be a wasted round trip.
    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        router.get(
            circlesIndex().url,
            {
                filter: {
                    state: filter,
                    ...(settled ? { search: settled } : {}),
                },
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['circles', 'search', 'counts'],
            },
        );
    }, [settled, filter]);

    const createButton = (
        <CreateCircleDialog
            trigger={
                <Button className="h-10 rounded-full px-5">
                    <Plus className="size-4" />
                    Create circle
                </Button>
            }
        />
    );

    return (
        <>
            <Head title="Create circle" />

            <Page width="wide">
                <PageHeader
                    title="Create circle"
                    description="Start a circle and track how it grows. The sharing itself happens on the app."
                    action={
                        <>
                            <div className="relative">
                                <Search
                                    className="pointer-events-none absolute top-1/2 left-3.5 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden
                                />
                                <Input
                                    type="search"
                                    value={term}
                                    onChange={(event) =>
                                        setTerm(event.target.value)
                                    }
                                    placeholder="Search circles…"
                                    aria-label="Search circles"
                                    className="h-10 w-56 rounded-full bg-card pl-10 shadow-card"
                                />
                            </div>

                            {createButton}
                        </>
                    }
                />

                <nav
                    aria-label="Filter circles"
                    className="mt-8 flex gap-6 border-b border-border"
                >
                    {tabs.map((tab) => {
                        const isActive = tab.key === filter;

                        return (
                            <Link
                                key={tab.key}
                                href={
                                    circlesIndex({
                                        query: {
                                            filter: {
                                                state: tab.key,
                                                ...(search ? { search } : {}),
                                            },
                                        },
                                    }).url
                                }
                                preserveScroll
                                aria-current={isActive ? 'page' : undefined}
                                className={cn(
                                    '-mb-px flex items-center gap-1.5 border-b-2 pb-3 text-sm font-medium transition-colors',
                                    isActive
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {tab.label}
                                <span
                                    className={cn(
                                        'tabular-nums',
                                        isActive
                                            ? 'text-primary'
                                            : 'text-muted-foreground/70',
                                    )}
                                >
                                    {counts[tab.key]}
                                </span>
                            </Link>
                        );
                    })}
                </nav>

                <div className="mt-6">
                    {circles.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title={
                                search
                                    ? 'Nothing matches that'
                                    : filter === 'all'
                                      ? 'You have not joined a circle yet'
                                      : `No ${filter} circles`
                            }
                            description={
                                search
                                    ? 'Try a different word, or clear the search to see everything.'
                                    : filter === 'all'
                                      ? 'Start one and it will show up here, with everything shared into it.'
                                      : 'Circles move between Active and Quiet on their own, as people share.'
                            }
                            action={
                                filter === 'all' && !search
                                    ? createButton
                                    : undefined
                            }
                        />
                    ) : (
                        <ul className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                            {circles.map((circle) => (
                                <li key={circle.id}>
                                    <CircleCard circle={circle} />
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </Page>
        </>
    );
}
