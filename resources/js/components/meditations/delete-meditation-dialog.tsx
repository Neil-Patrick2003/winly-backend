import { Form } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { destroy } from '@/actions/App/Http/Controllers/MeditationController';
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
import type { Meditation } from '@/types';

export default function DeleteMeditationDialog({
    meditation,
}: {
    meditation: Meditation;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="text-muted-foreground hover:text-destructive"
                    aria-label={`Delete ${meditation.title}`}
                    data-test={`delete-meditation-${meditation.id}`}
                >
                    <Trash2 className="size-4" />
                </Button>
            </DialogTrigger>

            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete {meditation.title}?</DialogTitle>

                    <DialogDescription>
                        This session will be removed permanently. The audio and
                        video files themselves are not touched.
                    </DialogDescription>
                </DialogHeader>

                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="secondary">Cancel</Button>
                    </DialogClose>

                    <Form
                        {...destroy.form(meditation.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setOpen(false)}
                    >
                        {({ processing }) => (
                            <Button variant="destructive" disabled={processing}>
                                Delete meditation
                            </Button>
                        )}
                    </Form>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
