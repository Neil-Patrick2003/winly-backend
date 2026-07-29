import type { ReactNode } from 'react';
import { UserAvatar } from '@/components/user-avatar';
import { cn } from '@/lib/utils';

type Person = {
    full_name: string;
    username?: string | null;
    avatar_url?: string | null;
};

type Props = {
    person: Person;
    /** Shown under the name in place of the username. */
    meta?: ReactNode;
    /** Buttons or badges pinned to the right. */
    action?: ReactNode;
    className?: string;
};

/**
 * One person in a list: picture, name, and whatever may be done about them.
 *
 * The name truncates rather than wrapping so a long one cannot push the action
 * off the row on a narrow screen.
 */
export function PersonRow({ person, meta, action, className }: Props) {
    return (
        <div className={cn('flex items-center gap-3', className)}>
            <UserAvatar name={person.full_name} src={person.avatar_url} />

            <div className="min-w-0 flex-1">
                <p className="truncate text-sm font-medium">{person.full_name}</p>

                <div className="truncate text-caption text-muted-foreground">
                    {meta ?? (person.username ? `@${person.username}` : null)}
                </div>
            </div>

            {action && <div className="flex shrink-0 items-center gap-1">{action}</div>}
        </div>
    );
}
