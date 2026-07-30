import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

/**
 * The strip above every page: the control that folds the rail away, and the
 * trail to here when a page has one.
 */
export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-2 border-b border-border bg-background/80 px-6 backdrop-blur sm:px-8">
            <SidebarTrigger className="-ml-1.5" />

            {breadcrumbs.length > 0 && <Breadcrumbs breadcrumbs={breadcrumbs} />}
        </header>
    );
}
