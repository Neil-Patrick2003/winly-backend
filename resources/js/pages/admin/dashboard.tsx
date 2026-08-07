import { Head } from '@inertiajs/react';
import {
    Flame,
    Heart,
    LayoutGrid,
    PenLine,
    Sparkles,
    UserPlus,
    Users,
    UsersRound,
} from 'lucide-react';
import { useState } from 'react';
import { CirclesDonut } from '@/components/admin/circles-donut';
import { PillarsPanel } from '@/components/admin/pillars-panel';
import { StatCard } from '@/components/admin/stat-card';
import { TrendPanel } from '@/components/admin/trend-panel';
import { DateRangePicker } from '@/components/date-range-picker';
import { Page } from '@/components/page';
import {
    accounts,
    active,
    circles,
    engagement,
    posts,
    postsSeries,
    signups,
    signupsSeries,
    streaks,
    winMix,
    wins,
} from '@/routes/admin/stats';

/** How far back the console looks before anybody touches the picker. */
const DEFAULT_DAYS = 14;

function isoDay(date: Date): string {
    return [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ].join('-');
}

function daysAgo(days: number): string {
    const date = new Date();
    date.setDate(date.getDate() - days);

    return isoDay(date);
}

/**
 * How the platform as a whole is doing.
 *
 * Every figure fetches itself from its own endpoint, so this file is layout and
 * nothing else — adding a statistic is a line here and a controller behind it,
 * with no payload in the middle for the two to fall out of step over.
 *
 * The first row moves with the date picker; the second is standing totals that
 * ignore it, because "how big is this thing" is not a question about a
 * fortnight. Charts follow: what is happening over time, and what it is made
 * of.
 */
export default function AdminDashboard() {
    const [range, setRange] = useState({
        from: daysAgo(DEFAULT_DAYS - 1),
        to: isoDay(new Date()),
    });

    const query = { query: range };

    return (
        <>
            <Head title="Platform health" />

            <Page width="wide">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-page-title font-semibold">
                            Platform health
                        </h1>
                        <p className="text-caption text-muted-foreground">
                            Everybody and every circle, not just yours.
                        </p>
                    </div>

                    <DateRangePicker
                        from={range.from}
                        to={range.to}
                        onApply={(from, to) => setRange({ from, to })}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="New accounts"
                        icon={UserPlus}
                        url={signups.url(query)}
                    />
                    <StatCard
                        label="Active accounts"
                        icon={Users}
                        url={active.url(query)}
                    />
                    <StatCard
                        label="Posts per day"
                        icon={PenLine}
                        format="decimal"
                        url={posts.url(query)}
                    />
                    <StatCard
                        label="Wins logged"
                        icon={Sparkles}
                        url={wins.url(query)}
                    />
                </div>

                <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label="Accounts"
                        icon={UsersRound}
                        url={accounts.url()}
                    />
                    <StatCard
                        label="Circles"
                        icon={LayoutGrid}
                        url={circles.url()}
                    />
                    <StatCard
                        label="Streaks running"
                        icon={Flame}
                        url={streaks.url()}
                    />
                    <StatCard
                        label="Engagement"
                        icon={Heart}
                        format="decimal"
                        suffix="%"
                        changeUnit="pp"
                        url={engagement.url(query)}
                    />
                </div>

                {/*
                 * The time series takes two thirds and the composition sits in
                 * the last one: "how much is happening" and "what is it made
                 * of" are different questions, and the wide plot is the one
                 * that needs the room to show a fortnight.
                 */}
                <div className="mt-6 grid items-start gap-6 lg:grid-cols-3">
                    <PillarsPanel
                        url={winMix.url(query)}
                        className="lg:col-span-2"
                    />

                    <CirclesDonut url={circles.url()} />
                </div>

                <div className="mt-6 grid items-start gap-6 lg:grid-cols-2">
                    <TrendPanel
                        title="Signups"
                        hint="accounts opened"
                        label="Signups"
                        color="var(--series-learning)"
                        url={signupsSeries.url(query)}
                    />

                    <TrendPanel
                        title="Posts"
                        hint="wins shared"
                        label="Posts"
                        color="var(--series-meditation)"
                        url={postsSeries.url(query)}
                    />
                </div>
            </Page>
        </>
    );
}
