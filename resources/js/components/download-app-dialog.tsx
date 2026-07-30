import { Apple, Check, Download, Lock, Smartphone } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

/**
 * Where each build can actually be got.
 *
 * `null` is the honest state for a build that does not exist yet, and it is
 * what drives the "Coming soon" row below — nothing here has to be edited in
 * two places when a store listing goes live, just given its URL.
 */
const DOWNLOADS: {
    key: string;
    icon: LucideIcon;
    name: string;
    detail: string;
    url: string | null;
}[] = [
    {
        key: 'android',
        icon: Smartphone,
        name: 'Android',
        detail: 'Phones and tablets running Android 9 or newer.',
        url: null,
    },
    {
        key: 'ios',
        icon: Apple,
        name: 'iPhone & iPad',
        detail: 'Waiting on App Store review.',
        url: null,
    },
    {
        key: 'pwa',
        icon: Download,
        name: 'Install from the browser',
        detail: 'Add it to your home screen, no store needed.',
        url: null,
    },
];

/**
 * The ways to take the app off the browser and onto a phone.
 *
 * Every platform is listed even when it is not ready, rather than hiding what
 * is missing: someone looking for the iPhone build should find out it is
 * coming, not be left wondering whether they looked in the wrong place.
 */
export function DownloadAppDialog({ trigger }: { trigger: ReactNode }) {
    return (
        <Dialog>
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className="font-display">
                        Take it with you
                    </DialogTitle>
                    <DialogDescription>
                        Log a win, cheer someone on, keep the circle's energy up
                        — from wherever you are.
                    </DialogDescription>
                </DialogHeader>

                <ul className="flex flex-col gap-2">
                    {DOWNLOADS.map(({ key, icon: Icon, name, detail, url }) => {
                        const available = url !== null;

                        return (
                            <li
                                key={key}
                                className={cn(
                                    'flex items-center gap-3 rounded-card border border-border p-3',
                                    available
                                        ? 'bg-card shadow-card'
                                        : 'bg-muted/50',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-9 shrink-0 items-center justify-center rounded-md',
                                        available
                                            ? 'bg-accent text-accent-foreground'
                                            : 'bg-secondary text-muted-foreground',
                                    )}
                                >
                                    <Icon className="size-4.5" aria-hidden />
                                </span>

                                <div className="grid flex-1 gap-0.5">
                                    <span
                                        className={cn(
                                            'text-card-title font-medium',
                                            !available &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        {name}
                                    </span>
                                    <span className="text-caption text-muted-foreground">
                                        {detail}
                                    </span>
                                </div>

                                {available ? (
                                    <Button asChild size="sm">
                                        <a href={url}>
                                            <Check />
                                            Get it
                                        </a>
                                    </Button>
                                ) : (
                                    <Badge
                                        variant="secondary"
                                        className="shrink-0 gap-1 text-muted-foreground"
                                    >
                                        <Lock className="size-3" aria-hidden />
                                        Coming soon
                                    </Badge>
                                )}
                            </li>
                        );
                    })}
                </ul>

                <p className="text-caption text-muted-foreground">
                    Everything works in the browser today — the apps are only
                    about having it in your pocket.
                </p>
            </DialogContent>
        </Dialog>
    );
}
