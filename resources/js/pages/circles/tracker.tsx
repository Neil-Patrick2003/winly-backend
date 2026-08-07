import { Head, router } from '@inertiajs/react';
import { ChevronDown, Flame, Users } from 'lucide-react';
import { DateRangePicker } from '@/components/date-range-picker';
import { EmptyState } from '@/components/empty-state';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserAvatar } from '@/components/user-avatar';
import { winTypeMeta } from '@/components/win-type-badge';
import CircleLayout from '@/layouts/circle/circle-layout';
import { cn } from '@/lib/utils';
import { tracker } from '@/routes/circles';
import type { CircleHeader, Paginated, TrackerRow, WinType } from '@/types';

/** The day, written the way this reader's locale writes days. */
function readable(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

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
    circleOptions,
    selectedCircles,
    members,
    winTypes,
    from,
    to,
    days,
    errors,
}: {
    circle: CircleHeader;
    /** This circle and the ones inside it — what the picker offers. */
    circleOptions: { id: string; name: string; is_parent: boolean }[];
    /** The ids actually being counted right now. */
    selectedCircles: string[];
    members: Paginated<TrackerRow>;
    winTypes: WinType[];
    from: string;
    to: string;
    days: number;
    errors: Record<string, string>;
}) {
    /*
     * The date range and the circle choice travel together.
     *
     * Both live in the query string, so changing one has to resend the other or
     * it is dropped on the way — narrowing to a circle would silently reset the
     * dates back to the default month.
     */
    const reload = (next: {
        from?: string;
        to?: string;
        circles?: string[];
    }) => {
        const circles = next.circles ?? selectedCircles;

        router.get(
            tracker(circle.id).url,
            {
                from: next.from ?? from,
                to: next.to ?? to,
                // All of them selected is the same as no filter, and a shorter
                // URL says so.
                ...(circles.length === circleOptions.length ? {} : { circles }),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const apply = (nextFrom: string, nextTo: string) =>
        reload({ from: nextFrom, to: nextTo });

    /** Everything ticked is the same as no filter — the label says so. */
    const allSelected = selectedCircles.length === circleOptions.length;

    const toggleCircle = (id: string) => {
        const next = selectedCircles.includes(id)
            ? selectedCircles.filter((each) => each !== id)
            : [...selectedCircles, id];

        // Clearing the last one would count nothing, which is never what
        // somebody means — an empty choice falls back to all of them.
        reload({
            circles: next.length === 0 ? circleOptions.map((o) => o.id) : next,
        });
    };

    return (
        <>
            <Head title={`${circle.name} · Tracker`} />

            <CircleLayout circle={circle}>
                <div className="space-y-4">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <p className="text-caption text-muted-foreground">
                            Wins shared into{' '}
                            {selectedCircles.length === circleOptions.length
                                ? circleOptions.length > 1
                                    ? 'this circle and the ones inside it'
                                    : 'this circle'
                                : `${selectedCircles.length} of ${circleOptions.length} circles`}{' '}
                            between{' '}
                            <span className="font-medium text-foreground">
                                {readable(from)}
                            </span>{' '}
                            and{' '}
                            <span className="font-medium text-foreground">
                                {readable(to)}
                            </span>{' '}
                            — {days} {days === 1 ? 'day' : 'days'}.
                        </p>

                        {/* The two controls travel together, in their own row.
                            They were siblings of the paragraph inside a
                            justify-between container, so the space between
                            them was whatever was left over after the text —
                            wide on a long sentence, nothing on a short one.
                            `items-end` lines their inputs up rather than their
                            labels, which are different lengths. */}
                        <div className="flex flex-wrap items-end gap-3">
                            {/* Only where there is a choice to make: a circle with
                                none inside it would be a picker of one. */}
                            {circleOptions.length > 1 ? (
                                <div className="grid gap-1.5">
                                    <Label className="text-caption">
                                        Circles counted
                                    </Label>

                                    {/* A dropdown rather than a row of chips: the
                                        count is what somebody reads at a glance,
                                        and a circle with a dozen inside it would
                                        otherwise push the date picker off the row. */}
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="outline"
                                                className="w-56 justify-between font-normal"
                                            >
                                                <span className="truncate">
                                                    {allSelected
                                                        ? 'All circles'
                                                        : selectedCircles.length ===
                                                            1
                                                          ? (circleOptions.find(
                                                                (option) =>
                                                                    option.id ===
                                                                    selectedCircles[0],
                                                            )?.name ??
                                                            '1 circle')
                                                          : `${selectedCircles.length} of ${circleOptions.length} circles`}
                                                </span>
                                                <ChevronDown
                                                    className="size-4 opacity-50"
                                                    aria-hidden
                                                />
                                            </Button>
                                        </DropdownMenuTrigger>

                                        <DropdownMenuContent
                                            align="start"
                                            className="w-56"
                                        >
                                            <DropdownMenuLabel>
                                                Count wins shared into
                                            </DropdownMenuLabel>
                                            <DropdownMenuSeparator />

                                            {/* Kept open on each tick: choosing
                                                three circles should be three taps,
                                                not three trips back to the menu. */}
                                            {circleOptions.map((option) => (
                                                <DropdownMenuCheckboxItem
                                                    key={option.id}
                                                    checked={selectedCircles.includes(
                                                        option.id,
                                                    )}
                                                    onSelect={(event) =>
                                                        event.preventDefault()
                                                    }
                                                    onCheckedChange={() =>
                                                        toggleCircle(option.id)
                                                    }
                                                >
                                                    <span className="truncate">
                                                        {option.name}
                                                    </span>
                                                    {option.is_parent ? null : (
                                                        <span className="ml-1 text-xs text-muted-foreground">
                                                            inside
                                                        </span>
                                                    )}
                                                </DropdownMenuCheckboxItem>
                                            ))}

                                            <DropdownMenuSeparator />
                                            <DropdownMenuItem
                                                disabled={allSelected}
                                                onSelect={() =>
                                                    reload({
                                                        circles:
                                                            circleOptions.map(
                                                                (option) =>
                                                                    option.id,
                                                            ),
                                                    })
                                                }
                                            >
                                                Select all
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            ) : null}

                            <div className="grid gap-1.5">
                                <Label htmlFor="range" className="text-caption">
                                    Date range
                                </Label>
                                <DateRangePicker
                                    from={from}
                                    to={to}
                                    onApply={apply}
                                    invalid={!!errors.to}
                                />
                            </div>
                        </div>
                    </div>

                    {errors.to && (
                        <p className="text-caption text-destructive">
                            {errors.to}
                        </p>
                    )}

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
                                        Wins by kind for each member of{' '}
                                        {circle.name}, from {readable(from)} to{' '}
                                        {readable(to)}
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
                                                const {
                                                    label,
                                                    icon: Icon,
                                                    ink,
                                                } = winTypeMeta[type];

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
                                                Total days
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
                                                            name={
                                                                member.full_name
                                                            }
                                                            src={
                                                                member.avatar_url
                                                            }
                                                        />

                                                        <div className="min-w-0">
                                                            <p className="truncate text-sm font-medium">
                                                                {
                                                                    member.full_name
                                                                }
                                                            </p>
                                                            {member.username && (
                                                                <p className="truncate text-caption text-muted-foreground">
                                                                    @
                                                                    {
                                                                        member.username
                                                                    }
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
                                                                {
                                                                    member.streak_days
                                                                }
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {member.streak_days ===
                                                            0
                                                                ? 'No streak running'
                                                                : `${member.streak_days} day streak`}
                                                            {member.longest_streak >
                                                                0 &&
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
                                                            value={
                                                                member.wins[
                                                                    type
                                                                ]
                                                            }
                                                            label={`${member.wins[type]} ${winTypeMeta[type].label.toLowerCase()} ${
                                                                member.wins[
                                                                    type
                                                                ] === 1
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
                                A dash means none of that kind in this range.
                                Total days counts the days somebody logged at
                                least one win here, so three in one day still
                                counts once. The streak counts days in a row
                                with a win, wherever it was shared.
                            </p>

                            <Pagination page={members} label="members" />
                        </>
                    )}
                </div>
            </CircleLayout>
        </>
    );
}
