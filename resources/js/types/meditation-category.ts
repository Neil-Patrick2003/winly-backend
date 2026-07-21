export type MeditationCategory = {
    id: number;
    name: string;
    icon: string;
    description: string | null;
    created_at: string;
};

export type MeditationCategorySort = 'name' | 'created_at' | 'updated_at';

export type MeditationCategoryFilters = {
    search: string | null;
    sort: MeditationCategorySort;
    direction: 'asc' | 'desc';
    from: string | null;
    to: string | null;
    per_page: number;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type Paginated<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    from: number | null;
    to: number | null;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type MeditationCategoryOption = {
    id: number;
    name: string;
    icon: string;
};

export type Meditation = {
    id: number;
    category_id: number;
    title: string;
    description: string | null;
    thumbnail: string | null;
    audio_url: string | null;
    video_url: string | null;
    duration_minutes: number;
    created_at: string;
    category: MeditationCategoryOption;
};

export type MeditationSort =
    'title' | 'duration_minutes' | 'created_at' | 'updated_at';

export type MeditationFilters = {
    search: string | null;
    category_id: number | null;
    sort: MeditationSort;
    direction: 'asc' | 'desc';
    min_duration: number | null;
    max_duration: number | null;
    from: string | null;
    to: string | null;
    per_page: number;
};
