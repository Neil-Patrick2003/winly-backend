import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

export default function Pagination<T>({
    page,
    only,
}: {
    page: Paginated<T>;
    only?: string[];
}) {
    if (page.last_page <= 1) {
        return null;
    }

    // Laravel puts "Previous" first and "Next" last; the rest are page numbers.
    const numbered = page.links.slice(1, -1);

    return (
        <nav
            aria-label="Pagination"
            className="flex flex-wrap items-center justify-between gap-3 px-0.5"
        >
            <p className="text-[13px] text-muted-foreground">
                Showing <span className="font-medium">{page.from ?? 0}</span>–
                <span className="font-medium">{page.to ?? 0}</span> of{' '}
                <span className="font-medium">{page.total}</span>
            </p>

            <div className="flex items-center gap-1">
                <Button
                    variant="outline"
                    size="icon"
                    asChild={Boolean(page.prev_page_url)}
                    disabled={!page.prev_page_url}
                    aria-label="Previous page"
                >
                    {page.prev_page_url ? (
                        <Link
                            href={page.prev_page_url}
                            only={only}
                            preserveScroll
                            preserveState
                        >
                            <ChevronLeft className="size-4" />
                        </Link>
                    ) : (
                        <ChevronLeft className="size-4" />
                    )}
                </Button>

                {numbered.map((link, position) =>
                    link.url ? (
                        <Button
                            key={`${link.label}-${position}`}
                            variant={link.active ? 'default' : 'ghost'}
                            size="icon"
                            asChild
                            aria-current={link.active ? 'page' : undefined}
                        >
                            <Link
                                href={link.url}
                                only={only}
                                preserveScroll
                                preserveState
                            >
                                {link.label}
                            </Link>
                        </Button>
                    ) : (
                        <span
                            key={`${link.label}-${position}`}
                            className={cn(
                                'px-2 text-sm text-muted-foreground select-none',
                            )}
                        >
                            {link.label}
                        </span>
                    ),
                )}

                <Button
                    variant="outline"
                    size="icon"
                    asChild={Boolean(page.next_page_url)}
                    disabled={!page.next_page_url}
                    aria-label="Next page"
                >
                    {page.next_page_url ? (
                        <Link
                            href={page.next_page_url}
                            only={only}
                            preserveScroll
                            preserveState
                        >
                            <ChevronRight className="size-4" />
                        </Link>
                    ) : (
                        <ChevronRight className="size-4" />
                    )}
                </Button>
            </div>
        </nav>
    );
}
