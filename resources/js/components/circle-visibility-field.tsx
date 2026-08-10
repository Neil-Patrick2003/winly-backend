import { Globe, Lock } from 'lucide-react';
import { useState } from 'react';

import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * The two kinds, open one first.
 *
 * Public leads because it is what almost every circle wants to be and what
 * every circle made before this choice existed already is — a form whose first
 * option is the ordinary one is a form most people can leave alone.
 *
 * The wording is the phone's, word for word. One setting explained two ways is
 * two settings as far as anybody reading both is concerned.
 */
const OPTIONS = [
    {
        value: '0',
        label: 'Public',
        hint: 'Anyone can find it in Discover and join.',
        Icon: Globe,
    },
    {
        value: '1',
        label: 'Private',
        hint: 'Hidden from Discover and search. People join by invitation.',
        Icon: Lock,
    },
] as const;

/**
 * Who can find a circle.
 *
 * Radio inputs rather than a checkbox or a switch, for two reasons. They post
 * "0" or "1", which is what Laravel's `boolean` rule takes — a bare checkbox
 * posts "on" and fails it, and the usual fix is a hidden sibling nobody
 * remembers is there. And one of the two is always checked, so there is no
 * absent case for the server to interpret.
 *
 * Works in both of the shapes this app writes forms in: give it `onChange` and
 * it is controlled by `useForm`, leave that off and the `name` carries it
 * through an uncontrolled Inertia `<Form>`. Either way the DOM holds the answer,
 * so a plain submit is never missing the field.
 */
export function CircleVisibilityField({
    name = 'is_private',
    isPrivate: controlled,
    defaultPrivate = false,
    onChange,
    disabled = false,
}: {
    /** The field name posted. Only worth changing if two sit on one page. */
    name?: string;
    /**
     * Given, this is the answer and the internal state is ignored.
     *
     * What a `useForm` caller wants: its `reset()` has to be able to put the
     * choice back, and a field holding its own copy would keep the old one and
     * disagree with the data being submitted.
     */
    isPrivate?: boolean;
    /** The starting answer where the field keeps its own state. */
    defaultPrivate?: boolean;
    /** Given, the value is also pushed into a `useForm` alongside the DOM. */
    onChange?: (isPrivate: boolean) => void;
    disabled?: boolean;
}) {
    const [internal, setInternal] = useState(defaultPrivate);
    const isPrivate = controlled ?? internal;

    const choose = (next: boolean) => {
        setInternal(next);
        onChange?.(next);
    };

    return (
        <fieldset className="grid gap-2" disabled={disabled}>
            <Label asChild>
                <legend>Who can find it</legend>
            </Label>

            <div className="grid gap-2">
                {OPTIONS.map((option) => {
                    const chosen = (option.value === '1') === isPrivate;

                    return (
                        <label
                            key={option.value}
                            className={cn(
                                'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors',
                                chosen
                                    ? 'border-primary bg-primary/5'
                                    : 'border-input hover:bg-accent/40',
                                disabled && 'cursor-not-allowed opacity-60',
                            )}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={option.value}
                                checked={chosen}
                                onChange={() => choose(option.value === '1')}
                                disabled={disabled}
                                className="sr-only"
                            />

                            <option.Icon
                                aria-hidden
                                className={cn(
                                    'mt-0.5 size-4 shrink-0',
                                    chosen
                                        ? 'text-primary'
                                        : 'text-muted-foreground',
                                )}
                            />

                            <span className="grid gap-0.5">
                                <span className="text-sm leading-none font-medium">
                                    {option.label}
                                </span>
                                <span className="text-muted-foreground text-xs">
                                    {option.hint}
                                </span>
                            </span>
                        </label>
                    );
                })}
            </div>
        </fieldset>
    );
}
