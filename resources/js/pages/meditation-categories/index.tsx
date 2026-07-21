import { Head } from '@inertiajs/react';
import { AudioLines, Pencil, Plus, SearchX, Sparkles } from 'lucide-react';
import { useState } from 'react';
import { index } from '@/actions/App/Http/Controllers/MeditationCategoryController';
import CategoryDialog from '@/components/meditation-categories/category-dialog';
import type { CategoryDraft } from '@/components/meditation-categories/category-dialog';
import CategoryFilterBar from '@/components/meditation-categories/category-filter-bar';
import DeleteCategoryDialog from '@/components/meditation-categories/delete-category-dialog';
import MetricStrip from '@/components/metric-strip';
import PageHeader from '@/components/page-header';
import Pagination from '@/components/pagination';
import SortableHeader from '@/components/sortable-header';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useMeditationCategoryFilters } from '@/hooks/use-meditation-category-filters';
import { meditationIcon } from '@/lib/meditation-icons';
import type {
    MeditationCategory,
    MeditationCategoryFilters as Filters,
    Paginated,
} from '@/types';

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

export default function MeditationCategoryIndex({
    categories,
    filters,
    totalCount,
    meditationCount,
    iconOptions,
    perPageOptions,
}: {
    categories: Paginated<MeditationCategory>;
    filters: Filters;
    totalCount: number;
    meditationCount: number;
    iconOptions: string[];
    perPageOptions: number[];
}) {
    const { sortBy, reset, isFiltered } = useMeditationCategoryFilters(filters);

    const [editing, setEditing] = useState<CategoryDraft | null>(null);
    const [dialogOpen, setDialogOpen] = useState(false);

    function openCreate() {
        setEditing(null);
        setDialogOpen(true);
    }

    function openEdit(category: CategoryDraft) {
        setEditing(category);
        setDialogOpen(true);
    }

    return (
        <>
            <Head title="Meditation categories" />

            <div className="mx-auto flex w-full max-w-[1400px] flex-col gap-5 px-4 py-5 sm:px-6">
                <PageHeader
                    eyebrow="Library"
                    title="Meditation categories"
                    description="The shelves your sessions are filed on. Every meditation belongs to exactly one."
                    actions={
                        <Button onClick={openCreate}>
                            <Plus />
                            New category
                        </Button>
                    }
                />

                <MetricStrip
                    metrics={[
                        {
                            label: 'Categories',
                            value: totalCount,
                            icon: Sparkles,
                        },
                        {
                            label: 'Sessions filed',
                            value: meditationCount,
                            icon: AudioLines,
                        },
                    ]}
                />

                <section className="flex flex-col gap-3">
                    <CategoryFilterBar
                        filters={filters}
                        perPageOptions={perPageOptions}
                    />

                    <div className="overflow-hidden rounded-card border border-border bg-card shadow-card">
                        <Table>
                            <TableHeader>
                                <TableRow className="border-border hover:bg-transparent">
                                    <SortableHeader
                                        column="name"
                                        label="Category"
                                        sort={filters.sort}
                                        direction={filters.direction}
                                        onSort={sortBy}
                                    />

                                    <TableHead className="hidden md:table-cell">
                                        Description
                                    </TableHead>

                                    <SortableHeader
                                        column="created_at"
                                        label="Created"
                                        sort={filters.sort}
                                        direction={filters.direction}
                                        onSort={sortBy}
                                        className="hidden sm:table-cell"
                                    />

                                    <TableHead className="w-24 text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {categories.data.map((category) => {
                                    const IconComponent = meditationIcon(
                                        category.icon,
                                    );

                                    return (
                                        <TableRow
                                            key={category.id}
                                            className="border-border"
                                        >
                                            <TableCell className="py-2">
                                                <div className="flex items-center gap-3">
                                                    <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-meditation-bg text-meditation-icon">
                                                        <IconComponent className="size-4" />
                                                    </span>

                                                    <span className="font-medium">
                                                        {category.name}
                                                    </span>
                                                </div>
                                            </TableCell>

                                            <TableCell className="hidden max-w-md py-2 md:table-cell">
                                                <p className="line-clamp-1 text-muted-foreground">
                                                    {category.description ??
                                                        '—'}
                                                </p>
                                            </TableCell>

                                            <TableCell className="hidden py-2 whitespace-nowrap text-muted-foreground tabular-nums sm:table-cell">
                                                {dateFormatter.format(
                                                    new Date(
                                                        category.created_at,
                                                    ),
                                                )}
                                            </TableCell>

                                            <TableCell className="py-2">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={() =>
                                                            openEdit(category)
                                                        }
                                                        aria-label={`Edit ${category.name}`}
                                                        data-test={`edit-category-${category.id}`}
                                                    >
                                                        <Pencil className="size-4" />
                                                    </Button>

                                                    <DeleteCategoryDialog
                                                        category={category}
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}

                                {categories.data.length === 0 && (
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell colSpan={4}>
                                            <div className="flex flex-col items-center gap-2.5 py-14 text-center">
                                                <span className="flex size-10 items-center justify-center rounded-full bg-meditation-bg text-meditation-icon">
                                                    {isFiltered ? (
                                                        <SearchX className="size-[18px]" />
                                                    ) : (
                                                        <Sparkles className="size-[18px]" />
                                                    )}
                                                </span>

                                                <div className="space-y-1">
                                                    <p className="font-semibold">
                                                        {isFiltered
                                                            ? 'No categories match these filters'
                                                            : 'No categories yet'}
                                                    </p>

                                                    <p className="text-[13px] text-muted-foreground">
                                                        {isFiltered
                                                            ? 'Try a different search term or widen the date range.'
                                                            : 'Create the first one to start organising sessions.'}
                                                    </p>
                                                </div>

                                                {isFiltered ? (
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={reset}
                                                    >
                                                        Clear filters
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        size="sm"
                                                        onClick={openCreate}
                                                    >
                                                        <Plus />
                                                        New category
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <Pagination
                        page={categories}
                        only={['categories', 'filters']}
                    />
                </section>
            </div>

            <CategoryDialog
                category={editing}
                iconOptions={iconOptions}
                open={dialogOpen}
                onOpenChange={setDialogOpen}
            />
        </>
    );
}

MeditationCategoryIndex.layout = {
    breadcrumbs: [{ title: 'Meditation categories', href: index() }],
};
