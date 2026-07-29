import { Head, Link } from '@inertiajs/react';
import { Settings2, Users } from 'lucide-react';
import { CircleBadge } from '@/components/circle-badge';
import { EmptyState } from '@/components/empty-state';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { manage, members } from '@/routes/circles';
import type { CircleListing } from '@/types';

export default function CirclesIndex({ circles }: { circles: CircleListing[] }) {
    return (
        <>
            <Head title="My Circles" />

            <div className="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6">
                <Heading
                    title="My Circles"
                    description="The circles you have joined. Open one to see its members, posts and tracker."
                />

                {circles.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="You have not joined a circle yet"
                        description="Circles you join from the app will show up here, with everything shared into them."
                    />
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {circles.map((circle) => (
                            <li key={circle.id}>
                                <article className="group flex h-full flex-col gap-3 rounded-card border border-border p-4 shadow-card transition-shadow hover:shadow-raised">
                                    <div className="flex items-start gap-3">
                                        <CircleBadge
                                            initial={circle.icon_initial}
                                            color={circle.color_hex}
                                        />

                                        <div className="min-w-0 flex-1">
                                            <h2 className="truncate text-card-title font-medium">
                                                <Link
                                                    href={members(circle.id)}
                                                    prefetch
                                                    className="rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                >
                                                    {circle.name}
                                                </Link>
                                            </h2>

                                            {circle.tag && (
                                                <Badge
                                                    variant="secondary"
                                                    className="mt-1"
                                                >
                                                    {circle.tag}
                                                </Badge>
                                            )}
                                        </div>
                                    </div>

                                    {circle.description && (
                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                            {circle.description}
                                        </p>
                                    )}

                                    <p className="text-caption text-muted-foreground tabular-nums">
                                        {circle.members_count}{' '}
                                        {circle.members_count === 1
                                            ? 'member'
                                            : 'members'}{' '}
                                        · {circle.posts_count}{' '}
                                        {circle.posts_count === 1 ? 'post' : 'posts'}
                                    </p>

                                    <div className="mt-auto flex flex-wrap gap-2 pt-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={members(circle.id)} prefetch>
                                                Open
                                            </Link>
                                        </Button>

                                        {circle.can_manage && (
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={manage(circle.id)} prefetch>
                                                    <Settings2 className="size-4" />
                                                    Manage
                                                </Link>
                                            </Button>
                                        )}
                                    </div>
                                </article>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
