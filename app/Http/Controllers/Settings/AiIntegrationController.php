<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mcp\Servers\WelleServer;
use App\Models\Circle;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal AI integration (MCP) tokens.
 *
 * Each belongs to the signed-in person only, so everybody manages their own
 * and never sees anyone else's. The plain token exists only for the page
 * view straight after it is made.
 */
class AiIntegrationController extends Controller
{
    /** Enough for a few apps without letting tokens pile up. */
    private const MAX_TOKENS = 10;

    /** How long a token works before a new one has to be made. */
    private const EXPIRES_AFTER_DAYS = 90;

    /**
     * Show the AI integration settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/ai-integration', [
            'mcpUrl' => route('mcp'),
            'tokens' => $this->tokens($request->user())
                ->latest()
                ->get(['id', 'name', 'last_used_at', 'expires_at', 'created_at']),
            'newToken' => $request->session()->get('newAiToken'),
            // The tools only answer about circles this person runs.
            'runsCircles' => Circle::query()->runBy($request->user())->exists(),
        ]);
    }

    /**
     * Make a new token.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        if ($this->tokens($request->user())->count() >= self::MAX_TOKENS) {
            throw ValidationException::withMessages([
                'name' => __('You can have up to :max tokens. Revoke one you no longer use first.', ['max' => self::MAX_TOKENS]),
            ]);
        }

        $token = $request->user()->createToken(
            $validated['name'],
            [WelleServer::TOKEN_ABILITY],
            now()->addDays(self::EXPIRES_AFTER_DAYS),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token created.')]);

        return to_route('ai-integration.edit')->with('newAiToken', $token->plainTextToken);
    }

    /**
     * Revoke a token. Another person's id, or an app token, is a 404.
     */
    public function destroy(Request $request, int $token): RedirectResponse
    {
        $this->tokens($request->user())->findOrFail($token)->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Token revoked.')]);

        return to_route('ai-integration.edit');
    }

    /**
     * This person's AI integration tokens, leaving out the mobile app's.
     *
     * @return MorphMany<PersonalAccessToken, User>
     */
    private function tokens(User $user): MorphMany
    {
        return $user->tokens()->where('abilities', json_encode([WelleServer::TOKEN_ABILITY]));
    }
}
