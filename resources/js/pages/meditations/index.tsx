import { Head, Link } from '@inertiajs/react';
import {
    AudioLines,
    Clock,
    Pencil,
    Plus,
    SearchX,
    Sparkles,
    Video,
    Waves,
} from 'lucide-react';
import {
    create,
    edit,
    index,
} from '@/actions/App/Http/Controllers/MeditationController';
import DeleteMeditationDialog from '@/components/meditations/delete-meditation-dialog';
import MeditationFilterBar from '@/components/meditations/meditation-filter-bar';
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
import { useMeditationFilters } from '@/hooks/use-meditation-filters';
import { meditationIcon } from '@/lib/meditation-icons';
import type {
    Meditation,
    MeditationCategoryOption,
    MeditationFilters,
    Paginated,
} from '@/types';

const dateFormatter = new Intl.DateTimeFormat(undefined, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

function formatDuration(minutes: number): string {
    if (minutes < 60) {
        return `${minutes} min`;
    }

    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;

    return remainder === 0 ? `${hours} hr` : `${hours} hr ${remainder} min`;
}

export default function MeditationIndex({
    meditations,
    filters,
    categories,
    totalCount,
    totalMinutes,
    perPageOptions,
}: {
    meditations: Paginated<Meditation>;
    filters: MeditationFilters;
    categories: MeditationCategoryOption[];
    totalCount: number;
    totalMinutes: number;
    perPageOptions: number[];
}) {
    const { sortBy, reset, isFiltered } = useMeditationFilters(filters);

    return (
        <>
            <Head title="Meditations" />

            <div className="mx-auto flex w-full max-w-[1400px] flex-col gap-5 px-4 py-5 sm:px-6">
                <PageHeader
                    eyebrow="Library"
                    title="Meditations"
                    description="Every guided session in the app, and the category it is filed under."
                    actions={
                        <Button asChild>
                            <Link href={create()} prefetch>
                                <Plus />
                                New meditation
                            </Link>
                        </Button>
                    }
                />

                <MetricStrip
                    metrics={[
                        {
                            label: 'Sessions',
                            value: totalCount,
                            icon: AudioLines,
                        },
                        {
                            label: 'Total length',
                            value: totalMinutes,
                            unit: 'min',
                            icon: Clock,
                        },
                        {
                            label: 'Categories',
                            value: categories.length,
                            icon: Sparkles,
                        },
                    ]}
                />

                <section className="flex flex-col gap-3">
                    <MeditationFilterBar
                        filters={filters}
                        categories={categories}
                        perPageOptions={perPageOptions}
                    />

                    <div className="overflow-hidden rounded-card border border-border bg-card shadow-card">
                        <Table>
                            <TableHeader>
                                <TableRow className="border-border hover:bg-transparent">
                                    <SortableHeader
                                        column="title"
                                        label="Session"
                                        sort={filters.sort}
                                        direction={filters.direction}
                                        onSort={sortBy}
                                    />

                                    <TableHead className="hidden sm:table-cell">
                                        Category
                                    </TableHead>

                                    <SortableHeader
                                        column="duration_minutes"
                                        label="Length"
                                        sort={filters.sort}
                                        direction={filters.direction}
                                        onSort={sortBy}
                                    />

                                    <TableHead className="hidden lg:table-cell">
                                        Media
                                    </TableHead>

                                    <SortableHeader
                                        column="created_at"
                                        label="Created"
                                        sort={filters.sort}
                                        direction={filters.direction}
                                        onSort={sortBy}
                                        className="hidden xl:table-cell"
                                    />

                                    <TableHead className="w-24 text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>

                            <TableBody>
                                {meditations.data.map((meditation) => {
                                    const CategoryIcon = meditationIcon(
                                        meditation.category.icon,
                                    );

                                    return (
                                        <TableRow
                                            key={meditation.id}
                                            className="border-border"
                                        >
                                            <TableCell className="max-w-sm py-2">
                                                <p className="font-medium">
                                                    {meditation.title}
                                                </p>

                                                {meditation.description && (
                                                    <p className="line-clamp-1 text-[12px] text-muted-foreground">
                                                        {meditation.description}
                                                    </p>
                                                )}
                                            </TableCell>

                                            <TableCell className="hidden py-2 sm:table-cell">
                                                <span className="inline-flex items-center gap-1.5 rounded-sm bg-meditation-bg px-2 py-0.5 text-[12px] font-medium text-meditation-icon">
                                                    <CategoryIcon className="size-3.5" />
                                                    {meditation.category.name}
                                                </span>
                                            </TableCell>

                                            <TableCell className="py-2 font-numeric font-bold whitespace-nowrap tabular-nums">
                                                {formatDuration(
                                                    meditation.duration_minutes,
                                                )}
                                            </TableCell>

                                            <TableCell className="hidden py-2 lg:table-cell">
                                                <div className="flex items-center gap-1.5 text-muted-foreground">
                                                    {meditation.audio_url && (
                                                        <AudioLines
                                                            className="size-4"
                                                            aria-label="Has audio"
                                                        />
                                                    )}

                                                    {meditation.video_url && (
                                                        <Video
                                                            className="size-4"
                                                            aria-label="Has video"
                                                        />
                                                    )}

                                                    {!meditation.audio_url &&
                                                        !meditation.video_url && (
                                                            <span className="text-sm">
                                                                —
                                                            </span>
                                                        )}
                                                </div>
                                            </TableCell>

                                            <TableCell className="hidden py-2 whitespace-nowrap text-muted-foreground tabular-nums xl:table-cell">
                                                {dateFormatter.format(
                                                    new Date(
                                                        meditation.created_at,
                                                    ),
                                                )}
                                            </TableCell>

                                            <TableCell className="py-2">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        asChild
                                                        aria-label={`Edit ${meditation.title}`}
                                                    >
                                                        <Link
                                                            href={edit(
                                                                meditation.id,
                                                            )}
                                                            prefetch
                                                        >
                                                            <Pencil className="size-4" />
                                                        </Link>
                                                    </Button>

                                                    <DeleteMeditationDialog
                                                        meditation={meditation}
                                                    />
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}

                                {meditations.data.length === 0 && (
                                    <TableRow className="hover:bg-transparent">
                                        <TableCell colSpan={6}>
                                            <div className="flex flex-col items-center gap-2.5 py-14 text-center">
                                                <span className="flex size-10 items-center justify-center rounded-full bg-meditation-bg text-meditation-icon">
                                                    {isFiltered ? (
                                                        <SearchX className="size-[18px]" />
                                                    ) : (
                                                        <Waves className="size-[18px]" />
                                                    )}
                                                </span>

                                                <div className="space-y-1">
                                                    <p className="font-semibold">
                                                        {isFiltered
                                                            ? 'No meditations match these filters'
                                                            : 'No meditations yet'}
                                                    </p>

                                                    <p className="text-[13px] text-muted-foreground">
                                                        {isFiltered
                                                            ? 'Try another category, a wider length range, or a different search term.'
                                                            : 'Add the first session to start building the library.'}
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
                                                    <Button size="sm" asChild>
                                                        <Link href={create()}>
                                                            <Plus />
                                                            New meditation
                                                        </Link>
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
                        page={meditations}
                        only={['meditations', 'filters']}
                    />
                </section>
            </div>
        </>
    );
}

MeditationIndex.layout = {
    breadcrumbs: [{ title: 'Meditations', href: index() }],
};
