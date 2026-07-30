import { useEffect, useState } from 'react';

/**
 * A value that only settles once it has stopped changing.
 *
 * Typing into a search box changes it on every keystroke; this holds the change
 * back until the typing pauses, so the work behind it runs once for a word
 * rather than once for each letter of it.
 */
export function useDebouncedValue<T>(value: T, delay = 300): T {
    const [settled, setSettled] = useState(value);

    useEffect(() => {
        const timer = setTimeout(() => setSettled(value), delay);

        return () => clearTimeout(timer);
    }, [value, delay]);

    return settled;
}
