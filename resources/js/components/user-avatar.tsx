import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import { cn } from '@/lib/utils';

type Props = {
    name: string;
    src?: string | null;
    className?: string;
};

/**
 * Somebody's picture, falling back to their initials.
 *
 * The alt is empty on purpose: the name is always rendered beside it, and a
 * screen reader announcing it twice is noise rather than help.
 */
export function UserAvatar({ name, src, className }: Props) {
    const getInitials = useInitials();

    return (
        <Avatar className={cn('size-8 shrink-0', className)}>
            {src && <AvatarImage src={src} alt="" />}
            <AvatarFallback className="text-caption font-medium">
                {getInitials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
