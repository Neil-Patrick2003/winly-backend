import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    CalendarCheck,
    HeartHandshake,
    Smartphone,
    Sparkles,
    UserPlus,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { CircleTrackerPreview } from '@/components/circle-tracker-preview';
import { DownloadAppDialog } from '@/components/download-app-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { WinPreviewCard } from '@/components/win-preview-card';
import { winTypeMeta } from '@/components/win-type-badge';
import { cn } from '@/lib/utils';
import { dashboard, login, register } from '@/routes';
import type { WinType } from '@/types';

/** The three kinds of care a day can hold, in the order the app lists them. */
const PILLARS: { type: WinType; blurb: string }[] = [
    {
        type: 'meditation',
        blurb: 'A few minutes of quiet, logged with the time they actually sat for.',
    },
    {
        type: 'learning',
        blurb: 'One thing they did not know this morning, written down while it is fresh.',
    },
    {
        type: 'movement',
        blurb: 'A walk, a run, a stretch. Whatever got them out of the chair.',
    },
];

/** What the web console is for — the owner's side of a circle. */
const OWNER_TOOLS: { icon: LucideIcon; title: string; blurb: string }[] = [
    {
        icon: UserPlus,
        title: 'Create and invite',
        blurb: 'Start a circle, name it, and invite the people you want in it. Small enough that everyone is noticed.',
    },
    {
        icon: CalendarCheck,
        title: 'The ESC tracker',
        blurb: 'Every member, every kind of care, over any range of days — so progress is something you can read, not something you guess at.',
    },
    {
        icon: HeartHandshake,
        title: 'Spot the quiet ones',
        blurb: 'Streaks still standing and streaks that lapsed, side by side. You will see who could use some energy before they drop off.',
    },
];

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Create a circle, track how it grows" />

            <div className="flex min-h-dvh flex-col bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-6 py-5">
                    <span className="flex items-center gap-2.5">
                        <AppLogoIcon className="size-8 shrink-0" />
                        <span className="font-display text-page-title font-semibold">
                            {name}
                        </span>
                    </span>

                    <nav className="flex items-center gap-2">
                        <DownloadAppDialog
                            trigger={
                                <Button
                                    variant="ghost"
                                    size="lg"
                                    className="hidden sm:inline-flex"
                                >
                                    <Smartphone />
                                    Get the app
                                </Button>
                            }
                        />

                        {auth.user ? (
                            <Button asChild size="lg">
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Button asChild variant="ghost" size="lg">
                                    <Link href={login()}>Log in</Link>
                                </Button>
                                <Button asChild variant="brand" size="lg">
                                    <Link href={register()}>Create circle</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="flex-1">
                    {/* Hero */}
                    <section className="relative overflow-hidden">
                        {/*
                         * The brand gradient blurred into the corner rather
                         * than banded across it — the same light the sign-in
                         * panel is lit by, so the two screens feel like one
                         * product.
                         */}
                        <div
                            className="pointer-events-none absolute inset-0"
                            aria-hidden
                        >
                            <div className="absolute -top-40 right-0 size-[32rem] rounded-full bg-brand-blue/15 blur-3xl" />
                            <div className="absolute -top-24 right-1/3 size-[26rem] rounded-full bg-brand-green/15 blur-3xl" />
                        </div>

                        <div className="relative mx-auto grid w-full max-w-5xl items-center gap-12 px-6 py-16 lg:grid-cols-2 lg:py-24">
                            <div className="flex flex-col items-start gap-6">
                                <span className="inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-3 py-1 text-caption font-medium text-muted-foreground shadow-card">
                                    <Sparkles className="size-3 text-brand-violet" />
                                    ESC · Extreme Self Care
                                </span>

                                <h1 className="font-display text-heading font-semibold text-balance sm:text-hero">
                                    Create a circle. Watch it grow.
                                </h1>

                                <p className="max-w-md text-section text-muted-foreground">
                                    This is where circle owners work: start a
                                    circle, invite the people you want in it,
                                    and track every act of extreme self care
                                    they log. The sharing itself happens on the
                                    app.
                                </p>

                                <div className="flex flex-wrap items-center gap-3">
                                    <Button asChild variant="brand" size="lg">
                                        <Link
                                            href={
                                                auth.user
                                                    ? dashboard()
                                                    : register()
                                            }
                                        >
                                            {auth.user
                                                ? 'Open your dashboard'
                                                : 'Create first circle'}
                                            <ArrowRight />
                                        </Link>
                                    </Button>

                                    <DownloadAppDialog
                                        trigger={
                                            <Button variant="outline" size="lg">
                                                <Smartphone />
                                                Get the app
                                            </Button>
                                        }
                                    />
                                </div>
                            </div>

                            <div className="flex justify-center lg:justify-end">
                                <CircleTrackerPreview />
                            </div>
                        </div>
                    </section>

                    {/* What the web console is for */}
                    <section className="mx-auto w-full max-w-5xl px-6 py-12">
                        <h2 className="font-display text-page-title font-semibold">
                            Everything an owner needs
                        </h2>
                        <p className="mt-1.5 max-w-md text-section text-muted-foreground">
                            The web console is built for one job: keeping a
                            circle alive and knowing how it is doing.
                        </p>

                        <div className="mt-8 grid gap-4 sm:grid-cols-3">
                            {OWNER_TOOLS.map(({ icon: Icon, title, blurb }) => (
                                <article
                                    key={title}
                                    className="rounded-card border border-border bg-card p-5 shadow-card"
                                >
                                    <span className="flex size-9 items-center justify-center rounded-md bg-accent text-accent-foreground">
                                        <Icon
                                            className="size-4.5"
                                            aria-hidden
                                        />
                                    </span>
                                    <h3 className="mt-3.5 font-display text-card-title font-semibold">
                                        {title}
                                    </h3>
                                    <p className="mt-1.5 text-caption text-muted-foreground">
                                        {blurb}
                                    </p>
                                </article>
                            ))}
                        </div>
                    </section>

                    {/* The three pillars — the vocabulary both halves share */}
                    <section className="mx-auto w-full max-w-5xl px-6 py-12">
                        <h2 className="font-display text-page-title font-semibold">
                            What counts as a win
                        </h2>
                        <p className="mt-1.5 max-w-md text-section text-muted-foreground">
                            Three kinds of care, one hue each, held everywhere
                            they appear — so you learn the colour once.
                        </p>

                        <div className="mt-8 grid gap-4 sm:grid-cols-3">
                            {PILLARS.map(({ type, blurb }) => {
                                const {
                                    label,
                                    icon: Icon,
                                    tint,
                                    ink,
                                } = winTypeMeta[type];

                                return (
                                    <article
                                        key={type}
                                        className="rounded-card border border-border bg-card p-5 shadow-card"
                                    >
                                        <span
                                            className={cn(
                                                'flex size-9 items-center justify-center rounded-md',
                                                tint,
                                                ink,
                                            )}
                                        >
                                            <Icon
                                                className="size-4.5"
                                                aria-hidden
                                            />
                                        </span>
                                        <h3 className="mt-3.5 font-display text-card-title font-semibold">
                                            {label}
                                        </h3>
                                        <p className="mt-1.5 text-caption text-muted-foreground">
                                            {blurb}
                                        </p>
                                    </article>
                                );
                            })}
                        </div>
                    </section>

                    {/* The social half, which lives on the phone */}
                    <section className="mx-auto w-full max-w-5xl px-6 py-12">
                        <div className="grid items-center gap-10 lg:grid-cols-2">
                            <div className="flex flex-col items-start gap-4">
                                <Badge
                                    variant="secondary"
                                    className="gap-1.5 text-muted-foreground"
                                >
                                    <Smartphone
                                        className="size-3"
                                        aria-hidden
                                    />
                                    On the app
                                </Badge>

                                <h2 className="font-display text-page-title font-semibold text-balance">
                                    The energy travels on mobile
                                </h2>
                                <p className="max-w-md text-section text-muted-foreground">
                                    Logging a win, cheering somebody on, posting
                                    a story at the end of a hard day — the
                                    social side belongs in a pocket, not a
                                    browser tab. Your members do all of that on
                                    the app; you watch it add up here.
                                </p>

                                <DownloadAppDialog
                                    trigger={
                                        <Button size="lg">
                                            <Smartphone />
                                            Get the app
                                        </Button>
                                    }
                                />
                            </div>

                            <div className="flex justify-center lg:justify-end">
                                <WinPreviewCard />
                            </div>
                        </div>
                    </section>

                    {/* Closing call to action */}
                    {!auth.user && (
                        <section className="mx-auto w-full max-w-5xl px-6 pt-4 pb-16">
                            <div className="relative overflow-hidden rounded-sheet border border-border bg-accent px-6 py-10 text-center sm:px-10">
                                <div
                                    className="pointer-events-none absolute inset-0"
                                    aria-hidden
                                >
                                    <div className="absolute -top-24 left-1/4 size-80 rounded-full bg-brand-violet/20 blur-3xl" />
                                    <div className="absolute right-1/4 -bottom-24 size-80 rounded-full bg-brand-green/20 blur-3xl" />
                                </div>

                                <div className="relative flex flex-col items-center gap-4">
                                    <h2 className="font-display text-page-title font-semibold text-balance">
                                        Start with one circle.
                                    </h2>
                                    <p className="max-w-sm text-section text-muted-foreground">
                                        A handful of people who will notice when
                                        you go quiet. That is the whole trick.
                                    </p>
                                    <Button asChild variant="brand" size="lg">
                                        <Link href={register()}>
                                            Create first circle
                                            <ArrowRight />
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </section>
                    )}
                </main>

                <footer className="border-t border-border">
                    <div className="mx-auto flex w-full max-w-5xl flex-wrap items-center justify-between gap-3 px-6 py-6 text-caption text-muted-foreground">
                        <span>
                            © {new Date().getFullYear()} {name}
                        </span>
                        <span>Extreme self care, shared.</span>
                    </div>
                </footer>
            </div>
        </>
    );
}
