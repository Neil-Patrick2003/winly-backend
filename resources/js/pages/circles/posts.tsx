import { Head } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { Pagination } from '@/components/pagination';
import { PostCard } from '@/components/post-card';
import CircleLayout from '@/layouts/circle/circle-layout';
import type { CircleHeader, CirclePost, Paginated } from '@/types';

export default function Posts({
    circle,
    posts,
}: {
    circle: CircleHeader;
    posts: Paginated<CirclePost>;
}) {
    return (
        <>
            <Head title={`${circle.name} · Posts`} />

            <CircleLayout circle={circle}>
                {posts.data.length === 0 ? (
                    <EmptyState
                        icon={Sparkles}
                        title="No wins shared yet"
                        description="Wins members share into this circle will appear here, newest first."
                    />
                ) : (
                    <div className="flex flex-col gap-4">
                        {posts.data.map((post) => (
                            <PostCard key={post.id} post={post} />
                        ))}

                        <Pagination page={posts} label="posts" />
                    </div>
                )}
            </CircleLayout>
        </>
    );
}
