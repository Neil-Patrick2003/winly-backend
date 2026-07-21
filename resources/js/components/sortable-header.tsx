import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import { TableHead } from '@/components/ui/table';
import { cn } from '@/lib/utils';

export default function SortableHeader({
    column,
    label,
    sort,
    direction,
    onSort,
    className,
}: {
    column: string;
    label: string;
    sort: string;
    direction: 'asc' | 'desc';
    onSort: (column: string) => void;
    className?: string;
}) {
    const isActive = sort === column;
    const ariaSort = isActive
        ? direction === 'asc'
            ? 'ascending'
            : 'descending'
        : 'none';

    const SortIcon = !isActive
        ? ChevronsUpDown
        : direction === 'asc'
          ? ArrowUp
          : ArrowDown;

    return (
        <TableHead aria-sort={ariaSort} className={className}>
            <button
                type="button"
                onClick={() => onSort(column)}
                className={cn(
                    'inline-flex items-center gap-1.5 rounded transition-colors hover:text-foreground',
                    'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                    isActive && 'text-foreground',
                )}
                data-test={`sort-${column}`}
            >
                {label}
                <SortIcon className="size-3.5" />
            </button>
        </TableHead>
    );
}
