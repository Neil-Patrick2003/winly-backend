import { Link } from '@inertiajs/react';
import { MessageSquare } from 'lucide-react';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { UserAvatar } from '@/components/user-avatar';
import { cn } from '@/lib/utils';
import { members } from '@/routes/circles';
import type { CircleListing, CircleWash } from '@/types';

/**
 * The pastel each wash paints with: the band across the top of the card and the
 * chip that names the tag, in one hue.
 */
const washes: Record<CircleWash, { band: string; chip: string }> = {
    blue: {
        band: 'from-wash-blue',
        chip: 'bg-wash-blue text-wash-blue-ink',
    },
    lavender: {
        band: 'from-wash-lavender',
        chip: 'bg-wash-lavender text-wash-lavender-ink',
    },
    pink: {
        band: 'from-wash-pink',
        chip: 'bg-wash-pink text-wash-pink-ink',
    },
    peach: {
        band: 'from-wash-peach',
        chip: 'bg-wash-peach text-wash-peach-ink',
    },
    mint: {
        band: 'from-wash-mint',
        chip: 'bg-wash-mint text-wash-mint-ink',
    },
    butter: {
        band: 'from-wash-butter',
        chip: 'bg-wash-butter text-wash-butter-ink',
    },
};

export function CircleCard({ circle }: { circle: CircleListing }) {
    const wash = washes[circle.wash];

    return (
        <article className="group flex h-full flex-col overflow-hidden rounded-sheet border border-border bg-card shadow-card transition-shadow hover:shadow-raised">
            {/* A wash of the circle's colour, fading into the card it tops. */}
            <div
                className={cn(
                    'h-20 bg-gradient-to-b to-card',
                    wash.band,
                )}
                aria-hidden
            />

            <div className="flex flex-1 flex-col gap-3 px-5 pt-4 pb-3">
                <div className="flex items-start gap-2">
                    <div className="min-w-0 flex-1">
                        <h2 className="truncate text-base font-semibold tracking-tight">
                            <Link
                                href={members(circle.id)}
                                prefetch
                                className="rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            >
                                {circle.name}
                            </Link>
                        </h2>
                    </div>

                    <Tooltip>
                        <TooltipTrigger asChild>
                            <span
                                className={cn(
                                    'mt-1.5 size-2 shrink-0 rounded-full',
                                    circle.is_active
                                        ? 'bg-primary'
                                        : 'bg-wash-peach-ink/60',
                                )}
                            >
                                <span className="sr-only">
                                    {circle.is_active ? 'Active' : 'Quiet'}
                                </span>
                            </span>
                        </TooltipTrigger>
                        <TooltipContent>
                            {circle.is_active
                                ? 'Shared into this week'
                                : 'Nothing shared this week'}
                        </TooltipContent>
                    </Tooltip>
                </div>

                {circle.tag && (
                    <span
                        className={cn(
                            'w-fit rounded-md px-2 py-0.5 text-label font-semibold tracking-wide uppercase',
                            wash.chip,
                        )}
                    >
                        {circle.tag}
                    </span>
                )}

                {circle.description && (
                    <p className="line-clamp-2 text-sm text-muted-foreground">
                        {circle.description}
                    </p>
                )}
            </div>

            <div className="mt-auto flex items-center gap-3 border-t border-border px-5 py-3">
                {circle.faces.length > 0 && (
                    <div className="flex -space-x-2">
                        {circle.faces.map((face) => (
                            <UserAvatar
                                key={face.id}
                                name={face.full_name}
                                src={face.avatar_url}
                                className="size-6 ring-2 ring-card"
                            />
                        ))}
                    </div>
                )}

                <span className="text-sm font-medium tabular-nums">
                    {circle.members_count}
                    <span className="sr-only"> members</span>
                </span>

                <Tooltip>
                    <TooltipTrigger asChild>
                        <span className="ml-auto flex items-center gap-1.5 text-sm text-muted-foreground tabular-nums">
                            <MessageSquare className="size-4" aria-hidden />
                            {circle.posts_count}
                        </span>
                    </TooltipTrigger>
                    <TooltipContent>
                        {circle.posts_count}{' '}
                        {circle.posts_count === 1 ? 'win' : 'wins'} shared
                    </TooltipContent>
                </Tooltip>
            </div>
        </article>
    );
}
