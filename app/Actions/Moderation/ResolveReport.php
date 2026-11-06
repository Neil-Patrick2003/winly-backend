<?php

namespace App\Actions\Moderation;

use App\Actions\RemovePost;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * What a moderator can do about a report.
 *
 * Shared by the admin Reports page and the `reports:resolve` command, so the
 * two cannot drift apart. Acting on one report closes every other open report
 * about the same thing, since they are all answered by the same decision.
 */
class ResolveReport
{
    public function __construct(
        protected RemovePost $removePost,
        protected DisableAccount $disableAccount,
    ) {}

    /**
     * Delete the reported post, comment or story.
     *
     * A reported person has no content of their own to delete; disabling the
     * account is the answer there.
     */
    public function removeContent(Report $report, ?User $moderator = null): void
    {
        $target = $report->reportable;

        if ($target instanceof User) {
            throw new InvalidArgumentException('A person is disabled, not removed.');
        }

        match (true) {
            $target instanceof Post => $this->removePost->execute($target),
            $target instanceof Comment => DB::transaction(function () use ($target): void {
                $target->delete();
                Post::query()
                    ->whereKey($target->post_id)
                    ->where('comments_count', '>', 0)
                    ->decrement('comments_count');
            }),
            $target instanceof Story => $target->delete(),
            // Already gone: nothing to remove, only reports to close.
            default => null,
        };

        $this->close($report, Report::STATUS_ACTIONED, $moderator);
    }

    /**
     * Disable whoever is behind the report: the person reported, or the author
     * of the post, comment or story.
     */
    public function disableAuthor(Report $report, ?User $moderator = null): ?User
    {
        $author = self::authorOf($report->reportable);

        if ($author === null) {
            throw new InvalidArgumentException('There is nobody left to disable.');
        }

        if ($author->is_admin) {
            throw new InvalidArgumentException('Staff accounts cannot be disabled from here.');
        }

        $this->disableAccount->disable($author);
        $this->close($report, Report::STATUS_ACTIONED, $moderator);

        return $author;
    }

    /**
     * Close the reports about this thing without changing it.
     */
    public function dismiss(Report $report, ?User $moderator = null): void
    {
        $this->close($report, Report::STATUS_DISMISSED, $moderator);
    }

    /**
     * The person a reported thing belongs to.
     */
    public static function authorOf(?Model $target): ?User
    {
        return match (true) {
            $target instanceof User => $target,
            $target instanceof Post, $target instanceof Comment, $target instanceof Story => $target->user,
            default => null,
        };
    }

    /**
     * Close this report and every other one still open about the same thing.
     */
    protected function close(Report $report, string $status, ?User $moderator): void
    {
        Report::query()
            ->where('reportable_type', $report->reportable_type)
            ->where('reportable_id', $report->reportable_id)
            ->where(fn ($query) => $query
                ->where('status', Report::STATUS_PENDING)
                ->orWhere('id', $report->getKey()))
            ->update([
                'status' => $status,
                'reviewed_at' => now(),
                'reviewed_by' => $moderator?->getKey(),
            ]);
    }
}
