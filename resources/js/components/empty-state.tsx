import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    icon?: LucideIcon;
    title: string;
    description?: string;
    /** A way out — usually the one thing worth doing from here. */
    action?: ReactNode;
    className?: string;
};

/**
 * What a list shows when it has nothing in it.
 *
 * Says what would be here and, where there is one, offers the action that puts
 * something in it — an empty panel with no explanation reads as a fault.
 */
export function EmptyState({
    icon: Icon,
    title,
    description,
    action,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col items-center justify-center rounded-card border border-dashed border-border px-6 py-12 text-center',
                className,
            )}
        >
            {Icon && (
                <Icon className="mb-3 size-7 text-muted-foreground/70" aria-hidden />
            )}

            <p className="text-sm font-medium">{title}</p>

            {description && (
                <p className="mt-1 max-w-sm text-caption text-muted-foreground">
                    {description}
                </p>
            )}

            {action && <div className="mt-4">{action}</div>}
        </div>
    );
}
