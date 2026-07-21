import { Head } from '@inertiajs/react';
import {
    create,
    index,
    store,
} from '@/actions/App/Http/Controllers/MeditationController';
import Heading from '@/components/heading';
import MeditationForm from '@/components/meditations/meditation-form';
import type { MeditationCategoryOption } from '@/types';

export default function CreateMeditation({
    categories,
    maxDuration,
}: {
    categories: MeditationCategoryOption[];
    maxDuration: number;
}) {
    return (
        <>
            <Head title="New meditation" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-5 px-4 py-5 sm:px-6">
                <Heading
                    title="New meditation"
                    description="File the session under a category and point it at its media."
                />

                <MeditationForm
                    action={store.form()}
                    categories={categories}
                    maxDuration={maxDuration}
                    submitLabel="Create meditation"
                />
            </div>
        </>
    );
}

CreateMeditation.layout = {
    breadcrumbs: [
        { title: 'Meditations', href: index() },
        { title: 'New', href: create() },
    ],
};
