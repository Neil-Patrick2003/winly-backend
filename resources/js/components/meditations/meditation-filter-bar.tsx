import FilterBar, { FilterField } from '@/components/filters/filter-bar';
import type { ActiveFilter } from '@/components/filters/filter-bar';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useMeditationFilters } from '@/hooks/use-meditation-filters';
import type { MeditationCategoryOption, MeditationFilters } from '@/types';

const ALL_CATEGORIES = 'all';

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

function formatDate(value: string): string {
    return dateFormatter.format(new Date(`${value}T00:00:00`));
}

export default function MeditationFilterBar({
    filters,
    categories,
    perPageOptions,
}: {
    filters: MeditationFilters;
    categories: MeditationCategoryOption[];
    perPageOptions: number[];
}) {
    const { setFilter, reset } = useMeditationFilters(filters);

    const activeFilters: ActiveFilter[] = [];

    if (filters.search) {
        activeFilters.push({
            key: 'search',
            label: 'Search',
            value: filters.search,
            onClear: () => setFilter('search', null),
        });
    }

    if (filters.category_id) {
        const category = categories.find(
            (option) => option.id === filters.category_id,
        );

        activeFilters.push({
            key: 'category_id',
            label: 'Category',
            value: category?.label ?? 'Unknown',
            onClear: () => setFilter('category_id', null),
        });
    }

    if (filters.min_duration !== null) {
        activeFilters.push({
            key: 'min_duration',
            label: 'At least',
            value: `${filters.min_duration} min`,
            onClear: () => setFilter('min_duration', null),
        });
    }

    if (filters.max_duration !== null) {
        activeFilters.push({
            key: 'max_duration',
            label: 'At most',
            value: `${filters.max_duration} min`,
            onClear: () => setFilter('max_duration', null),
        });
    }

    if (filters.from) {
        activeFilters.push({
            key: 'from',
            label: 'Created after',
            value: formatDate(filters.from),
            onClear: () => setFilter('from', null),
        });
    }

    if (filters.to) {
        activeFilters.push({
            key: 'to',
            label: 'Created before',
            value: formatDate(filters.to),
            onClear: () => setFilter('to', null),
        });
    }

    return (
        <FilterBar
            search={filters.search}
            onSearch={(value) => setFilter('search', value)}
            searchPlaceholder="Search sessions by title or description"
            activeFilters={activeFilters}
            onReset={reset}
            perPage={filters.per_page}
            perPageOptions={perPageOptions}
            onPerPageChange={(value) => setFilter('per_page', value)}
        >
            <FilterField label="Category" htmlFor="category">
                <Select
                    value={
                        filters.category_id
                            ? String(filters.category_id)
                            : ALL_CATEGORIES
                    }
                    onValueChange={(value) =>
                        setFilter(
                            'category_id',
                            value === ALL_CATEGORIES ? null : Number(value),
                        )
                    }
                >
                    <SelectTrigger id="category" className="w-full">
                        <SelectValue />
                    </SelectTrigger>

                    <SelectContent>
                        <SelectItem value={ALL_CATEGORIES}>
                            All categories
                        </SelectItem>

                        {categories.map((category) => (
                            <SelectItem
                                key={category.id}
                                value={String(category.id)}
                            >
                                {category.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </FilterField>

            <FilterField label="Length (minutes)">
                <div className="flex items-center gap-2">
                    <Input
                        // Remount when the server value changes so a reset
                        // clears the box the user typed into.
                        key={`min-${filters.min_duration ?? ''}`}
                        type="number"
                        min={0}
                        inputMode="numeric"
                        aria-label="Minimum length in minutes"
                        placeholder="Min"
                        defaultValue={filters.min_duration ?? ''}
                        onBlur={(event) =>
                            setFilter(
                                'min_duration',
                                event.target.value
                                    ? Number(event.target.value)
                                    : null,
                            )
                        }
                    />

                    <span className="text-muted-foreground">–</span>

                    <Input
                        key={`max-${filters.max_duration ?? ''}`}
                        type="number"
                        min={0}
                        inputMode="numeric"
                        aria-label="Maximum length in minutes"
                        placeholder="Max"
                        defaultValue={filters.max_duration ?? ''}
                        onBlur={(event) =>
                            setFilter(
                                'max_duration',
                                event.target.value
                                    ? Number(event.target.value)
                                    : null,
                            )
                        }
                    />
                </div>
            </FilterField>

            <FilterField label="Created from" htmlFor="from">
                <Input
                    id="from"
                    type="date"
                    value={filters.from ?? ''}
                    max={filters.to ?? undefined}
                    onChange={(event) =>
                        setFilter('from', event.target.value || null)
                    }
                />
            </FilterField>

            <FilterField label="Created to" htmlFor="to">
                <Input
                    id="to"
                    type="date"
                    value={filters.to ?? ''}
                    min={filters.from ?? undefined}
                    onChange={(event) =>
                        setFilter('to', event.target.value || null)
                    }
                />
            </FilterField>
        </FilterBar>
    );
}
