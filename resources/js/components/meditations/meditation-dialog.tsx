import { Form } from '@inertiajs/react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/MeditationController';
import FieldLabel from '@/components/field-label';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Meditation, MeditationCategoryOption } from '@/types';

export type MeditationDraft = Pick<
    Meditation,
    | 'id'
    | 'category_id'
    | 'title'
    | 'description'
    | 'thumbnail'
    | 'audio_url'
    | 'video_url'
    | 'duration_minutes'
>;

const textareaClasses =
    'w-full rounded-md border border-input bg-transparent px-2.5 py-2 text-[13px] shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30';

/**
 * Create and edit in one dialog. The list behind it keeps its filters, its
 * scroll position, and its page.
 */
export default function MeditationDialog({
    meditation,
    categories,
    maxDuration,
    open,
    onOpenChange,
}: {
    /** The row being edited, or null to create a new one. */
    meditation: MeditationDraft | null;
    categories: MeditationCategoryOption[];
    maxDuration: number;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEditing = meditation !== null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90svh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing
                            ? `Edit ${meditation.title}`
                            : 'New meditation'}
                    </DialogTitle>

                    <DialogDescription>
                        {isEditing
                            ? 'Changes apply everywhere this session appears.'
                            : 'File the session under a category and point it at its media.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    // Radix unmounts the dialog body on close, so switching
                    // rows re-reads defaultValue without any extra plumbing.
                    key={meditation?.id ?? 'new'}
                    {...(isEditing ? update.form(meditation.id) : store.form())}
                    options={{ preserveScroll: true, preserveState: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <FieldLabel htmlFor="title" required>
                                    Title
                                </FieldLabel>

                                <Input
                                    id="title"
                                    name="title"
                                    defaultValue={meditation?.title}
                                    required
                                    autoFocus
                                    autoComplete="off"
                                    placeholder="Body Scan for Deep Rest"
                                />

                                <InputError message={errors.title} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <FieldLabel htmlFor="category_id" required>
                                        Category
                                    </FieldLabel>

                                    <Select
                                        name="category_id"
                                        defaultValue={
                                            meditation
                                                ? String(meditation.category_id)
                                                : undefined
                                        }
                                        required
                                    >
                                        <SelectTrigger
                                            id="category_id"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Choose a category" />
                                        </SelectTrigger>

                                        <SelectContent>
                                            {categories.map((category) => (
                                                <SelectItem
                                                    key={category.id}
                                                    value={String(category.id)}
                                                >
                                                    {category.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>

                                    <InputError message={errors.category_id} />
                                </div>

                                <div className="grid gap-2">
                                    <FieldLabel
                                        htmlFor="duration_minutes"
                                        required
                                    >
                                        Duration (minutes)
                                    </FieldLabel>

                                    <Input
                                        id="duration_minutes"
                                        name="duration_minutes"
                                        type="number"
                                        min={1}
                                        max={maxDuration}
                                        inputMode="numeric"
                                        defaultValue={
                                            meditation?.duration_minutes
                                        }
                                        required
                                        placeholder="12"
                                    />

                                    <InputError
                                        message={errors.duration_minutes}
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <FieldLabel htmlFor="description">
                                    Description
                                </FieldLabel>

                                <textarea
                                    id="description"
                                    name="description"
                                    rows={3}
                                    defaultValue={meditation?.description ?? ''}
                                    maxLength={2000}
                                    placeholder="What the session covers, and who it is for."
                                    className={textareaClasses}
                                />

                                <InputError message={errors.description} />
                            </div>

                            <div className="grid gap-2">
                                <FieldLabel htmlFor="thumbnail">
                                    Thumbnail path
                                </FieldLabel>

                                <Input
                                    id="thumbnail"
                                    name="thumbnail"
                                    defaultValue={meditation?.thumbnail ?? ''}
                                    placeholder="thumbnails/body-scan.jpg"
                                    autoComplete="off"
                                />

                                <InputError message={errors.thumbnail} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <FieldLabel htmlFor="audio_url">
                                        Audio URL
                                    </FieldLabel>

                                    <Input
                                        id="audio_url"
                                        name="audio_url"
                                        type="url"
                                        defaultValue={
                                            meditation?.audio_url ?? ''
                                        }
                                        placeholder="https://cdn.winly.test/audio/…"
                                        autoComplete="off"
                                    />

                                    <InputError message={errors.audio_url} />
                                </div>

                                <div className="grid gap-2">
                                    <FieldLabel htmlFor="video_url">
                                        Video URL
                                    </FieldLabel>

                                    <Input
                                        id="video_url"
                                        name="video_url"
                                        type="url"
                                        defaultValue={
                                            meditation?.video_url ?? ''
                                        }
                                        placeholder="https://cdn.winly.test/video/…"
                                        autoComplete="off"
                                    />

                                    <InputError message={errors.video_url} />
                                </div>
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    disabled={processing}
                                    data-test="submit-meditation"
                                >
                                    {isEditing
                                        ? 'Save changes'
                                        : 'Create meditation'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
