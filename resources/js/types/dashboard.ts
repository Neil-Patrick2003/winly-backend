/**
 * A count measured over a rolling window, against the window before it.
 *
 * `change` is null when there is nothing to compare against — a jump from an
 * empty window is not growth, and a percentage would invent a trend.
 */
export type CountStat = {
    value: number;
    previous: number;
    change: number | null;
    days: number;
};

export type CirclesStat = CountStat & {
    started: number;
};

export type MembersStat = CountStat & {
    people: number;
    joined: number;
};

export type DailyPostsStat = CountStat & {
    total: number;
};

export type EngagementStat = CountStat & {
    engaged: number;
    total: number;
};

/** The win kinds the activity chart plots, in fixed order. */
export type WinSeries = 'meditation' | 'learning' | 'movement';

export type ActivityPoint = {
    date: string;
} & Record<WinSeries, number>;

export type ActivityOverview = {
    days: number;
    series: WinSeries[];
    points: ActivityPoint[];
};

export type MyCircle = {
    id: string;
    name: string;
    icon_initial: string;
    color_hex: string;
    tag: string | null;
    members_count: number;
};

/** The panel lists the largest few; `total` is how many the owner runs. */
export type MyCirclesPayload = {
    total: number;
    data: MyCircle[];
};

export type StreakLeader = {
    id: string;
    full_name: string;
    username: string;
    avatar_url: string | null;
    streak_days: number;
    longest_streak: number;
    logged_today: boolean;
};

export type StreakLeaderboard = {
    alive: number;
    at_risk: number;
    data: StreakLeader[];
};

export type ActivityEntry = {
    id: string;
    caption: string | null;
    created_at: string | null;
    user: {
        id: string;
        full_name: string;
        username: string;
        avatar_url: string | null;
    };
    wins: string[];
};

/** The panel lists the newest few; `total` is how many exist. */
export type ActivityFeedPayload = {
    total: number;
    data: ActivityEntry[];
};

export type JoinPoint = {
    date: string;
    joined: number;
};

export type MemberStatuses = {
    accepted: number;
    pending: number;
    declined: number;
    blocked: number;
};

export type MemberOverview = {
    days: number;
    points: JoinPoint[];
    statuses: MemberStatuses;
};
