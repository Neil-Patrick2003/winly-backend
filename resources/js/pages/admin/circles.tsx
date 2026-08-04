import { Head, router } from '@inertiajs/react';
import { Plus, Search, ShieldAlert, Users } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { AdminCreateCircleDialog } from '@/components/admin-create-circle-dialog';
import { AdminNav } from '@/components/admin-nav';
import { CircleCard } from '@/components/circle-card';
import { EmptyState } from '@/components/empty-state';
import { Page, PageHeader } from '@/components/page';
import { Pagination } from '@/components/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useDebouncedValue } from '@/hooks/use-debounced-value';
import { cn } from '@/lib/utils';
import { circles as adminCircles } from '@/routes/admin';
import type { AdminCircleListing, CirclePerson, Paginated } from '@/types';

type Tab = 'all' | 'active' | 'quiet' | 'ownerless';

const tabs: { key: Tab; label: string }[] = [
    { key: 'all', label: 'All circles' },
    { key: 'active', label: 'Active' },
    { key: 'quiet', label: 'Quiet' },
    { key: 'ownerless', label: 'No owner' },
];

/**
 * Every circle on the platform, drawn the way a member sees their own.
 *
 * Deliberately the same card as "My Circles": staff are looking at the same
 * objects, and a second visual language for them would be the product learned
 * twice. What is added is the owner on each card, and the way in — from here
 * the ordinary members, posts, tracker and manage tabs open for any circle.
 */
export default function AdminCircles({
    circles,
    filter,
    search,
    counts,
    ownerCandidates,
    ownerSearch,
}: {
    circles: Paginated<AdminCircleListing>;
    filter: Tab;
    search: string | null;
    counts: Record<Tab, number>;
    ownerCandidates: CirclePerson[];
    ownerSearch: string | null;
}) {
    const [term, setTerm] = useState(search ?? '');
    const settled = useDebouncedValue(term);
    // The first render is the server's own answer; asking again on mount would
    // be a wasted round trip.
    const mounted = useRef(false);

    useEffect(() => {
        if (!mounted.current) {
            mounted.current = true;

            return;
        }

        router.get(
            adminCircles().url,
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

    const show = (next: Tab) =>
        router.get(
            adminCircles().url,
            { filter: { state: next, ...(term ? { search: term } : {}) } },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title="All circles" />

            <Page width="wide">
                <PageHeader
                    title="All circles"
                    description="Every circle on the platform. Open one to see its members, posts and tracker, or to manage it."
                    action={
                        <>
                            <AdminCreateCircleDialog
                                candidates={ownerCandidates}
                                ownerSearch={ownerSearch}
                                trigger={
                                    <Button size="sm">
                                        <Plus className="size-4" />
                                        New circle
                                    </Button>
                                }
                            />
                            <AdminNav />
                        </>
                    }
                />

                <div className="mt-6 flex flex-wrap items-center gap-2">
                    {tabs.map(({ key, label }) => (
                        <button
                            key={key}
                            type="button"
                            onClick={() => show(key)}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                filter === key
                                    ? 'bg-accent text-accent-foreground'
                                    : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                            )}
                        >
                            {label}
                            <span className="ml-1.5 text-caption opacity-70">
                                {counts[key]}
                            </span>
                        </button>
                    ))}

                    <div className="relative ml-auto w-full sm:w-64">
                        <Search className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={term}
                            onChange={(event) => setTerm(event.target.value)}
                            placeholder="Search circles"
                            aria-label="Search circles"
                            className="pl-8"
                        />
                    </div>
                </div>

                {circles.data.length === 0 ? (
                    <EmptyState
                        className="mt-6"
                        icon={Users}
                        title="No circles found"
                        description={
                            search
                                ? `Nothing matches “${search}”.`
                                : 'There are no circles under this filter.'
                        }
                    />
                ) : (
                    <div className="mt-6 space-y-4">
                        <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {circles.data.map((circle) => (
                                <li key={circle.id} className="flex flex-col">
                                    <CircleCard circle={circle} />

                                    <p className="mt-2 flex items-center gap-2 px-1 text-caption text-muted-foreground">
                                        {circle.owner ? (
                                            <>
                                                Owned by{' '}
                                                {circle.owner.full_name}
                                            </>
                                        ) : (
                                            <Badge
                                                variant="secondary"
                                                className="gap-1"
                                            >
                                                <ShieldAlert
                                                    className="size-3"
                                                    aria-hidden
                                                />
                                                No owner — nobody can manage it
                                            </Badge>
                                        )}
                                    </p>
                                </li>
                            ))}
                        </ul>

                        <Pagination page={circles} label="circles" />
                    </div>
                )}
            </Page>
        </>
    );
}
