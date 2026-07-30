import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    children: ReactNode;
    /** Marks a section whose actions cannot be undone. */
    danger?: boolean;
    className?: string;
};

/**
 * A titled block on a settings page.
 *
 * The heading and its body travel together so every section on a page is
 * spaced and weighted the same, however many of them there are.
 */
export function SettingsSection({
    title,
    description,
    children,
    danger = false,
    className,
}: Props) {
    return (
        <section
            className={cn(
                'space-y-3',
                danger && 'rounded-card border border-destructive/40 p-4',
                className,
            )}
        >
            <Heading variant="small" title={title} description={description} />

            {children}
        </section>
    );
}
