import { CalendarDays } from 'lucide-react';
import { useState } from 'react';
import type { DateRange } from 'react-day-picker';

import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/** The spans offered as shortcuts, counted back from today inclusive. */
const PRESETS = [7, 30, 90];

/**
 * Read a `YYYY-MM-DD` day as a date in the reader's own timezone.
 *
 * The explicit midnight matters: `new Date('2026-07-30')` is parsed as UTC and
 * lands on the 29th for anyone west of Greenwich, while the same string with a
 * time on it is parsed locally, which is what a calendar day means here.
 */
function parseDay(value: string): Date | undefined {
    const parsed = new Date(`${value}T00:00:00`);

    return Number.isNaN(parsed.getTime()) ? undefined : parsed;
}

/**
 * Write a date back as `YYYY-MM-DD`, reading the local calendar day.
 *
 * Deliberately not `toISOString`, which converts to UTC before it formats and
 * so reports the day before for every reader east of Greenwich — the range
 * would quietly shift by a day each time it was applied.
 */
function formatDay(date: Date): string {
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

/** The day, written the way this reader's locale writes days. */
function readable(date: Date): string {
    return date.toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

/** Midnight today, the latest day worth counting wins up to. */
function today(): Date {
    const now = new Date();

    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
}

/**
 * One control for both ends of a date range.
 *
 * The two ends are picked on a single calendar rather than typed into a field
 * each, so the span is chosen by looking at it. Nothing is committed until
 * Apply: a half-made range — the first click of two — would otherwise reload
 * the page against a range the reader had not finished describing.
 */
export function DateRangePicker({
    from,
    to,
    onApply,
    invalid = false,
    className,
}: {
    from: string;
    to: string;
    onApply: (from: string, to: string) => void;
    invalid?: boolean;
    className?: string;
}) {
    const applied = { from: parseDay(from), to: parseDay(to) };

    const [open, setOpen] = useState(false);
    const [draft, setDraft] = useState<DateRange | undefined>(applied);

    const label =
        applied.from && applied.to
            ? `${readable(applied.from)} – ${readable(applied.to)}`
            : 'Pick a date range';

    const choosePreset = (days: number) => {
        const end = today();
        const start = new Date(end);
        start.setDate(start.getDate() - (days - 1));

        setDraft({ from: start, to: end });
    };

    const apply = () => {
        if (!draft?.from || !draft.to) {
            return;
        }

        onApply(formatDay(draft.from), formatDay(draft.to));
        setOpen(false);
    };

    /*
     * Seed the draft from what is actually applied on the way in and out, so a
     * half-made range — the first click of two, then Esc — is not still sitting
     * there the next time the calendar opens.
     */
    const toggle = (next: boolean) => {
        setDraft(applied);
        setOpen(next);
    };

    return (
        <Popover open={open} onOpenChange={toggle}>
            <PopoverTrigger asChild>
                <Button
                    id="range"
                    type="button"
                    variant="outline"
                    size="lg"
                    aria-invalid={invalid}
                    className={cn(
                        'justify-start gap-2 font-normal tabular-nums',
                        className,
                    )}
                >
                    <CalendarDays className="text-muted-foreground" />
                    {label}
                </Button>
            </PopoverTrigger>

            <PopoverContent align="end" className="w-auto p-0">
                <Calendar
                    mode="range"
                    autoFocus
                    numberOfMonths={2}
                    defaultMonth={applied.from ?? today()}
                    selected={draft}
                    onSelect={setDraft}
                    // A tracker counts wins already shared, so a range running
                    // into next week could only ever come back empty.
                    disabled={{ after: today() }}
                    className="p-3 pb-0"
                />

                <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border p-3">
                    <div className="flex flex-wrap gap-1">
                        {PRESETS.map((days) => (
                            <Button
                                key={days}
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={() => choosePreset(days)}
                            >
                                Last {days} days
                            </Button>
                        ))}
                    </div>

                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => toggle(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            onClick={apply}
                            disabled={!draft?.from || !draft.to}
                        >
                            Apply
                        </Button>
                    </div>
                </div>
            </PopoverContent>
        </Popover>
    );
}
