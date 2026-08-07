<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreReportRequest;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    /**
     * Flag a post, comment, story or person for staff to look at.
     *
     * Reporting is not moderation: nothing is hidden or removed here. It puts a
     * row on a queue, and the answer is the same whatever staff later decide,
     * so that the person reporting is not told what happened to somebody else's
     * account.
     *
     * Reporting your own content is refused. It is never a real report, and a
     * queue with them in it is a queue somebody has to read past.
     */
    public function store(StoreReportRequest $request): JsonResponse
    {
        $reportable = $request->reportable();
        $reporter = $request->user();

        // `user_id` on a post, comment or story; on a reported person it is the
        // record itself.
        $ownerId = $reportable instanceof User
            ? $reportable->id
            : $reportable->user_id ?? null;

        if ($ownerId === $reporter->id) {
            throw ValidationException::withMessages([
                'id' => 'You cannot report your own content.',
            ]);
        }

        /*
         * `firstOrCreate` against the unique index rather than an error on a
         * second attempt: somebody who reports the same thing twice meant it
         * once, and telling them off for it teaches them the button is broken.
         * They get the same acknowledgement either way.
         */
        $report = Report::firstOrCreate(
            [
                'reporter_id' => $reporter->id,
                'reportable_type' => $reportable->getMorphClass(),
                'reportable_id' => $reportable->getKey(),
            ],
            [
                'reason' => $request->string('reason')->value(),
                'note' => $request->input('note'),
            ],
        );

        return response()->json([
            'message' => 'Thanks — our team will review this within 24 hours.',
            'data' => ['id' => $report->id],
        ], 201);
    }
}
