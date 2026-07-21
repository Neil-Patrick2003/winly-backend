import {
    Brain,
    CloudMoon,
    Flower2,
    HandHeart,
    HeartPulse,
    Leaf,
    Moon,
    MountainSnow,
    Music,
    Sparkles,
    Sun,
    Sunrise,
    Waves,
    Wind,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

/**
 * Mirrors MeditationCategory::ICONS on the server. Keep both lists in sync.
 */
export const meditationIcons: Record<string, LucideIcon> = {
    brain: Brain,
    'cloud-moon': CloudMoon,
    'flower-2': Flower2,
    'hand-heart': HandHeart,
    'heart-pulse': HeartPulse,
    leaf: Leaf,
    moon: Moon,
    'mountain-snow': MountainSnow,
    music: Music,
    sparkles: Sparkles,
    sun: Sun,
    sunrise: Sunrise,
    waves: Waves,
    wind: Wind,
};

export function meditationIcon(name: string): LucideIcon {
    return meditationIcons[name] ?? Sparkles;
}

export function iconLabel(name: string): string {
    return name
        .split('-')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}
