<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReportRequest;
use App\Models\Report;
use App\Notifications\ContentReported;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ReportController extends Controller
{
    /**
     * File a report about a post, comment, story or person.
     *
     * Reporting the same thing again updates the one report rather than
     * stacking up copies, and puts it back on the queue if it had been closed.
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $target = $request->target;

        $report = Report::query()->updateOrCreate(
            [
                'reporter_id' => $request->user()->getKey(),
                'reportable_type' => $target->getMorphClass(),
                'reportable_id' => $target->getKey(),
            ],
            [
                'reason' => $request->string('reason')->value(),
                'note' => $request->input('note'),
                'status' => Report::STATUS_PENDING,
                'reviewed_at' => null,
                'reviewed_by' => null,
            ],
        );

        $this->notifyModerators($report);

        return response()->json(['data' => ['id' => $report->id]], 201);
    }

    /**
     * Email the moderation inbox.
     *
     * A failed send must not lose the report or tell the person it failed: it
     * is already saved, and `reports:list` still shows it.
     */
    protected function notifyModerators(Report $report): void
    {
        $address = config('legal.contact_email');

        if (! $address) {
            return;
        }

        try {
            Notification::route('mail', $address)->notify(new ContentReported($report));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
