<?php

namespace App\Notifications;

use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\Story;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells whoever moderates that something was reported.
 *
 * Both stores expect reports to be acted on within 24 hours, so this goes out
 * the moment one is filed rather than waiting for somebody to check a list.
 * Sent inline, not queued, for the reason `ResetPasswordCode` gives: nothing
 * guarantees a queue worker is running.
 */
class ContentReported extends Notification
{
    public function __construct(public readonly Report $report) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $report = $this->report;
        $target = $report->reportable;
        $reporter = $report->reporter;

        $mail = (new MailMessage)
            ->subject('['.config('app.name').'] '.ucfirst($report->typeName()).' reported: '.$report->reasonLabel())
            ->line('Reason: '.$report->reasonLabel())
            ->line('Reported by: '.$reporter->full_name.' (@'.$reporter->username.')');

        if ($report->note) {
            $mail->line('Their note: "'.$report->note.'"');
        }

        $mail->line('What was reported: '.$this->describe($target));

        return $mail
            ->line('Report ID: '.$report->id)
            ->action('Review it', route('admin.reports'))
            ->line('From there you can delete it, disable the account behind it, or dismiss the report.');
    }

    protected function describe(mixed $target): string
    {
        return match (true) {
            $target instanceof Post => 'a post by @'.$target->user->username.' (post '.$target->id.')'
                .($target->caption ? ': "'.str($target->caption)->limit(200).'"' : ''),
            $target instanceof Comment => 'a comment by @'.$target->user->username.' (comment '.$target->id.'): "'
                .str($target->text)->limit(200).'"',
            $target instanceof Story => 'a story by @'.$target->user->username.' (story '.$target->id.')',
            $target instanceof User => 'the account @'.$target->username.' ('.$target->full_name.', user '.$target->id.')',
            default => 'something that has since been removed',
        };
    }
}
