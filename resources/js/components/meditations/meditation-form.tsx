import { Form, Link } from '@inertiajs/react';
import { index } from '@/actions/App/Http/Controllers/MeditationController';
import FieldLabel from '@/components/field-label';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Meditation, MeditationCategoryOption } from '@/types';

type FormAction = {
    action: string;
    method: 'post' | 'put' | 'patch';
};

type MeditationInput = Pick<
    Meditation,
    | 'category_id'
    | 'title'
    | 'description'
    | 'thumbnail'
    | 'audio_url'
    | 'video_url'
    | 'duration_minutes'
>;

export default function MeditationForm({
    action,
    categories,
    maxDuration,
    meditation,
    submitLabel,
}: {
    action: FormAction;
    categories: MeditationCategoryOption[];
    maxDuration: number;
    meditation?: MeditationInput;
    submitLabel: string;
}) {
    return (
        <Form
            {...action}
            options={{ preserveScroll: true }}
            resetOnSuccess={!meditation}
            className="flex flex-col gap-4 rounded-card border border-border bg-card p-5 shadow-card"
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
                            <FieldLabel htmlFor="duration_minutes" required>
                                Duration (minutes)
                            </FieldLabel>

                            <Input
                                id="duration_minutes"
                                name="duration_minutes"
                                type="number"
                                min={1}
                                max={maxDuration}
                                inputMode="numeric"
                                defaultValue={meditation?.duration_minutes}
                                required
                                placeholder="12"
                            />

                            <InputError message={errors.duration_minutes} />
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
                            className="w-full rounded-md border border-input bg-transparent px-2.5 py-2 text-[13px] shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
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
                                defaultValue={meditation?.audio_url ?? ''}
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
                                defaultValue={meditation?.video_url ?? ''}
                                placeholder="https://cdn.winly.test/video/…"
                                autoComplete="off"
                            />

                            <InputError message={errors.video_url} />
                        </div>
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            disabled={processing}
                            data-test="submit-meditation"
                        >
                            {submitLabel}
                        </Button>

                        <Button variant="ghost" asChild>
                            <Link href={index()}>Cancel</Link>
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}
