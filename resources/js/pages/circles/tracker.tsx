import { Head, router } from '@inertiajs/react';
import { Flame, Users } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Pagination } from '@/components/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { UserAvatar } from '@/components/user-avatar';
import { winTypeMeta } from '@/components/win-type-badge';
import CircleLayout from '@/layouts/circle/circle-layout';
import { cn } from '@/lib/utils';
import { tracker } from '@/routes/circles';
import type { CircleHeader, Paginated, TrackerRange, TrackerRow, WinType } from '@/types';

const rangeLabels: Record<TrackerRange, string> = {
    '7': 'Last 7 days',
    '30': 'Last 30 days',
    '90': 'Last 90 days',
    all: 'All time',
};

/** A count, with nothing dimmed so the ones who showed up stand out. */
function Count({ value, label }: { value: number; label: string }) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span
                    className={cn(
                        'tabular-nums',
                        value === 0
                            ? 'text-muted-foreground/40'
                            : 'font-medium text-foreground',
                    )}
                >
                    {value === 0 ? '—' : value}
                </span>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}

export default function Tracker({
    circle,
    members,
    winTypes,
    range,
    since,
}: {
    circle: CircleHeader;
    members: Paginated<TrackerRow>;
    winTypes: WinType[];
    range: TrackerRange;
    since: string | null;
}) {
    const changeRange = (next: string) =>
        router.get(
            tracker(circle.id).url,
            { range: next },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    const rangeCaption = since
        ? `Wins shared into this circle since ${new Date(since).toLocaleDateString(
              undefined,
              { month: 'short', day: 'numeric', year: 'numeric' },
          )}.`
        : 'Every win shared into this circle.';

    return (
        <>
            <Head title={`${circle.name} · Tracker`} />

            <CircleLayout circle={circle}>
                <div className="space-y-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-caption text-muted-foreground">
                            {rangeCaption}
                        </p>

                        <Select value={range} onValueChange={changeRange}>
                            <SelectTrigger
                                className="w-44"
                                aria-label="Date range"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(
                                    Object.keys(rangeLabels) as TrackerRange[]
                                ).map((key) => (
                                    <SelectItem key={key} value={key}>
                                        {rangeLabels[key]}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {members.data.length === 0 ? (
                        <EmptyState
                            icon={Users}
                            title="Nobody in this circle yet"
                            description="Once people join, what they share here will be counted."
                        />
                    ) : (
                        <>
                            <div className="overflow-x-auto rounded-card border border-border shadow-card">
                                <table className="w-full border-collapse">
                                    <caption className="sr-only">
                                        Wins by kind for each member of {circle.name},{' '}
                                        {rangeLabels[range].toLowerCase()}
                                    </caption>

                                    <thead>
                                        <tr className="border-b border-border bg-muted/40">
                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 text-left text-label font-medium tracking-wide text-muted-foreground uppercase"
                                            >
                                                Member
                                            </th>

                                            <th
                                                scope="col"
                                                className="px-3 py-2.5 text-center text-label font-medium tracking-wide text-muted-foreground uppercase"
                                            >
                                                Streak
                                            </th>

                                            {winTypes.map((type) => {
                                                const { label, icon: Icon, ink } =
                                                    winTypeMeta[type];

                                                return (
                                                    <th
                                                        key={type}
                                                        scope="col"
                                                        className="px-3 py-2.5 text-center text-label font-medium tracking-wide text-muted-foreground uppercase"
                                                    >
                                                        <span className="flex items-center justify-center gap-1.5">
                                                            <Icon
                                                                className={cn(
                                                                    'size-3.5',
                                                                    ink,
                                                                )}
                                                                aria-hidden
                                                            />
                                                            {label}
                                                        </span>
                                                    </th>
                                                );
                                            })}

                                            <th
                                                scope="col"
                                                className="px-4 py-2.5 text-right text-label font-medium tracking-wide text-muted-foreground uppercase"
                                            >
                                                Total
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-border">
                                        {members.data.map((member) => (
                                            <tr
                                                key={member.id}
                                                className="transition-colors hover:bg-muted/30"
                                            >
                                                <th
                                                    scope="row"
                                                    className="px-4 py-3 text-left font-normal"
                                                >
                                                    <div className="flex items-center gap-3">
                                                        <UserAvatar
                                                            name={member.full_name}
                                                            src={member.avatar_url}
                                                        />

                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-medium">
                                                                {member.full_name}
                                                            </p>
                                                            {member.username && (
                                                                <p className="truncate text-caption text-muted-foreground">
                                                                    @{member.username}
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                </th>

                                                <td className="px-3 py-3 text-center">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <span
                                                                className={cn(
                                                                    'inline-flex items-center gap-1 tabular-nums',
                                                                    member.streak_days ===
                                                                        0 &&
                                                                        'text-muted-foreground/40',
                                                                )}
                                                            >
                                                                <Flame
                                                                    className={cn(
                                                                        'size-3.5',
                                                                        member.streak_days >
                                                                            0
                                                                            ? 'text-orange-500'
                                                                            : 'text-muted-foreground/40',
                                                                    )}
                                                                    aria-hidden
                                                                />
                                                                {member.streak_days}
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {member.streak_days === 0
                                                                ? 'No streak running'
                                                                : `${member.streak_days} day streak`}
                                                            {member.longest_streak > 0 &&
                                                                ` · best ${member.longest_streak}`}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                </td>

                                                {winTypes.map((type) => (
                                                    <td
                                                        key={type}
                                                        className="px-3 py-3 text-center"
                                                    >
                                                        <Count
                                                            value={member.wins[type]}
                                                            label={`${member.wins[type]} ${winTypeMeta[type].label.toLowerCase()} ${
                                                                member.wins[type] === 1
                                                                    ? 'win'
                                                                    : 'wins'
                                                            }`}
                                                        />
                                                    </td>
                                                ))}

                                                <td className="px-4 py-3 text-right text-sm font-semibold tabular-nums">
                                                    {member.total}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            <p className="text-caption text-muted-foreground">
                                A dash means none of that kind in this range. The streak
                                counts days in a row with a win, wherever it was shared.
                            </p>

                            <Pagination page={members} label="members" />
                        </>
                    )}
                </div>
            </CircleLayout>
        </>
    );
}
