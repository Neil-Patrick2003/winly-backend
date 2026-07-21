import { Search } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Input } from '@/components/ui/input';

const DEBOUNCE_MS = 300;

/**
 * A search box that reports upward on a debounce.
 *
 * The server value wins whenever it changes for a reason other than our own
 * request — a reset, or a back/forward navigation. The `sent` guard stops that
 * from clobbering characters typed while a request is still in flight.
 */
export default function SearchField({
    value,
    onSearch,
    placeholder,
    label = 'Search',
}: {
    value: string | null;
    onSearch: (value: string | null) => void;
    placeholder: string;
    label?: string;
}) {
    const serverValue = value ?? '';

    const [search, setSearch] = useState(serverValue);
    const [synced, setSynced] = useState(serverValue);
    const [sent, setSent] = useState(serverValue);

    if (serverValue !== synced) {
        setSynced(serverValue);

        if (serverValue !== sent) {
            setSent(serverValue);
            setSearch(serverValue);
        }
    }

    useEffect(() => {
        if (search === sent) {
            return;
        }

        const timeout = setTimeout(() => {
            setSent(search);
            onSearch(search || null);
        }, DEBOUNCE_MS);

        return () => clearTimeout(timeout);
    }, [search, sent, onSearch]);

    return (
        <div className="relative min-w-0 flex-1">
            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />

            <Input
                type="search"
                aria-label={label}
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder={placeholder}
                autoComplete="off"
                className="pl-8"
            />
        </div>
    );
}
