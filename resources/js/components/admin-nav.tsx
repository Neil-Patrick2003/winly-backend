import { Link } from '@inertiajs/react';
import { Users, UsersRound } from 'lucide-react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { circles, users } from '@/routes/admin';

const tabs = [
    { href: users(), label: 'People', icon: Users },
    { href: circles(), label: 'Circles', icon: UsersRound },
];

/**
 * Moving between the staff screens.
 *
 * A row of two rather than anything in the main navigation: these pages are for
 * a handful of people, and putting them in the header everybody sees would mean
 * every member's nav carrying a gap where an admin link would have been.
 */
export function AdminNav() {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <nav className="flex gap-1" aria-label="Admin">
            {tabs.map(({ href, label, icon: Icon }) => (
                <Link
                    key={label}
                    href={href}
                    prefetch
                    className={cn(
                        'flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                        isCurrentUrl(href)
                            ? 'bg-accent text-accent-foreground'
                            : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                    )}
                >
                    <Icon className="size-4" aria-hidden />
                    {label}
                </Link>
            ))}
        </nav>
    );
}
