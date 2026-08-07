<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteAccountRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    /**
     * Delete the signed-in account, and everything it made.
     *
     * A real delete rather than the soft one the model normally does. The App
     * Store requires an app that lets you make an account to let you remove it,
     * and the privacy policy says the content goes with it — a row still in the
     * table with a date in `deleted_at` honours neither.
     *
     * Posts, comments and stories are all `cascadeOnDelete` on `user_id`, so
     * they go with the row rather than being orphaned or left visible under a
     * name that no longer exists.
     *
     * Tokens first, so that a delete which fails partway cannot leave a live
     * session pointing at a half-removed account.
     */
    public function destroy(DeleteAccountRequest $request): Response
    {
        $request->ensurePasswordIsCorrect();

        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();

            // `forceDelete`, because the model is soft-deleting: `delete()`
            // would only stamp `deleted_at` and the database would cascade
            // nothing.
            $user->forceDelete();
        });

        return response()->noContent();
    }
}
