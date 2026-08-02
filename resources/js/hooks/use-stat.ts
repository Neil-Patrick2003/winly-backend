import { useCallback, useEffect, useState } from 'react';

type Stat<T> = {
    data: T | null;
    isLoading: boolean;
    error: string | null;
    reload: () => void;
};

type Result<T> = {
    /** Which request this settled from, so a stale reply cannot overwrite a fresh one. */
    key: string;
    data: T | null;
    error: string | null;
};

/**
 * Fetch one owner console statistic from its own endpoint.
 *
 * Each tile owns a request rather than sharing a page payload, so a slow
 * aggregate holds up one number instead of the whole console, and a failure
 * shows up in the tile it belongs to rather than taking the page down.
 *
 * Loading is derived from whether the settled result belongs to the request
 * currently in flight, rather than flipped on at the top of the effect — the
 * flag would be a second source of truth that renders once before the fetch
 * even starts.
 */
export function useStat<T>(url: string): Stat<T> {
    const [attempt, setAttempt] = useState(0);
    const [result, setResult] = useState<Result<T> | null>(null);

    const key = `${url}#${attempt}`;

    useEffect(() => {
        const controller = new AbortController();

        fetch(url, {
            signal: controller.signal,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Request failed (${response.status})`);
                }

                return response.json() as Promise<T>;
            })
            .then((payload) => setResult({ key, data: payload, error: null }))
            .catch((cause: unknown) => {
                // An abort is this effect cleaning up after itself, not a fault.
                if (controller.signal.aborted) {
                    return;
                }

                setResult({
                    key,
                    data: null,
                    error:
                        cause instanceof Error
                            ? cause.message
                            : 'Could not load this figure.',
                });
            });

        return () => controller.abort();
    }, [key, url]);

    const settled = result?.key === key ? result : null;

    return {
        data: settled?.data ?? null,
        error: settled?.error ?? null,
        isLoading: settled === null,
        reload: useCallback(() => setAttempt((count) => count + 1), []),
    };
}
