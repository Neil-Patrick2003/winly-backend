export type CircleSummary = {
    id: string;
    name: string;
    icon_initial: string;
    color_hex: string;
    /** Whether the reader owns this circle, and so may manage it. */
    can_manage: boolean;
};

/** The circle header every tab shares. */
export type CircleHeader = CircleSummary & {
    description: string | null;
    tag: string | null;
    members_count: number;
};

/** The pastels a circle's card can be washed in. */
export type CircleWash =
    | 'blue'
    | 'lavender'
    | 'pink'
    | 'peach'
    | 'mint'
    | 'butter';

/** A circle as the My Circles list shows it. */
export type CircleListing = CircleHeader & {
    wash: CircleWash;
    posts_count: number;
    /** Somebody has shared into it in the last week. */
    is_active: boolean;
    joined_at: string;
    faces: { id: string; full_name: string; avatar_url: string | null }[];
};

export type CircleFilter = 'all' | 'active' | 'quiet';

export type CircleMember = {
    id: string;
    full_name: string;
    username: string | null;
    avatar_url: string | null;
    joined_at: string | null;
    is_owner: boolean;
};

export type CircleMemberRow = CircleMember & {
    streak_days: number;
    wins_count: number;
};

export type CirclePerson = Pick<
    CircleMember,
    'id' | 'full_name' | 'username' | 'avatar_url'
>;

export type PendingInvitation = {
    id: string;
    invited_at: string | null;
    user: CirclePerson;
};

/** Somebody the owner could ask, and where they already stand. */
export type InviteCandidate = CirclePerson & {
    is_member: boolean;
    invite_status: 'pending' | 'accepted' | 'declined' | null;
    is_blocked: boolean;
};

export type PostAuthor = Pick<
    CircleMember,
    'id' | 'full_name' | 'username' | 'avatar_url'
>;

export type WinFile = {
    id: string;
    url: string;
    kind: 'image' | 'video';
};

export type PostWin = {
    type: 'meditation' | 'learning' | 'movement';
    detail: {
        duration_minutes?: number;
        completed?: boolean;
        learned_text?: string | null;
        reference_source?: string | null;
        movement_type?: string | null;
    };
    media: WinFile[];
};

export type PostComment = {
    id: string;
    text: string;
    created_at: string | null;
    author: PostAuthor;
    can_delete: boolean;
};

export type CirclePost = {
    id: string;
    caption: string | null;
    likes_count: number;
    comments_count: number;
    viewer_has_liked: boolean;
    created_at: string | null;
    author: PostAuthor;
    wins: PostWin[];
    /** The newest few; comments_count is the real total. */
    comments: PostComment[];
};

export type WinType = 'meditation' | 'learning' | 'movement';

/** How much of each kind a member has shared into the circle over a range. */
export type TrackerRow = {
    id: string;
    full_name: string;
    username: string | null;
    avatar_url: string | null;
    streak_days: number;
    longest_streak: number;
    /** Every win type is present; a kind they have not done is 0. */
    wins: Record<WinType, number>;
    /** Days with at least one win in range — not the sum of `wins`. */
    total: number;
};

export type TrackerRange = '7' | '30' | '90' | 'all';

/** A Laravel length-aware paginator, as Inertia serialises it. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    /** Null on an empty page. */
    from: number | null;
    to: number | null;
    prev_page_url: string | null;
    next_page_url: string | null;
};
