import { Head } from '@inertiajs/react';
import { Heart, LayoutGrid, PenLine, UsersRound } from 'lucide-react';
import { useState } from 'react';
import { ActivityChart } from '@/components/dashboard/activity-chart';
import { ActivityFeed } from '@/components/dashboard/activity-feed';
import { MemberOverview } from '@/components/dashboard/member-overview';
import { MyCircles } from '@/components/dashboard/my-circles';
import { StatTile } from '@/components/dashboard/stat-tile';
import { StreakLeaders } from '@/components/dashboard/streak-leaders';
import { DateRangePicker } from '@/components/date-range-picker';
import { Page } from '@/components/page';
import { useStat } from '@/hooks/use-stat';
import { dashboard } from '@/routes';
import {
    activity,
    circles,
    engagement,
    memberOverview,
    members,
    myCircles,
    overview,
    posts,
    streakLeaders,
} from '@/routes/dashboard/stats';
import type {
    ActivityFeedPayload,
    ActivityOverview,
    CirclesStat,
    DailyPostsStat,
    EngagementStat,
    MemberOverview as MemberOverviewPayload,
    MembersStat,
    MyCirclesPayload,
    StreakLeaderboard,
} from '@/types/dashboard';

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
 * The panel title above its card.
 *
 * Every heading is one line at the same size, so two cards side by side start
 * at the same point down the page.
 */
function PanelTitle({ children }: { children: React.ReactNode }) {
    return (
        <h2 className="mb-2 font-display text-sm font-semibold text-foreground">
            {children}
        </h2>
    );
}

export default function Dashboard() {
    const [range, setRange] = useState({
        from: daysAgo(DEFAULT_DAYS - 1),
        to: isoDay(new Date()),
    });

    const query = { query: range };

    const circlesStat = useStat<CirclesStat>(circles.url(query));
    const membersStat = useStat<MembersStat>(members.url(query));
    const postsStat = useStat<DailyPostsStat>(posts.url(query));
    const engagementStat = useStat<EngagementStat>(engagement.url(query));
    const chart = useStat<ActivityOverview>(overview.url(query));
    const memberChart = useStat<MemberOverviewPayload>(
        memberOverview.url(query),
    );

    const circleList = useStat<MyCirclesPayload>(myCircles.url());
    const leaders = useStat<StreakLeaderboard>(streakLeaders.url());
    const feed = useStat<ActivityFeedPayload>(activity.url());

    return (
        <>
            <Head title="Dashboard" />

            <Page width="wide">
                <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-page-title font-semibold">
                            Dashboard
                        </h1>
                        <p className="text-caption text-muted-foreground">
                            How your circles are doing at a glance.
                        </p>
                    </div>

                    <DateRangePicker
                        from={range.from}
                        to={range.to}
                        onApply={(from, to) => setRange({ from, to })}
                    />
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatTile
                        label="Circles"
                        icon={LayoutGrid}
                        value={circlesStat.data?.value ?? null}
                        change={circlesStat.data?.change}
                        isLoading={circlesStat.isLoading}
                        error={circlesStat.error}
                        onRetry={circlesStat.reload}
                    />

                    <StatTile
                        label="Members"
                        icon={UsersRound}
                        value={membersStat.data?.value ?? null}
                        change={membersStat.data?.change}
                        isLoading={membersStat.isLoading}
                        error={membersStat.error}
                        onRetry={membersStat.reload}
                    />

                    <StatTile
                        label="Posts per day"
                        icon={PenLine}
                        value={postsStat.data?.value ?? null}
                        format="decimal"
                        change={postsStat.data?.change}
                        isLoading={postsStat.isLoading}
                        error={postsStat.error}
                        onRetry={postsStat.reload}
                    />

                    <StatTile
                        label="Engagement"
                        icon={Heart}
                        value={engagementStat.data?.value ?? null}
                        format="decimal"
                        suffix="%"
                        change={engagementStat.data?.change}
                        changeUnit="pp"
                        isLoading={engagementStat.isLoading}
                        error={engagementStat.error}
                        onRetry={engagementStat.reload}
                    />
                </div>

                {/*
                 * Each section is a column and the card takes the room left
                 * after the heading. A card set to the section's full height
                 * instead would measure 100% of a box that already contains
                 * that heading, and hang over whatever follows by exactly the
                 * height of the title above it.
                 */}
                <div className="mt-6 grid items-stretch gap-6 lg:grid-cols-3">
                    <section className="flex flex-col lg:col-span-2">
                        <PanelTitle>Activity overview</PanelTitle>
                        <div className="flex-1">
                            <ActivityChart
                                points={chart.data?.points ?? null}
                                isLoading={chart.isLoading}
                                error={chart.error}
                                onRetry={chart.reload}
                            />
                        </div>
                    </section>

                    <section className="flex flex-col">
                        <PanelTitle>Streak leaders</PanelTitle>
                        <div className="flex-1">
                            <StreakLeaders
                                board={leaders.data}
                                isLoading={leaders.isLoading}
                                error={leaders.error}
                                onRetry={leaders.reload}
                            />
                        </div>
                    </section>
                </div>

                <div className="mt-6 grid items-stretch gap-6 lg:grid-cols-2">
                    <section className="flex flex-col">
                        <PanelTitle>My circles</PanelTitle>
                        <div className="flex-1">
                            <MyCircles
                                payload={circleList.data}
                                isLoading={circleList.isLoading}
                                error={circleList.error}
                                onRetry={circleList.reload}
                            />
                        </div>
                    </section>

                    <section className="flex flex-col">
                        <PanelTitle>Recent activity</PanelTitle>
                        <div className="flex-1">
                            <ActivityFeed
                                payload={feed.data}
                                isLoading={feed.isLoading}
                                error={feed.error}
                                onRetry={feed.reload}
                            />
                        </div>
                    </section>
                </div>

                <section className="mt-6 flex flex-col">
                    <PanelTitle>Member overview</PanelTitle>
                    <div className="flex-1">
                        <MemberOverview
                            overview={memberChart.data}
                            isLoading={memberChart.isLoading}
                            error={memberChart.error}
                            onRetry={memberChart.reload}
                        />
                    </div>
                </section>
            </Page>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
