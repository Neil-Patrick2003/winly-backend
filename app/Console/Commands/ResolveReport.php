<?php

namespace App\Console\Commands;

use App\Actions\Moderation\ResolveReport as Resolver;
use App\Models\Report;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * The same three choices as the admin Reports page, for when a terminal is
 * closer to hand than a browser.
 */
#[AsCommand(name: 'reports:resolve', description: 'Remove reported content, disable its author, or dismiss the report')]
class ResolveReport extends Command
{
    protected $signature = 'reports:resolve
        {report : The report ID, from reports:list or the email}
        {--remove : Delete the reported post, comment or story}
        {--disable : Disable the account behind it, so they cannot sign in and their content is hidden}
        {--dismiss : Close the report without changing anything}';

    public function handle(Resolver $resolver): int
    {
        $report = Report::query()->with('reportable')->find($this->argument('report'));

        if (! $report) {
            $this->error('No report with that ID.');

            return self::FAILURE;
        }

        $chosen = array_filter([
            'remove' => $this->option('remove'),
            'disable' => $this->option('disable'),
            'dismiss' => $this->option('dismiss'),
        ]);

        if (count($chosen) !== 1) {
            $this->error('Pass exactly one of --remove, --disable or --dismiss.');

            return self::FAILURE;
        }

        try {
            match (array_key_first($chosen)) {
                'remove' => $resolver->removeContent($report),
                'disable' => $this->info('Disabled @'.$resolver->disableAuthor($report)?->username.'.'),
                'dismiss' => $resolver->dismiss($report),
            };
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
