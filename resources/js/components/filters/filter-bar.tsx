import { SlidersHorizontal, X } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import SearchField from '@/components/filters/search-field';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type ActiveFilter = {
    /** Stable key, used for React and for the clear button label. */
    key: string;
    /** What was filtered on, e.g. "Category". */
    label: string;
    /** The human-readable value, e.g. "Sleep". */
    value: string;
    onClear: () => void;
};

/**
 * The filter surface shared by the index screens.
 *
 * Search stays visible because it is the filter people reach for first;
 * everything else lives behind a disclosure so the page opens calm. Whatever
 * is applied is echoed back as chips, so a narrowed list never looks like an
 * empty one.
 */
export default function FilterBar({
    search,
    onSearch,
    searchPlaceholder,
    activeFilters,
    onReset,
    perPage,
    perPageOptions,
    onPerPageChange,
    children,
}: {
    search: string | null;
    onSearch: (value: string | null) => void;
    searchPlaceholder: string;
    activeFilters: ActiveFilter[];
    onReset: () => void;
    perPage: number;
    perPageOptions: number[];
    onPerPageChange: (value: number) => void;
    children: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const advancedCount = activeFilters.filter(
        (filter) => filter.key !== 'search',
    ).length;

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="rounded-card border border-border bg-card p-3 shadow-card"
        >
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <SearchField
                    value={search}
                    onSearch={onSearch}
                    placeholder={searchPlaceholder}
                />

                <div className="flex items-center gap-2">
                    <CollapsibleTrigger asChild>
                        <Button variant="outline" data-test="toggle-filters">
                            <SlidersHorizontal className="size-4" />
                            Filters
                            {advancedCount > 0 && (
                                <span className="ml-0.5 flex size-[18px] items-center justify-center rounded-full bg-primary text-[10px] font-semibold text-primary-foreground tabular-nums">
                                    {advancedCount}
                                </span>
                            )}
                        </Button>
                    </CollapsibleTrigger>

                    <Select
                        value={String(perPage)}
                        onValueChange={(value) =>
                            onPerPageChange(Number(value))
                        }
                    >
                        <SelectTrigger
                            className="w-auto"
                            aria-label="Rows per page"
                        >
                            <SelectValue />
                        </SelectTrigger>

                        <SelectContent>
                            {perPageOptions.map((option) => (
                                <SelectItem key={option} value={String(option)}>
                                    {option} rows
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <CollapsibleContent>
                <div className="mt-3 grid gap-3 border-t border-border pt-3 sm:grid-cols-2 lg:grid-cols-4">
                    {children}
                </div>
            </CollapsibleContent>

            {activeFilters.length > 0 && (
                <div className="mt-3 flex flex-wrap items-center gap-1.5 border-t border-border pt-3">
                    <span className="text-[11px] font-semibold tracking-[0.06em] text-muted-foreground uppercase">
                        Filtered by
                    </span>

                    {activeFilters.map((filter) => (
                        <button
                            key={filter.key}
                            type="button"
                            onClick={filter.onClear}
                            className="group inline-flex items-center gap-1.5 rounded-sm border border-border bg-secondary py-0.5 pr-1.5 pl-2 text-[12px] transition-colors hover:border-destructive/40 hover:text-destructive focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <span className="text-muted-foreground group-hover:text-destructive">
                                {filter.label}
                            </span>
                            <span className="font-medium">{filter.value}</span>
                            <X className="size-3" />
                            <span className="sr-only">
                                Clear {filter.label} filter
                            </span>
                        </button>
                    ))}

                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={onReset}
                        data-test="clear-filters"
                        className="h-6 px-2 text-[12px] text-muted-foreground"
                    >
                        Clear all
                    </Button>
                </div>
            )}
        </Collapsible>
    );
}

/**
 * One labelled control inside the advanced filter grid.
 */
export function FilterField({
    label,
    htmlFor,
    children,
}: {
    label: string;
    htmlFor?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1.5">
            <Label
                htmlFor={htmlFor}
                className="text-caption text-muted-foreground"
            >
                {label}
            </Label>

            {children}
        </div>
    );
}
