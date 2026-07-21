import { Form } from '@inertiajs/react';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/MeditationCategoryController';
import FieldLabel from '@/components/field-label';
import InputError from '@/components/input-error';
import IconPicker from '@/components/meditation-categories/icon-picker';
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
import type { MeditationCategory } from '@/types';

export type CategoryDraft = Pick<
    MeditationCategory,
    'id' | 'name' | 'icon' | 'description'
>;

/**
 * Create and edit in one dialog. The list behind it keeps its filters, its
 * scroll position, and its page.
 */
export default function CategoryDialog({
    category,
    iconOptions,
    open,
    onOpenChange,
}: {
    /** The row being edited, or null to create a new one. */
    category: CategoryDraft | null;
    iconOptions: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const isEditing = category !== null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {isEditing ? `Edit ${category.name}` : 'New category'}
                    </DialogTitle>

                    <DialogDescription>
                        {isEditing
                            ? 'Changes apply everywhere this category appears.'
                            : 'Give the category a name, an icon, and a short description.'}
                    </DialogDescription>
                </DialogHeader>

                <Form
                    // Radix unmounts the dialog body on close, so switching
                    // rows re-reads defaultValue without any extra plumbing.
                    key={category?.id ?? 'new'}
                    {...(isEditing ? update.form(category.id) : store.form())}
                    options={{ preserveScroll: true, preserveState: true }}
                    onSuccess={() => onOpenChange(false)}
                    className="flex flex-col gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <FieldLabel htmlFor="name" required>
                                    Name
                                </FieldLabel>

                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={category?.name}
                                    required
                                    autoFocus
                                    autoComplete="off"
                                    placeholder="Sleep"
                                />

                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <FieldLabel htmlFor="icon" required>
                                    Icon
                                </FieldLabel>

                                <IconPicker
                                    options={iconOptions}
                                    defaultValue={category?.icon}
                                />

                                <InputError message={errors.icon} />
                            </div>

                            <div className="grid gap-2">
                                <FieldLabel htmlFor="description">
                                    Description
                                </FieldLabel>

                                <textarea
                                    id="description"
                                    name="description"
                                    rows={3}
                                    defaultValue={category?.description ?? ''}
                                    maxLength={1000}
                                    placeholder="What this category helps with, in a sentence or two."
                                    className="w-full rounded-md border border-input bg-transparent px-2.5 py-2 text-[13px] shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-input/30"
                                />

                                <InputError message={errors.description} />
                            </div>

                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button variant="secondary" type="button">
                                        Cancel
                                    </Button>
                                </DialogClose>

                                <Button
                                    disabled={processing}
                                    data-test="submit-category"
                                >
                                    {isEditing
                                        ? 'Save changes'
                                        : 'Create category'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
