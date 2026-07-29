import { BookOpen, Brain, Footprints } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { WinType } from '@/types';

/**
 * How each kind of win is drawn, wherever it appears.
 *
 * One hue per pillar, held across the badge on a post and the column on the
 * tracker, so the colour is learned once rather than per screen. The tints and
 * their ink are theme tokens, so both follow light and dark.
 */
export const winTypeMeta: Record<
    WinType,
    { label: string; icon: LucideIcon; tint: string; ink: string; dot: string }
> = {
    meditation: {
        label: 'Meditation',
        icon: Brain,
        tint: 'bg-meditation-bg',
        ink: 'text-meditation-icon',
        dot: 'bg-meditation',
    },
    learning: {
        label: 'Learning',
        icon: BookOpen,
        tint: 'bg-learning-bg',
        ink: 'text-learning-icon',
        dot: 'bg-learning',
    },
    movement: {
        label: 'Movement',
        icon: Footprints,
        tint: 'bg-movement-bg',
        ink: 'text-movement-icon',
        dot: 'bg-movement',
    },
};

export function WinTypeBadge({
    type,
    className,
}: {
    type: WinType;
    className?: string;
}) {
    const { label, icon: Icon, tint, ink } = winTypeMeta[type];

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-caption font-medium',
                tint,
                ink,
                className,
            )}
        >
            <Icon className="size-3" aria-hidden />
            {label}
        </span>
    );
}
