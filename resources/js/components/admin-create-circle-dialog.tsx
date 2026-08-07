import { router, useForm } from '@inertiajs/react';
import { Check, Search } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { PersonRow } from '@/components/person-row';
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
import { cn } from '@/lib/utils';
import { circles as adminCircles } from '@/routes/admin';
import { store as storeCircle } from '@/routes/admin/circles';
import type { CirclePerson } from '@/types';

/**
 * Start a circle on somebody else's behalf.
 *
 * The one thing this asks that the ordinary create form does not: who owns it.
 * Staff never make circles for themselves, so leaving the owner implied would
 * hand every circle made here to whoever happened to be signed in.
 *
 * The candidates are searched on the server rather than listed in full — the
 * picker has to keep working when there are ten thousand accounts.
 */
export function AdminCreateCircleDialog({
    trigger,
    candidates,
    ownerSearch,
}: {
    trigger: ReactNode;
    candidates: CirclePerson[];
    ownerSearch: string | null;
}) {
    const [open, setOpen] = useState(false);
    const [term, setTerm] = useState(ownerSearch ?? '');
    const [owner, setOwner] = useState<CirclePerson | null>(null);

    const { data, setData, post, processing, errors, reset, clearErrors } =
        useForm({
            name: '',
            description: '',
            tag: '',
            owner_id: '',
        });

    /*
     * A partial reload rather than a second endpoint: the list already comes
     * from this page, so searching it only has to ask this page again for the
     * one prop that changes.
     */
    const lookUp = (event: React.FormEvent) => {
        event.preventDefault();

        router.get(
            adminCircles().url,
            { owner_search: term },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['ownerCandidates', 'ownerSearch'],
            },
        );
    };

    const choose = (person: CirclePerson) => {
        setOwner(person);
        setData('owner_id', person.id);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        post(storeCircle.url(), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setOwner(null);
                setOpen(false);
            },
        });
    };

    const change = (next: boolean) => {
        setOpen(next);

        if (!next) {
            reset();
            clearErrors();
            setOwner(null);
        }
    };

    return (
        <Dialog open={open} onOpenChange={change}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit}>
                    <DialogHeader>
                        <DialogTitle>Create a circle</DialogTitle>
                        <DialogDescription>
                            The owner you pick becomes its first member and can
                            manage it from then on.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="my-6 space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="admin-circle-name">Name</Label>
                            <Input
                                id="admin-circle-name"
                                value={data.name}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                                required
                                maxLength={60}
                                autoFocus
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="admin-circle-description">
                                Description
                            </Label>
                            <Input
                                id="admin-circle-description"
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                                maxLength={500}
                                placeholder="What this circle is for"
                            />
                            <InputError message={errors.description} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="admin-circle-tag">Tag</Label>
                            <Input
                                id="admin-circle-tag"
                                value={data.tag}
                                onChange={(event) =>
                                    setData('tag', event.target.value)
                                }
                                maxLength={40}
                            />
                            <InputError message={errors.tag} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="admin-circle-owner">Owner</Label>

                            {owner ? (
                                <div className="flex items-center gap-2 rounded-md border border-border p-2">
                                    <div className="min-w-0 flex-1">
                                        <PersonRow person={owner} />
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setOwner(null);
                                            setData('owner_id', '');
                                        }}
                                    >
                                        Change
                                    </Button>
                                </div>
                            ) : (
                                <>
                                    <div className="flex gap-2">
                                        <Input
                                            id="admin-circle-owner"
                                            value={term}
                                            onChange={(event) =>
                                                setTerm(event.target.value)
                                            }
                                            onKeyDown={(event) => {
                                                // Enter searches rather than
                                                // submitting a form with no
                                                // owner chosen yet.
                                                if (event.key === 'Enter') {
                                                    lookUp(event);
                                                }
                                            }}
                                            placeholder="Search by name, username or email"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={lookUp}
                                        >
                                            <Search className="size-4" />
                                            <span className="sr-only">
                                                Search people
                                            </span>
                                        </Button>
                                    </div>

                                    <ul
                                        className={cn(
                                            'max-h-52 divide-y divide-border overflow-y-auto rounded-md border border-border',
                                            candidates.length === 0 && 'hidden',
                                        )}
                                    >
                                        {candidates.map((person) => (
                                            <li key={person.id}>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        choose(person)
                                                    }
                                                    className="flex w-full items-center gap-2 px-3 py-2 text-left transition-colors hover:bg-accent/50"
                                                >
                                                    <div className="min-w-0 flex-1">
                                                        <PersonRow
                                                            person={person}
                                                        />
                                                    </div>
                                                    <Check className="size-4 shrink-0 text-muted-foreground opacity-0" />
                                                </button>
                                            </li>
                                        ))}
                                    </ul>

                                    {candidates.length === 0 && (
                                        <p className="text-caption text-muted-foreground">
                                            Nobody matches that search.
                                        </p>
                                    )}
                                </>
                            )}

                            <InputError message={errors.owner_id} />
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={processing || !data.owner_id}
                        >
                            Create circle
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
