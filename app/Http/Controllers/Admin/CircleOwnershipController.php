<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Circles\TransferCircleOwnership;
use App\Http\Controllers\Controller;
use App\Models\Circle;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CircleOwnershipController extends Controller
{
    /**
     * Put somebody else at the top of a circle.
     *
     * The one thing a circle cannot sort out from the inside: changing the
     * owner needs the owner's permission, so a group whose owner has gone quiet
     * — or whose account is gone altogether — stays stuck until staff step in.
     *
     * Reached from the circle's own manage screen, which staff can open for any
     * circle. The guard against handing it to a stranger is the validation
     * rule; the action refuses the same thing again, since it is also what the
     * API would call if it ever grew this.
     */
    public function __invoke(Request $request, Circle $circle, TransferCircleOwnership $transfer): RedirectResponse
    {
        $validated = $request->validate([
            'owner_id' => [
                'required',
                'uuid',
                Rule::exists('circle_memberships', 'user_id')
                    ->where('circle_id', $circle->getKey()),
            ],
        ], [
            'owner_id.exists' => 'That person is not in this circle.',
        ]);

        $owner = User::query()->whereKey($validated['owner_id'])->firstOrFail();

        $transfer->execute($circle, $owner);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':circle is now owned by :name.', [
                'circle' => $circle->name,
                'name' => $owner->full_name,
            ]),
        ]);

        return back();
    }
}
