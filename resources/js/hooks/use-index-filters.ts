import { router } from '@inertiajs/react';
import { useCallback, useMemo } from 'react';

export type FilterValue = string | number | null;

type UseIndexFiltersOptions<F> = {
    /** The current filter values, straight from the server. */
    filters: F;
    /** Builds the index URL for a set of query parameters. */
    url: (options: { query: Record<string, FilterValue> }) => string;
    /** Values that are dropped from the URL because they are the default. */
    defaults: Record<string, FilterValue>;
    /** Props to reload; everything else is left untouched. */
    only: string[];
    /** Filters that make the list "narrowed", for empty states and reset buttons. */
    activeKeys: (keyof F)[];
};

/**
 * Shared plumbing for filterable index screens.
 *
 * Requests reload only the props that can change, keep the scroll position,
 * and replace the history entry so typing in a search box does not bury the
 * back button under one entry per keystroke.
 *
 * Pass `defaults`, `only` and `activeKeys` as module-level constants: they are
 * dependencies of the returned callbacks, so inline literals would hand back a
 * new function on every render.
 */
export function useIndexFilters<F extends Record<string, FilterValue>>({
    filters,
    url,
    defaults,
    only,
    activeKeys,
}: UseIndexFiltersOptions<F>) {
    const visit = useCallback(
        (next: Record<string, FilterValue>) => {
            const query: Record<string, FilterValue> = { ...next };

            // Drop defaults and empty values so the URL stays readable.
            for (const [key, value] of Object.entries(query)) {
                if (value === null || value === '' || value === defaults[key]) {
                    delete query[key];
                }
            }

            router.get(url({ query }), undefined, {
                only,
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [defaults, only, url],
    );

    const setFilter = useCallback(
        (key: keyof F, value: FilterValue) => {
            visit({ ...filters, [key]: value, page: null });
        },
        [filters, visit],
    );

    const sortBy = useCallback(
        (column: string) => {
            const direction =
                filters.sort === column && filters.direction === 'asc'
                    ? 'desc'
                    : 'asc';

            visit({ ...filters, sort: column, direction, page: null });
        },
        [filters, visit],
    );

    const reset = useCallback(() => visit({}), [visit]);

    const isFiltered = useMemo(
        () =>
            activeKeys.some(
                (key) => filters[key] !== null && filters[key] !== '',
            ),
        [activeKeys, filters],
    );

    return { setFilter, sortBy, reset, isFiltered };
}
