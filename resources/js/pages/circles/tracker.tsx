import { Head, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ChevronDown,
    ChevronsUpDown,
    Flame,
    SearchX,
    SlidersHorizontal,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { DateRangePicker } from '@/components/date-range-picker';
import { EmptyState } from '@/components/empty-state';
import { ActiveFilterChips } from '@/components/filters/filter-bar';
import type { ActiveFilter } from '@/components/filters/filter-bar';
import SearchField from '@/components/filters/search-field';
import { Pagination } from '@/components/pagination';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { Separator } from '@/components/ui/separator';
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

/** Whether somebody turned up at all in the range. */
type Activity = 'active' | 'inactive';

/** Every column the table can be ordered by — each one a column on show. */
type SortBy = 'name' | 'streak' | 'days' | 'points' | WinType;

type TrackerSort = { by: SortBy; direction: 'asc' | 'desc' };

/**
 * Everything narrowing who is listed, as the server understands it.
 *
 * All of it narrows the rows rather than the range: a filtered page counts the
 * same days in the same circles, with fewer people standing next to the
 * numbers. Only the search is kept out, because it lives in its own box.
 */
type TrackerFilters = {
    /**
     * The awards. A complete run is the whole range with nothing missed — all
     * three kinds logged on each of its days. `exclude_referenced` qualifies
     * `complete` rather than standing on its own: it takes the cited finishers
     * back out, so the two awards name two sets of people.
     */
    completion: {
        with_reference: boolean;
        complete: boolean;
        exclude_referenced: boolean;
    };
    /** Both together is everybody, which is what neither of them already says. */
    activity: Activity[];
    /** Every kind ticked, not any of them. */
    kinds: WinType[];
    min_points: number | null;
    min_days: number | null;
    streaking: boolean;
    min_streak: number | null;
};

/** Nothing ticked and nothing typed — the ordinary tab. */
const NO_FILTERS: TrackerFilters = {
    completion: {
        with_reference: false,
        complete: false,
        exclude_referenced: false,
    },
    activity: [],
    kinds: [],
    min_points: null,
    min_days: null,
    streaking: false,
    min_streak: null,
};

/** How the tab opens: a list of people, read alphabetically. */
const BY_NAME: TrackerSort = { by: 'name', direction: 'asc' };

/** The day, written the way this reader's locale writes days. */
function readable(date: string): string {
    return new Date(`${date}T00:00:00`).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

/**
 * The filters as the query string carries them.
 *
 * Only what is set, so an untouched panel leaves the URL as clean as it was
 * and a shared link says exactly what was applied.
 */
function filterQuery(filters: TrackerFilters): Record<string, unknown> {
    const { completion } = filters;

    return {
        ...(completion.with_reference ? { complete_with_reference: 1 } : {}),
        ...(completion.complete ? { complete: 1 } : {}),
        ...(completion.complete && completion.exclude_referenced
            ? { exclude_referenced: 1 }
            : {}),
        ...(filters.activity.length > 0 ? { activity: filters.activity } : {}),
        ...(filters.kinds.length > 0 ? { kinds: filters.kinds } : {}),
        ...(filters.min_points ? { min_points: filters.min_points } : {}),
        ...(filters.min_days ? { min_days: filters.min_days } : {}),
        ...(filters.streaking ? { streaking: 1 } : {}),
        ...(filters.min_streak ? { min_streak: filters.min_streak } : {}),
    };
}

/** The order, left out of the URL where it is the one the tab opens with. */
function sortQuery(sort: TrackerSort): Record<string, unknown> {
    if (sort.by === BY_NAME.by && sort.direction === BY_NAME.direction) {
        return {};
    }

    return { sort: sort.by, direction: sort.direction };
}

/** A floor as the box shows it — an empty box is no floor at all. */
function asText(value: number | null): string {
    return value === null ? '' : String(value);
}

/**
 * A floor typed into one of the number boxes.
 *
 * Held locally while it is being typed and handed over on blur or Enter, so a
 * three-digit figure is one trip to the server rather than three. Anything
 * that is not a number drops back to what the server last confirmed, which is
 * what the box is showing everywhere else.
 */
function NumberFilter({
    id,
    label,
    value,
    onCommit,
}: {
    id: string;
    label: string;
    value: number | null;
    onCommit: (value: number | null) => void;
}) {
    const [draft, setDraft] = useState(asText(value));

    /*
     * The box follows the server whenever the server changes it — a chip
     * cleared outside this control has to empty the box that set it.
     *
     * Adjusted while rendering rather than in an effect, which is what React
     * asks for when state derives from a prop: an effect would paint the stale
     * figure first and correct it a frame later.
     */
    const [applied, setApplied] = useState(value);

    if (applied !== value) {
        setApplied(value);
        setDraft(asText(value));
    }

    const commit = () => {
        const trimmed = draft.trim();
        const parsed = Number(trimmed);

        if (trimmed !== '' && (!Number.isFinite(parsed) || parsed < 0)) {
            setDraft(asText(value));

            return;
        }

        // Zero is no floor at all — at least none is the whole list — and the
        // server reads it the same way.
        const next = trimmed === '' ? null : Math.floor(parsed) || null;

        setDraft(asText(next));

        if (next !== value) {
            onCommit(next);
        }
    };

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={id} className="text-caption font-normal">
                {label}
            </Label>
            <Input
                id={id}
                type="number"
                min={0}
                inputMode="numeric"
                className="h-8"
                placeholder="Any"
                value={draft}
                onChange={(event) => setDraft(event.target.value)}
                onBlur={commit}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        commit();
                    }
                }}
            />
        </div>
    );
}

