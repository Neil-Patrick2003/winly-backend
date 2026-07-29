import { router, useForm } from '@inertiajs/react';
import { Heart, MessageCircle, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { PostMedia } from '@/components/post-media';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { UserAvatar } from '@/components/user-avatar';
import { WinTypeBadge } from '@/components/win-type-badge';
import { cn } from '@/lib/utils';
import { destroy as destroyComment } from '@/routes/comments';
import { like, unlike } from '@/routes/posts';
import { store as storeComment } from '@/routes/posts/comments';
import type { CirclePost, PostWin } from '@/types';

/** The sentence a win says on its own, under the caption. */
function winSummary(win: PostWin): string | null {
    if (win.type === 'meditation') {
        const minutes = win.detail.duration_minutes ?? 0;
        const cutShort = win.detail.completed === false ? ', cut short' : '';

        return `Sat for ${minutes} ${minutes === 1 ? 'minute' : 'minutes'}${cutShort}`;
    }

    if (win.type === 'learning') {
        return win.detail.learned_text ?? null;
    }

    return win.detail.movement_type
        ? `Moved: ${win.detail.movement_type}`
        : 'Moved today';
}

function timeAgo(iso: string | null): string {
    if (!iso) {
        return '';
    }

    const seconds = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);

    if (seconds < 60) {
        return 'just now';
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)}m`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)}h`;
    }

    if (seconds < 604800) {
        return `${Math.floor(seconds / 86400)}d`;
    }

    return new Date(iso).toLocaleDateString();
}

export function PostCard({ post }: { post: CirclePost }) {
    const [showComments, setShowComments] = useState(post.comments.length > 0);

    const { data, setData, post: submit, processing, errors, reset } = useForm({
        text: '',
    });

    const toggleLike = () => {
        const route = post.viewer_has_liked ? unlike(post.id) : like(post.id);

        router.visit(route.url, {
            method: route.method,
            preserveScroll: true,
            preserveState: true,
        });
    };

    const addComment = (event: React.FormEvent) => {
        event.preventDefault();

        submit(storeComment(post.id).url, {
            preserveScroll: true,
            onSuccess: () => reset('text'),
        });
    };

    const media = post.wins.flatMap((win) => win.media);
    const hasCounts = post.likes_count > 0 || post.comments_count > 0;

    return (
        <article className="overflow-hidden rounded-card border border-border bg-card shadow-card">
            <header className="flex items-center gap-3 p-4 pb-3">
                <UserAvatar
                    name={post.author.full_name}
                    src={post.author.avatar_url}
                    className="size-10"
                />

                <div className="min-w-0">
                    <p className="truncate text-sm font-semibold">
                        {post.author.full_name}
                    </p>
                    <p className="text-caption text-muted-foreground">
                        {timeAgo(post.created_at)}
                    </p>
                </div>

                <div className="ml-auto flex flex-wrap justify-end gap-1">
                    {post.wins.map((win) => (
                        <WinTypeBadge key={win.type} type={win.type} />
                    ))}
                </div>
            </header>

            {post.caption && (
                <p className="px-4 pb-3 text-sm whitespace-pre-line">{post.caption}</p>
            )}

            <ul className="space-y-1 px-4 pb-3">
                {post.wins.map((win) => {
                    const summary = winSummary(win);

                    if (!summary) {
                        return null;
                    }

                    return (
                        <li key={win.type} className="text-sm text-muted-foreground">
                            {summary}
                            {win.type === 'learning' && win.detail.reference_source && (
                                <>
                                    {' — '}
                                    <a
                                        href={win.detail.reference_source}
                                        target="_blank"
                                        rel="noreferrer noopener"
                                        className="underline underline-offset-4 hover:text-foreground"
                                    >
                                        source
                                    </a>
                                </>
                            )}
                        </li>
                    );
                })}
            </ul>

            <PostMedia files={media} />

            {hasCounts && (
                <div className="flex items-center justify-between px-4 py-2 text-caption text-muted-foreground tabular-nums">
                    <span>
                        {post.likes_count > 0 &&
                            `${post.likes_count} ${post.likes_count === 1 ? 'like' : 'likes'}`}
                    </span>

                    {post.comments_count > 0 && (
                        <button
                            type="button"
                            onClick={() => setShowComments(true)}
                            className="rounded-sm underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            {post.comments_count}{' '}
                            {post.comments_count === 1 ? 'comment' : 'comments'}
                        </button>
                    )}
                </div>
            )}

            <div className="flex border-t border-border">
                <Button
                    variant="ghost"
                    className="flex-1 rounded-none"
                    onClick={toggleLike}
                    aria-pressed={post.viewer_has_liked}
                >
                    <Heart
                        className={cn(
                            'size-4 transition-colors',
                            post.viewer_has_liked && 'fill-red-500 text-red-500',
                        )}
                    />
                    {post.viewer_has_liked ? 'Liked' : 'Like'}
                </Button>

                <Button
                    variant="ghost"
                    className="flex-1 rounded-none"
                    onClick={() => setShowComments((open) => !open)}
                    aria-expanded={showComments}
                >
                    <MessageCircle className="size-4" />
                    Comment
                </Button>
            </div>

            {showComments && (
                <div className="space-y-3 border-t border-border bg-muted/20 p-4">
                    {post.comments_count > post.comments.length && (
                        <p className="text-caption text-muted-foreground">
                            Showing the latest {post.comments.length} of{' '}
                            {post.comments_count} comments.
                        </p>
                    )}

                    {post.comments.map((comment) => (
                        <div key={comment.id} className="flex items-start gap-2">
                            <UserAvatar
                                name={comment.author.full_name}
                                src={comment.author.avatar_url}
                                className="size-7"
                            />

                            <div className="min-w-0 flex-1 rounded-2xl bg-background px-3 py-2 shadow-float">
                                <p className="text-caption font-semibold">
                                    {comment.author.full_name}
                                </p>
                                <p className="text-sm whitespace-pre-line">
                                    {comment.text}
                                </p>
                            </div>

                            {comment.can_delete && (
                                <ConfirmDialog
                                    tooltip="Delete comment"
                                    title="Delete this comment?"
                                    description="It will be removed for everyone. This cannot be undone."
                                    confirmLabel="Delete"
                                    destructive
                                    onConfirm={() =>
                                        router.delete(
                                            destroyComment(comment.id).url,
                                            { preserveScroll: true },
                                        )
                                    }
                                    trigger={
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="size-7 shrink-0 text-muted-foreground"
                                        >
                                            <Trash2 className="size-3.5" />
                                            <span className="sr-only">
                                                Delete comment
                                            </span>
                                        </Button>
                                    }
                                />
                            )}
                        </div>
                    ))}

                    <form onSubmit={addComment} className="flex items-start gap-2">
                        <div className="flex-1">
                            <Input
                                value={data.text}
                                onChange={(event) => setData('text', event.target.value)}
                                placeholder="Write a comment…"
                                maxLength={2000}
                                aria-label={`Comment on ${post.author.full_name}'s post`}
                                aria-invalid={!!errors.text}
                            />
                            {errors.text && (
                                <p className="mt-1 text-caption text-destructive">
                                    {errors.text}
                                </p>
                            )}
                        </div>

                        <Button
                            type="submit"
                            size="sm"
                            disabled={processing || data.text.trim() === ''}
                        >
                            Post
                        </Button>
                    </form>
                </div>
            )}
        </article>
    );
}
