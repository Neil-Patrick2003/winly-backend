import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/MeditationCategoryController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import type { MeditationCategory } from '@/types';

export default function DeleteCategoryDialog({
    category,
}: {
    category: MeditationCategory;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="text-muted-foreground hover:text-destructive"
                    aria-label={`Delete ${category.name}`}
                    data-test={`delete-category-${category.id}`}
                >
                    <Trash2 className="size-4" />
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {category.name}?</DialogTitle>

                    <DialogDescription>
                        This category will be removed permanently. Sessions
                        filed under it will need a new home.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Form
                        {...destroy.form(category.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                    >
                        {({ processing }) => (
                            <Button variant="destructive" disabled={processing}>
                                Delete category
                            </Button>
                        )}
                    </Form>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
