import { index } from '@/actions/App/Http/Controllers/MeditationController';
import { useIndexFilters } from '@/hooks/use-index-filters';
import type { FilterValue } from '@/hooks/use-index-filters';
import type { MeditationFilters } from '@/types';

const DEFAULTS: Record<string, FilterValue> = {
    search: null,
    category_id: null,
    sort: 'title',
    direction: 'asc',
    min_duration: null,
    max_duration: null,
    from: null,
    to: null,
    per_page: 10,
};

const ONLY = ['meditations', 'filters'];

const ACTIVE_KEYS: (keyof MeditationFilters)[] = [
    'search',
    'category_id',
    'min_duration',
    'max_duration',
    'from',
    'to',
];

export function useMeditationFilters(filters: MeditationFilters) {
    return useIndexFilters({
        filters,
        url: index.url,
        defaults: DEFAULTS,
        only: ONLY,
        activeKeys: ACTIVE_KEYS,
    });
}
