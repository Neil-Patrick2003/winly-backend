import { Link } from '@inertiajs/react';
import { Flame, HeartHandshake, Users } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { CircleTrackerPreview } from '@/components/circle-tracker-preview';
import { home } from '@/routes';

/** What a circle gives back, said once each. */
const PROMISES = [
    { icon: Users, text: 'See your circle’s week at a glance' },
    { icon: Flame, text: 'Streaks kept honest, together' },
    { icon: HeartHandshake, text: 'Energy you give and get back' },
];

/**
 * The artwork beside the sign-in form.
 *
 * It shows the circle tracker rather than a stock photograph, because that is
 * the screen people come back for: your own streak is private business, a
 * circle's is shared. Composed from theme tokens, so it follows light and dark
 * with the rest of the page and cannot drift out of palette.
 */
export function AuthBrandPanel({ name }: { name: string }) {
    return (
        <div className="relative hidden overflow-hidden bg-accent lg:flex lg:flex-col lg:justify-between lg:p-10">
            {/*
             * The brand gradient, taken apart into three soft lights. Used
             * whole it is a hard 90° band; blurred and spread it reads as a
             * tinted room, which is what a pastel palette wants behind it.
             */}
            <div className="pointer-events-none absolute inset-0" aria-hidden>
                <div className="absolute -top-32 -left-24 size-[28rem] rounded-full bg-brand-green/25 blur-3xl" />
                <div className="absolute top-1/4 -right-32 size-[26rem] rounded-full bg-brand-blue/20 blur-3xl" />
                <div className="absolute -bottom-32 left-1/4 size-[28rem] rounded-full bg-brand-violet/20 blur-3xl" />
            </div>

            <Link
                href={home()}
                className="relative z-10 flex w-fit items-center gap-2.5"
            >
                <AppLogoIcon className="size-8 shrink-0" />
                <span className="font-display text-page-title font-semibold text-foreground">
                    {name}
                </span>
            </Link>

            <div className="relative z-10 flex flex-col items-start gap-8">
                <CircleTrackerPreview />

                <div className="max-w-sm">
                    <h2 className="font-display text-heading font-semibold text-balance text-foreground">
                        Track your circle’s care.
                    </h2>
                    <p className="mt-2 text-section text-muted-foreground">
                        Every act of extreme self care one of you logs shows up
                        here. Nobody grows alone — you can see who is carrying
                        the week and who could use some of your energy.
                    </p>
                </div>
            </div>

            <ul className="relative z-10 flex flex-col gap-3">
                {PROMISES.map(({ icon: Icon, text }) => (
                    <li
                        key={text}
                        className="flex items-center gap-2.5 text-caption text-muted-foreground"
                    >
                        <span className="flex size-6 items-center justify-center rounded-full bg-card/70 text-accent-foreground shadow-card">
                            <Icon className="size-3.5" aria-hidden />
                        </span>
                        {text}
                    </li>
                ))}
            </ul>
        </div>
    );
}
