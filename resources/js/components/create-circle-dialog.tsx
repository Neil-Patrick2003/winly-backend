import { useForm } from '@inertiajs/react';
import {  useState } from 'react';
import type {ReactNode} from 'react';
import CircleController from '@/actions/App/Http/Controllers/CircleController';
import { CircleVisibilityField } from '@/components/circle-visibility-field';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Start a circle without leaving the list.
 *
 * The colour and the initial are not asked for: naming the thing is what
 * somebody came to do, and both are worked out from the name on the way in.
 */
export function CreateCircleDialog({ trigger }: { trigger: ReactNode }) {
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        name: '',
        description: '',
        tag: '',
        is_private: false,
    });

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(CircleController.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOpen(false);
            },
        });
    };

    const change = (next: boolean) => {
        setOpen(next);

        if (! next) {
            reset();
            clearErrors();
        }
    };

    return (
        <Dialog open={open} onOpenChange={change}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="sm:max-w-md">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Create a circle</DialogTitle>
                        <DialogDescription>
                            A named group people join. You will be its first member.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="my-6 space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="circle-name">Name</Label>
                            <Input
                                id="circle-name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                placeholder="Morning Movers"
                                maxLength={60}
                                required
                                autoFocus
                                aria-invalid={!! errors.name}
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="circle-description">Description</Label>
                            <Input
                                id="circle-description"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                placeholder="What this circle is for"
                                maxLength={500}
                                aria-invalid={!! errors.description}
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="circle-tag">Tag</Label>
                            <Input
                                id="circle-tag"
                                value={data.tag}
                                onChange={(event) => setData('tag', event.target.value)}
                                placeholder="Fitness"
                                maxLength={40}
                                aria-invalid={!! errors.tag}
                            />
                            <InputError message={errors.tag} />
                        </div>

                        <CircleVisibilityField
                            isPrivate={data.is_private}
                            onChange={(next) => setData('is_private', next)}
                            disabled={processing}
                        />
                        <InputError message={errors.is_private} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => change(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || data.name.trim() === ''}
                        >
                            Create circle
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
