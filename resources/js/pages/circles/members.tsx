import { Head } from '@inertiajs/react';
import { Flame, Users } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Pagination } from '@/components/pagination';
import { PersonRow } from '@/components/person-row';
import { Badge } from '@/components/ui/badge';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import CircleLayout from '@/layouts/circle/circle-layout';
import type { CircleHeader, CircleMemberRow, Paginated } from '@/types';

function joinedOn(iso: string | null): string {
    if (!iso) {
        return '';
    }

    return `Joined ${new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    })}`;
}

export default function Members({
    circle,
    members,
}: {
    circle: CircleHeader;
    members: Paginated<CircleMemberRow>;
}) {
    return (
        <>
            <Head title={`${circle.name} · Members`} />

            <CircleLayout circle={circle}>
                {members.data.length === 0 ? (
                    <EmptyState
                        icon={Users}
                        title="Nobody has joined yet"
                        description="Members who join this circle will be listed here."
                    />
                ) : (
                    <div className="space-y-4">
                        <ul className="divide-y divide-border overflow-hidden rounded-card border border-border shadow-card">
                            {members.data.map((member) => (
                                <li key={member.id} className="px-4 py-3">
                                    <PersonRow
                                        person={member}
                                        meta={
                                            <span className="flex flex-wrap items-center gap-x-2">
                                                {member.username && (
                                                    <span>
                                                        @{member.username}
                                                    </span>
                                                )}
                                                <span aria-hidden>·</span>
                                                <span>
                                                    {joinedOn(member.joined_at)}
                                                </span>
                                            </span>
                                        }
                                        action={
                                            <>
                                                {member.is_owner && (
                                                    <Badge variant="secondary">
                                                        Owner
                                                    </Badge>
                                                )}

                                                {/* Same rank, said differently:
                                                    the group needs to know who
                                                    to go to, and the founder is
                                                    worth naming apart. */}
                                                {member.is_co_owner && (
                                                    <Badge variant="secondary">
                                                        Co-owner
                                                    </Badge>
                                                )}

                                                {member.streak_days > 0 && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <span className="flex items-center gap-1 text-caption text-muted-foreground tabular-nums">
                                                                <Flame
                                                                    className="size-3.5 text-orange-500"
                                                                    aria-hidden
                                                                />
                                                                {
                                                                    member.streak_days
                                                                }
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {member.streak_days}{' '}
                                                            day streak
                                                        </TooltipContent>
                                                    </Tooltip>
                                                )}

                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <span className="w-16 text-right text-caption text-muted-foreground tabular-nums">
                                                            {member.wins_count}{' '}
                                                            wins
                                                        </span>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        {member.wins_count} wins
                                                        shared all told
                                                    </TooltipContent>
                                                </Tooltip>
                                            </>
                                        }
                                    />
                                </li>
                            ))}
                        </ul>

                        <Pagination page={members} label="members" />
                    </div>
                )}
            </CircleLayout>
        </>
    );
}
