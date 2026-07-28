export type User = {
    id: string;
    full_name: string;
    username: string | null;
    email: string;
    avatar_url?: string | null;
    cover_gradient?: string | null;
    bio?: string | null;
    streak_days: number;
    longest_streak: number;
    followers_count: number;
    following_count: number;
    wins_count: number;
    is_private: boolean;
    is_admin: boolean;
    last_active_at: string | null;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