/** One ticked line in the filter panel. */
function FilterCheckbox({
    id,
    checked,
    disabled,
    onToggle,
    children,
}: {
    id: string;
    checked: boolean;
    disabled?: boolean;
    onToggle: () => void;
    children: ReactNode;
}) {
    return (
        <div className="flex items-center gap-2">
            <Checkbox
                id={id}
                checked={checked}
                disabled={disabled}
                onCheckedChange={onToggle}
            />
            <Label
                htmlFor={id}
                className={cn(
                    'text-sm font-normal',
                    disabled && 'text-muted-foreground/50',
                )}
            >
                {children}
            </Label>
        </div>
    );
}

/** A headed group of controls in the filter panel. */
function FilterGroup({
    title,
    hint,
    children,
}: {
    title: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <p className="text-label font-medium tracking-wide text-muted-foreground uppercase">
                {title}
            </p>
            {hint && (
                <p className="-mt-1 text-caption text-muted-foreground">
                    {hint}
                </p>
            )}
            {children}
        </div>
    );
}

/**
 * A column header that orders the table.
 *
 * A fresh column opens the way it is asked for — names climb and numbers fall
 * — and clicking the one already sorted turns it around, which is the only
 * thing left to ask of a column that is already on top.
 */
function SortHeader({
    on,
    sort,
    onSort,
    align = 'center',
    children,
}: {
    on: SortBy;
    sort: TrackerSort;
    onSort: (next: TrackerSort) => void;
    align?: 'left' | 'center' | 'right';
    children: ReactNode;
}) {
    const active = sort.by === on;
    const opens = on === 'name' ? 'asc' : 'desc';
    const direction = active ? sort.direction : opens;

    const Arrow = !active
        ? ChevronsUpDown
        : direction === 'asc'
          ? ArrowUp
          : ArrowDown;

    return (
        <th
            scope="col"
            aria-sort={
                active
                    ? direction === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : 'none'
            }
            className={cn(
                'py-2.5 text-label font-medium tracking-wide text-muted-foreground uppercase',
                align === 'left' && 'px-4 text-left',
                align === 'right' && 'px-4 text-right',
                align === 'center' && 'px-3 text-center',
            )}
        >
            <button
                type="button"
                onClick={() =>
                    onSort({
                        by: on,
                        direction: active
                            ? direction === 'asc'
                                ? 'desc'
                                : 'asc'
                            : opens,
                    })
                }
                className={cn(
                    'inline-flex items-center gap-1.5 rounded-sm transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    active && 'text-foreground',
                    align === 'right' && 'flex-row-reverse',
                )}
            >
                {children}
                <Arrow
                    className={cn(
                        'size-3.5 shrink-0',
                        active ? 'opacity-100' : 'opacity-40',
                    )}
                    aria-hidden
                />
            </button>
        </th>
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
    search,
    filters,
    sort,
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
    /** What the list is narrowed to by name, if anything. */
    search: string | null;
    /** What the list is narrowed to by the panel, if anything. */
    filters: TrackerFilters;
    sort: TrackerSort;
    errors: Record<string, string>;
}) {
    const [panelOpen, setPanelOpen] = useState(false);

    /*
     * The date range, the circle choice, the search, the filters and the order
     * travel together.
     *
     * All of it lives in the query string, so changing one has to resend the
     * others or they are dropped on the way — narrowing to a circle would
     * silently reset the dates back to the default month, and typing a name
     * would undo both.
     */
    const reload = (next: {
        from?: string;
        to?: string;
        circles?: string[];
        search?: string | null;
        filters?: TrackerFilters;
        sort?: TrackerSort;
    }) => {
        const circles = next.circles ?? selectedCircles;
        const term = next.search === undefined ? search : next.search;

        router.get(
            tracker(circle.id).url,
            {
                from: next.from ?? from,
                to: next.to ?? to,
                // All of them selected is the same as no filter, and a shorter
                // URL says so.
                ...(circles.length === circleOptions.length ? {} : { circles }),
                ...(term ? { search: term } : {}),
                ...filterQuery(next.filters ?? filters),
                ...sortQuery(next.sort ?? sort),
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

    /** Change one thing in the panel and leave the rest of it alone. */
    const narrow = (change: Partial<TrackerFilters>) => {
        const next = { ...filters, ...change };

        // The exclusion is a qualification on the plain award, so unticking
        // that box takes its sub-option with it rather than leaving a setting
        // behind that nothing acts on.
        reload({
            filters: next.completion.complete
                ? next
                : {
                      ...next,
                      completion: {
                          ...next.completion,
                          exclude_referenced: false,
                      },
                  },
        });
    };

    const toggleAward = (key: keyof TrackerFilters['completion']) =>
        narrow({
            completion: {
                ...filters.completion,
                [key]: !filters.completion[key],
            },
        });

    /** Tick or untick one option in a set, leaving the others as they were. */
    const toggleIn = <T extends string>(chosen: T[], option: T): T[] =>
        chosen.includes(option)
            ? chosen.filter((each) => each !== option)
            : [...chosen, option];

    /*
     * What is applied, echoed back under the controls.
     *
     * The panel closes over its own settings, so without this a filtered list
     * looks like an empty circle. Each chip clears the one thing it names.
     */
    const chips: ActiveFilter[] = [];

    if (filters.completion.with_reference) {
        chips.push({
            key: 'with-reference',
            label: 'Award',
            value: 'Complete with reference',
            onClear: () => toggleAward('with_reference'),
        });
    }

    if (filters.completion.complete) {
        chips.push({
            key: 'complete',
            label: 'Award',
            value: filters.completion.exclude_referenced
                ? 'Complete, uncited only'
                : 'Complete',
            onClear: () => toggleAward('complete'),
        });
    }

    // Both ticked narrows nothing, so it is not something to show as applied.
    if (filters.activity.length === 1) {
        chips.push({
            key: 'activity',
            label: 'Activity',
            value:
                filters.activity[0] === 'active' ? 'Active' : 'Nothing logged',
            onClear: () => narrow({ activity: [] }),
        });
    }

    filters.kinds.forEach((kind) =>
        chips.push({
            key: `kind-${kind}`,
            label: 'Logged',
            value: winTypeMeta[kind].label,
            onClear: () => narrow({ kinds: toggleIn(filters.kinds, kind) }),
        }),
    );

    if (filters.min_points !== null) {
        chips.push({
            key: 'min-points',
            label: 'Points',
            value: `${filters.min_points} or more`,
            onClear: () => narrow({ min_points: null }),
        });
    }

    if (filters.min_days !== null) {
        chips.push({
            key: 'min-days',
            label: 'Days',
            value: `${filters.min_days} or more`,
            onClear: () => narrow({ min_days: null }),
        });
    }

    if (filters.streaking) {
        chips.push({
            key: 'streaking',
            label: 'Streak',
            value: 'Still running',
            onClear: () => narrow({ streaking: false }),
        });
    }

    if (filters.min_streak !== null) {
        chips.push({
            key: 'min-streak',
            label: 'Streak',
            value: `${filters.min_streak} days or more`,
            onClear: () => narrow({ min_streak: null }),
        });
    }

    const clearFilters = () => reload({ filters: NO_FILTERS });

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

                        {/* The controls travel together, in their own row.
                            They were siblings of the paragraph inside a
                            justify-between container, so the space between
                            them was whatever was left over after the text —
                            wide on a long sentence, nothing on a short one.
                            `items-end` lines their inputs up rather than their
                            labels, which are different lengths. */}
                        <div className="flex flex-wrap items-end gap-3">
                            {/* Narrows the rows, not the range: a searched list
                                still counts the same days in the same circles,
                                so a name typed here can be cleared without
                                losing the window somebody set up first. */}
                            <div className="grid w-56 gap-1.5">
                                <Label className="text-caption">
                                    Find a member
                                </Label>
                                <SearchField
                                    value={search}
                                    onSearch={(term) =>
                                        reload({ search: term })
                                    }
                                    placeholder="Name or username…"
                                    label="Search members"
                                />
                            </div>

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

                            {/* Everything that narrows who is listed rather
                                than what is counted, behind one disclosure —
                                eight controls strung across the row would bury
                                the date picker the tab is mostly about. What is
                                applied comes back as chips underneath, so a
                                closed panel never hides why a list is short. */}
                            <div className="grid gap-1.5">
                                <Label className="text-caption">Filters</Label>

                                <Popover
                                    open={panelOpen}
                                    onOpenChange={setPanelOpen}
                                >
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            className="font-normal"
                                            data-test="toggle-filters"
                                        >
                                            <SlidersHorizontal
                                                className="size-4"
                                                aria-hidden
                                            />
                                            Filters
                                            {chips.length > 0 && (
                                                <span className="ml-0.5 flex size-[18px] items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground tabular-nums">
                                                    {chips.length}
                                                </span>
                                            )}
                                        </Button>
                                    </PopoverTrigger>

                                    {/* The panel is taller than most screens
                                        once every group is in it, so the
                                        groups scroll and the clear button
                                        stays put — a control that has to be
                                        scrolled to is one somebody stops
                                        reaching for. Capped on the room Radix
                                        measured between the trigger and the
                                        edge of the window rather than on a
                                        share of the viewport, so a panel
                                        opened from a button near the bottom
                                        of the page still ends on screen. */}
                                    <PopoverContent
                                        align="end"
                                        className="flex max-h-(--radix-popover-content-available-height) w-80 flex-col p-0"
                                    >
                                        <div className="grid gap-4 overflow-y-auto p-4">
                                            <FilterGroup
                                                title="Awards"
                                                hint="Finishing means all three kinds logged on every day of the range."
                                            >
                                                <FilterCheckbox
                                                    id="award-cited"
                                                    checked={
                                                        filters.completion
                                                            .with_reference
                                                    }
                                                    onToggle={() =>
                                                        toggleAward(
                                                            'with_reference',
                                                        )
                                                    }
                                                >
                                                    Complete with learning
                                                    reference
                                                </FilterCheckbox>

                                                <FilterCheckbox
                                                    id="award-complete"
                                                    checked={
                                                        filters.completion
                                                            .complete
                                                    }
                                                    onToggle={() =>
                                                        toggleAward('complete')
                                                    }
                                                >
                                                    Complete
                                                </FilterCheckbox>

                                                {/* Indented and disabled until the
                                                box it qualifies is ticked, so
                                                it reads as part of that award
                                                rather than a third one. */}
                                                <div className="pl-6">
                                                    <FilterCheckbox
                                                        id="award-exclude-cited"
                                                        disabled={
                                                            !filters.completion
                                                                .complete
                                                        }
                                                        checked={
                                                            filters.completion
                                                                .exclude_referenced
                                                        }
                                                        onToggle={() =>
                                                            toggleAward(
                                                                'exclude_referenced',
                                                            )
                                                        }
                                                    >
                                                        Exclude those with a
                                                        reference
                                                    </FilterCheckbox>
                                                </div>
                                            </FilterGroup>

                                            <Separator />

                                            <FilterGroup
                                                title="Activity"
                                                hint="Both is everybody, which is what neither already says."
                                            >
                                                {(
                                                    [
                                                        ['active', 'Active'],
                                                        [
                                                            'inactive',
                                                            'Nothing logged',
                                                        ],
                                                    ] as [Activity, string][]
                                                ).map(([value, label]) => (
                                                    <FilterCheckbox
                                                        key={value}
                                                        id={`activity-${value}`}
                                                        checked={filters.activity.includes(
                                                            value,
                                                        )}
                                                        onToggle={() =>
                                                            narrow({
                                                                activity:
                                                                    toggleIn(
                                                                        filters.activity,
                                                                        value,
                                                                    ),
                                                            })
                                                        }
                                                    >
                                                        {label}
                                                    </FilterCheckbox>
                                                ))}
                                            </FilterGroup>

                                            <Separator />

                                            <FilterGroup
                                                title="Kinds logged"
                                                hint="Ticking two asks for people doing both, not either."
                                            >
                                                {winTypes.map((type) => {
                                                    const {
                                                        label,
                                                        icon: Icon,
                                                        ink,
                                                    } = winTypeMeta[type];

                                                    return (
                                                        <FilterCheckbox
                                                            key={type}
                                                            id={`kind-${type}`}
                                                            checked={filters.kinds.includes(
                                                                type,
                                                            )}
                                                            onToggle={() =>
                                                                narrow({
                                                                    kinds: toggleIn(
                                                                        filters.kinds,
                                                                        type,
                                                                    ),
                                                                })
                                                            }
                                                        >
                                                            <span className="flex items-center gap-1.5">
                                                                <Icon
                                                                    className={cn(
                                                                        'size-3.5',
                                                                        ink,
                                                                    )}
                                                                    aria-hidden
                                                                />
                                                                {label}
                                                            </span>
                                                        </FilterCheckbox>
                                                    );
                                                })}
                                            </FilterGroup>

                                            <Separator />

                                            <FilterGroup title="At least">
                                                <div className="grid grid-cols-2 gap-3">
                                                    <NumberFilter
                                                        id="min-points"
                                                        label="Points"
                                                        value={
                                                            filters.min_points
                                                        }
                                                        onCommit={(
                                                            min_points,
                                                        ) =>
                                                            narrow({
                                                                min_points,
                                                            })
                                                        }
                                                    />
                                                    <NumberFilter
                                                        id="min-days"
                                                        label="Total days"
                                                        value={filters.min_days}
                                                        onCommit={(min_days) =>
                                                            narrow({ min_days })
                                                        }
                                                    />
                                                </div>
                                            </FilterGroup>

                                            <Separator />

                                            <FilterGroup title="Streak">
                                                <FilterCheckbox
                                                    id="streaking"
                                                    checked={filters.streaking}
                                                    onToggle={() =>
                                                        narrow({
                                                            streaking:
                                                                !filters.streaking,
                                                        })
                                                    }
                                                >
                                                    Still running today
                                                </FilterCheckbox>

                                                <NumberFilter
                                                    id="min-streak"
                                                    label="Days in a row, at least"
                                                    value={filters.min_streak}
                                                    onCommit={(min_streak) =>
                                                        narrow({ min_streak })
                                                    }
                                                />
                                            </FilterGroup>
                                        </div>

                                        <div className="border-t border-border p-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="w-full"
                                                disabled={chips.length === 0}
                                                onClick={() => {
                                                    clearFilters();
                                                    setPanelOpen(false);
                                                }}
                                            >
                                                Clear all filters
                                            </Button>
                                        </div>
                                    </PopoverContent>
                                </Popover>
                            </div>

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

                    <ActiveFilterChips filters={chips} onReset={clearFilters} />

                    {errors.to && (
                        <p className="text-caption text-destructive">
                            {errors.to}
                        </p>
                    )}

                    {members.data.length === 0 ? (
                        /* A filter nobody met is not an empty circle, and it is
                           the narrower claim of the two — somebody filtering to
                           finishers and searching a name wants to hear that
                           nobody finished, not that nobody is called that. */
                        chips.length > 0 ? (
                            <EmptyState
                                icon={SlidersHorizontal}
                                title={
                                    search
                                        ? `Nobody matching “${search}” fits these filters`
                                        : 'Nobody fits these filters'
                                }
                                description="Try a wider date range, or clear the filters to see everybody again."
                                action={
                                    <Button
                                        variant="outline"
                                        onClick={() =>
                                            reload({
                                                filters: NO_FILTERS,
                                                search: null,
                                            })
                                        }
                                    >
                                        Clear filters
                                    </Button>
                                }
                            />
                        ) : /* A search that matched nobody is not an empty
                               circle, and saying so would have somebody
                               wondering where everyone went. */
                        search ? (
                            <EmptyState
                                icon={SearchX}
                                title={`Nobody here matches “${search}”`}
                                description="Try part of a name or a username, or clear the search to see everybody again."
                                action={
                                    <Button
                                        variant="outline"
                                        onClick={() => reload({ search: null })}
                                    >
                                        Clear search
                                    </Button>
                                }
                            />
                        ) : (
                            <EmptyState
                                icon={Users}
                                title="Nobody in this circle yet"
                                description="Once people join, what they share here will be counted."
                            />
                        )
                    ) : (
                        <>
                            <div className="overflow-x-auto rounded-card border border-border shadow-card">
                                <table className="w-full border-collapse">
                                    <caption className="sr-only">
                                        Wins by kind, days logged and points for
                                        each member of {circle.name}, from{' '}
                                        {readable(from)} to {readable(to)}
                                    </caption>

                                    <thead>
                                        <tr className="border-b border-border bg-muted/40">
                                            <SortHeader
                                                on="name"
                                                sort={sort}
                                                onSort={(next) =>
                                                    reload({ sort: next })
                                                }
                                                align="left"
                                            >
                                                Member
                                            </SortHeader>

                                            <SortHeader
                                                on="streak"
                                                sort={sort}
                                                onSort={(next) =>
                                                    reload({ sort: next })
                                                }
                                            >
                                                Streak
                                            </SortHeader>

                                            {winTypes.map((type) => {
                                                const {
                                                    label,
                                                    icon: Icon,
                                                    ink,
                                                } = winTypeMeta[type];

                                                return (
                                                    <SortHeader
                                                        key={type}
                                                        on={type}
                                                        sort={sort}
                                                        onSort={(next) =>
                                                            reload({
                                                                sort: next,
                                                            })
                                                        }
                                                    >
                                                        <span className="flex items-center gap-1.5">
                                                            <Icon
                                                                className={cn(
                                                                    'size-3.5',
                                                                    ink,
                                                                )}
                                                                aria-hidden
                                                            />
                                                            {label}
                                                        </span>
                                                    </SortHeader>
                                                );
                                            })}

                                            <SortHeader
                                                on="days"
                                                sort={sort}
                                                onSort={(next) =>
                                                    reload({ sort: next })
                                                }
                                                align="right"
                                            >
                                                Total days
                                            </SortHeader>

                                            <SortHeader
                                                on="points"
                                                sort={sort}
                                                onSort={(next) =>
                                                    reload({ sort: next })
                                                }
                                                align="right"
                                            >
                                                Total points
                                            </SortHeader>
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
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <span
                                                                    className={cn(
                                                                        'tabular-nums',
                                                                        member
                                                                            .wins[
                                                                            type
                                                                        ] === 0
                                                                            ? 'text-muted-foreground/40'
                                                                            : 'font-medium text-foreground',
                                                                    )}
                                                                >
                                                                    {member
                                                                        .wins[
                                                                        type
                                                                    ] === 0
                                                                        ? '—'
                                                                        : member
                                                                              .wins[
                                                                              type
                                                                          ]}
                                                                </span>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                {`${member.wins[type]} ${winTypeMeta[
                                                                    type
                                                                ].label.toLowerCase()} ${
                                                                    member.wins[
                                                                        type
                                                                    ] === 1
                                                                        ? 'win'
                                                                        : 'wins'
                                                                }`}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    </td>
                                                ))}

                                                <td className="px-4 py-3 text-right text-sm font-semibold tabular-nums">
                                                    {member.total}
                                                </td>

                                                <td className="px-4 py-3 text-right text-sm font-semibold tabular-nums">
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <span
                                                                className={cn(
                                                                    'tabular-nums',
                                                                    member.total_points ===
                                                                        0 &&
                                                                        'font-normal text-muted-foreground/40',
                                                                )}
                                                            >
                                                                {member.total_points ===
                                                                0
                                                                    ? '—'
                                                                    : member.total_points}
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {member.total_points ===
                                                            1
                                                                ? '1 point — one kind, on one day'
                                                                : `${member.total_points} points, one per kind per day`}
                                                        </TooltipContent>
                                                    </Tooltip>
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
                                counts once. Points go by kind: each kind is
                                worth a point on a day it was logged, however
                                often it was logged that day — so a day with all
                                three is worth three, and posting the same kind
                                again is worth nothing further. The streak
                                counts days in a row with a win, wherever it was
                                shared.
                            </p>

                            <Pagination page={members} label="members" />
                        </>
                    )}
                </div>
            </CircleLayout>
        </>
    );
}
