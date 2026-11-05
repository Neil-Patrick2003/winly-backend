<?php

namespace App\Console\Commands;

use App\Models\Report;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'reports:list', description: 'List reports waiting for a moderator')]
class ListReports extends Command
{
    protected $signature = 'reports:list {--all : Include reports that were already handled}';

    public function handle(): int
    {
        $reports = Report::query()
            ->with(['reporter', 'reportable'])
            ->when(! $this->option('all'), fn ($query) => $query->where('status', Report::STATUS_PENDING))
            ->oldest()
            ->limit(200)
            ->get();

        if ($reports->isEmpty()) {
            $this->info('No reports waiting.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Filed', 'Type', 'Reason', 'Reporter', 'Status', 'Note'],
            $reports->map(fn (Report $report) => [
                $report->id,
                $report->created_at?->diffForHumans(),
                $report->typeName().($report->reportable ? '' : ' (gone)'),
                $report->reasonLabel(),
                '@'.$report->reporter?->username,
                $report->status,
                str((string) $report->note)->limit(40),
            ]),
        );

        return self::SUCCESS;
    }
}
