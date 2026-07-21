import FilterBar, { FilterField } from '@/components/filters/filter-bar';
import type { ActiveFilter } from '@/components/filters/filter-bar';
import { Input } from '@/components/ui/input';
import { useMeditationCategoryFilters } from '@/hooks/use-meditation-category-filters';
import type { MeditationCategoryFilters } from '@/types';

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

function formatDate(value: string): string {
    return dateFormatter.format(new Date(`${value}T00:00:00`));
}

export default function CategoryFilterBar({
    filters,
    perPageOptions,
}: {
    filters: MeditationCategoryFilters;
    perPageOptions: number[];
}) {
    const { setFilter, reset } = useMeditationCategoryFilters(filters);

    const activeFilters: ActiveFilter[] = [];

    if (filters.search) {
        activeFilters.push({
            key: 'search',
            label: 'Search',
            value: filters.search,
            onClear: () => setFilter('search', null),
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
            searchPlaceholder="Search categories by name or description"
            activeFilters={activeFilters}
            onReset={reset}
            perPage={filters.per_page}
            perPageOptions={perPageOptions}
            onPerPageChange={(value) => setFilter('per_page', value)}
        >
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
