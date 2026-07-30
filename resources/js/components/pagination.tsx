import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

/**
 * Previous and next across a paginated list, with the range being shown.
 *
 * Numbered links are left out on purpose: page seven of a member list is not a
 * place anybody is trying to get to, and the numbers crowd out the count, which
 * is the part people actually read.
 *
 * Renders nothing when everything already fits on one page.
 */
export function Pagination<T>({
    page,
    label = 'results',
}: {
    page: Paginated<T>;
    label?: string;
}) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className="flex items-center justify-between gap-4 pt-2"
            aria-label="Pagination"
        >
            <p className="text-caption text-muted-foreground tabular-nums">
                {page.from}–{page.to} of {page.total} {label}
            </p>

            <div className="flex gap-2">
                <Button variant="outline" size="sm" asChild={!!page.prev_page_url} disabled={!page.prev_page_url}>
                    {page.prev_page_url ? (
                        <Link href={page.prev_page_url} preserveScroll>
                            <ChevronLeft className="size-4" />
                            Previous
                        </Link>
                    ) : (
                        <span>
                            <ChevronLeft className="size-4" />
                            Previous
                        </span>
                    )}
                </Button>

                <Button variant="outline" size="sm" asChild={!!page.next_page_url} disabled={!page.next_page_url}>
                    {page.next_page_url ? (
                        <Link href={page.next_page_url} preserveScroll>
                            Next
                            <ChevronRight className="size-4" />
                        </Link>
                    ) : (
                        <span>
                            Next
                            <ChevronRight className="size-4" />
                        </span>
                    )}
                </Button>
            </div>
        </nav>
    );
}
