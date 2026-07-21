import { Head } from '@inertiajs/react';
import {
    index,
    update,
} from '@/actions/App/Http/Controllers/MeditationController';
import Heading from '@/components/heading';
import MeditationForm from '@/components/meditations/meditation-form';
import type { Meditation, MeditationCategoryOption } from '@/types';

export default function EditMeditation({
    meditation,
    categories,
    maxDuration,
}: {
    meditation: Pick<
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
    categories: MeditationCategoryOption[];
    maxDuration: number;
}) {
    return (
        <>
            <Head title={`Edit ${meditation.title}`} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-5 px-4 py-5 sm:px-6">
                <Heading
                    title={`Edit ${meditation.title}`}
                    description="Changes apply everywhere this session appears."
                />

                <MeditationForm
                    action={update.form(meditation.id)}
                    categories={categories}
                    maxDuration={maxDuration}
                    meditation={meditation}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

EditMeditation.layout = {
    breadcrumbs: [
        { title: 'Meditations', href: index() },
        { title: 'Edit', href: '' },
    ],
};
