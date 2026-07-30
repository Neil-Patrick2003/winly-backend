<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Post;
use App\Models\WinMedia;
use App\Models\WinMeditation;
use App\Rules\MediaFile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Left open, because who may edit a post is the policy's question and the
     * controller asks it. Answering it here as well would mean two places to
     * keep in step, and a 403 raised from a form request is harder to trace
     * back to the rule that caused it.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The same shape as creating a post, because an edit is a restatement of
     * the whole thing rather than a patch: `wins` describes what the post
     * should end up being, and a kind left out of it is a kind being removed.
     *
     * `wins` stays required with at least one entry. A post is its wins, so
     * removing the last one is not an edit — it is a deletion, and there is an
     * endpoint for that which also tidies up the media.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $post = $this->route('post');

        return [
            'caption' => ['nullable', 'string', 'max:2000'],

            'circle_ids' => ['nullable', 'array', 'max:50'],
            'circle_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('circle_memberships', 'circle_id')
                    ->where('user_id', $this->user()?->getKey()),
            ],

            'wins' => ['required', 'array', 'min:1', 'max:'.count(Post::WIN_TYPES)],
            'wins.*.type' => ['required', 'string', Rule::in(Post::WIN_TYPES), 'distinct'],
            'wins.*.completed_at' => ['nullable', 'date', 'before_or_equal:now'],

            // Files being added by this edit. What is already on the win stays
            // unless its id appears in `remove_media_ids`.
            'wins.*.media' => ['nullable', 'array', 'max:'.WinMedia::MAX_PER_WIN],
            'wins.*.media.*' => ['required', new MediaFile],

            /*
             * Existing media to drop, narrowed to this post's own rows.
             *
             * The scoping is the point: without it a guessable id would let
             * somebody delete a photo from a post that is not theirs, through
             * an endpoint they are otherwise allowed to call on their own.
             */
            'wins.*.remove_media_ids' => ['nullable', 'array'],
            'wins.*.remove_media_ids.*' => [
                'uuid',
                'distinct',
                Rule::exists('win_media', 'id')
                    ->where('post_id', $post instanceof Post ? $post->getKey() : null),
            ],

            'wins.*.duration_minutes' => [
                'required_if:wins.*.type,meditation',
                'nullable',
                'integer',
                'min:1',
                'max:'.WinMeditation::MAX_DURATION_MINUTES,
            ],
            'wins.*.completed' => ['nullable', 'boolean'],

            'wins.*.learned_text' => ['required_if:wins.*.type,learning', 'nullable', 'string', 'max:2000'],
            'wins.*.reference_source' => ['nullable', 'string', 'max:2048'],

            'wins.*.movement_type' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Check the rules that need more than one field to answer.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->assertMediaFits($validator);
        });
    }

    /**
     * Keep a win from growing past the media cap across several edits.
     *
     * The per-field rule only counts the files arriving now. Ten already on a
     * win plus one more each time would slip past it, so the cap is checked
     * against what the win will actually hold once this edit is applied.
     */
    protected function assertMediaFits(Validator $validator): void
    {
        $post = $this->route('post');

        if (! $post instanceof Post) {
            return;
        }

        foreach ($this->validated('wins') ?? [] as $index => $win) {
            $existing = match ($win['type']) {
                'meditation' => $post->winMeditation,
                'learning' => $post->winLearning,
                'movement' => $post->winMovement,
                default => null,
            };

            $kept = $existing === null
                ? 0
                : $existing->media()
                    ->whereNotIn('id', $win['remove_media_ids'] ?? [])
                    ->count();

            $total = $kept + count($win['media'] ?? []);

            if ($total > WinMedia::MAX_PER_WIN) {
                $validator->errors()->add(
                    "wins.{$index}.media",
                    'A win may hold at most '.WinMedia::MAX_PER_WIN.' photos or clips, and this would leave it with '.$total.'.'
                );
            }
        }
    }
}
