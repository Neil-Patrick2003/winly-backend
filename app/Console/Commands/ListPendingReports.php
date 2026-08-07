<?php

namespace App\Console\Commands;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Story;
use App\Models\User;
use Illuminate\Console\Command;

use function Laravel\Prompts\table;

/**
 * The reports queue, on the command line.
 *
 * The terms promise action within 24 hours, and a promise like that needs
 * somebody able to see what is waiting. This is the smallest thing that makes
 * it true — a staff screen in the admin console is the better answer and this
 * is not a substitute for one, but an unread table is worse than a plain list.
 */
class ListPendingReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:pending
                            {--overdue : Only those older than the 24 hours the terms promise}
                            {--limit=50 : How many to show}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List content reports waiting on staff';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = Report::query()->pending()->with('reporter');

        if ($this->option('overdue')) {
            $query->where('created_at', '<', now()->subDay());
        }

        $reports = $query->limit((int) $this->option('limit'))->get();

        if ($reports->isEmpty()) {
            $this->info($this->option('overdue')
                ? 'Nothing overdue. Every report is inside the 24 hours.'
                : 'No reports waiting.');

            return self::SUCCESS;
        }

        table(
            ['Reported', 'What', 'Reason', 'By', 'Waiting', 'Note'],
            $reports->map(fn (Report $report): array => [
                $report->created_at->format('d M H:i'),
                $this->describe($report),
                $report->reason,
                $report->reporter?->username ?? '—',
                // The number the promise is measured against, said plainly so
                // an overdue one is obvious without arithmetic.
                $report->created_at->diffForHumans(short: true, syntax: true),
                $report->note === null ? '' : mb_strimwidth($report->note, 0, 40, '…'),
            ])->all(),
        );

        $overdue = $reports->filter(fn (Report $report): bool => $report->created_at->lt(now()->subDay()));

        if ($overdue->isNotEmpty()) {
            $this->newLine();
            $this->warn("{$overdue->count()} past the 24 hours promised in the terms.");
        }

        return self::SUCCESS;
    }

    /**
     * A one-line description of what a report is about.
     */
    private function describe(Report $report): string
    {
        $subject = $report->reportable;

        return match (true) {
            $subject instanceof Post => "post by @{$subject->user?->username}",
            $subject instanceof Comment => "comment by @{$subject->user?->username}",
            $subject instanceof Story => "story by @{$subject->user?->username}",
            $subject instanceof User => "user @{$subject->username}",
            // A report whose subject has since been deleted. Kept visible
            // rather than skipped: it is still evidence somebody complained.
            default => 'deleted content',
        };
    }
}
