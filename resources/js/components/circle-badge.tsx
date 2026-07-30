import { cn } from '@/lib/utils';

const sizes = {
    sm: 'size-6 rounded-md text-[10px]',
    md: 'size-9 rounded-lg text-caption',
    lg: 'size-12 rounded-sheet text-base',
} as const;

type Props = {
    initial: string;
    color: string;
    size?: keyof typeof sizes;
    className?: string;
};

/**
 * A circle's mark: its initial on the colour drawn from its name.
 *
 * Decorative — the circle's name is always rendered next to it, so this is
 * hidden from screen readers rather than repeating it.
 */
export function CircleBadge({ initial, color, size = 'md', className }: Props) {
    return (
        <span
            aria-hidden
            className={cn(
                'inline-flex shrink-0 items-center justify-center font-semibold text-white',
                sizes[size],
                className,
            )}
            style={{ backgroundColor: color }}
        >
            {initial}
        </span>
    );
}
