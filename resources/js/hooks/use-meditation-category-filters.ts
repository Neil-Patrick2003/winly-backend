import { index } from '@/actions/App/Http/Controllers/MeditationCategoryController';
import { useIndexFilters } from '@/hooks/use-index-filters';
import type { FilterValue } from '@/hooks/use-index-filters';
import type { MeditationCategoryFilters } from '@/types';

const DEFAULTS: Record<string, FilterValue> = {
    search: null,
    sort: 'name',
    direction: 'asc',
    from: null,
    to: null,
    per_page: 10,
};

const ONLY = ['categories', 'filters'];

const ACTIVE_KEYS: (keyof MeditationCategoryFilters)[] = [
    'search',
    'from',
    'to',
];

export function useMeditationCategoryFilters(
    filters: MeditationCategoryFilters,
) {
    return useIndexFilters({
        filters,
        url: index.url,
        defaults: DEFAULTS,
        only: ONLY,
        activeKeys: ACTIVE_KEYS,
    });
}
