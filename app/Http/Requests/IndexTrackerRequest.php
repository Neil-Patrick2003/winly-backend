<?php

namespace App\Http\Requests;

use App\Models\Post;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTrackerRequest extends FormRequest
{
    /**
     * How far back the tracker looks when the caller does not say.
     */
    public const DEFAULT_DAYS = 30;

    /**
     * The columns the table can be ordered by.
     *
     * Every one of them is a column somebody can see, so an order they set is
     * an order they can read back off the page.
     */
    public const SORTS = ['name', 'streak', 'days', 'points', 'meditation', 'learning', 'movement'];

    /**
     * Whether somebody turned up at all in the range.
     */
    public const ACTIVITY = ['active', 'inactive'];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            // Both bounds are inclusive, so the same day in each is a single
            // day rather than nothing at all.
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],

            /*
             * Which circles to count, out of this one and the circles inside
             * it. Absent means all of them, which is what the tab is for.
             *
             * Only ids are validated here — whether each one actually belongs
             * to this circle is a question about the circle, and the controller
             * answers it by intersecting with what it already knows.
             */
            'circles' => ['nullable', 'array'],
            'circles.*' => ['uuid'],

            /*
             * Narrows the rows to the people whose name or username contains
             * it. A filter on who is listed rather than on what is counted:
             * the range and the circles decide the numbers, and searching
             * leaves both alone.
             */
            'search' => ['nullable', 'string', 'max:60'],

            /*
             * The award filters, each its own checkbox.
             *
             * Like the search, these narrow who is listed rather than what is
             * counted: a filtered page still shows the same numbers against
             * the same range, with fewer people standing next to them. None of
             * them ticked is the ordinary tab, which is what an untouched set
             * of boxes means.
             */
            'complete_with_reference' => ['nullable', 'boolean'],
            'complete' => ['nullable', 'boolean'],
            // Only meaningful alongside `complete`, and ignored without it.
            'exclude_referenced' => ['nullable', 'boolean'],

            /*
             * Who turned up and who did not. Both together is everybody, which
             * is what neither of them already means.
             */
            'activity' => ['nullable', 'array'],
            'activity.*' => ['string', Rule::in(self::ACTIVITY)],

            /*
             * The kinds somebody has been logging. Ticking two asks for people
             * doing both, not either — the box is about what a practice looks
             * like, and somebody doing one of the two is not doing both.
             */
            'kinds' => ['nullable', 'array'],
            'kinds.*' => ['string', Rule::in(Post::WIN_TYPES)],

            /*
             * Floors rather than exact figures, which is what a cutoff for an
             * award is. Zero is allowed through validation and read as absent:
             * a list of people with at least no points is the whole list.
             */
            'min_points' => ['nullable', 'integer', 'min:0'],
            'min_days' => ['nullable', 'integer', 'min:0'],

            // A streak still running, and how long it has to be.
            'streaking' => ['nullable', 'boolean'],
            'min_streak' => ['nullable', 'integer', 'min:0'],

            'sort' => ['nullable', 'string', Rule::in(self::SORTS)],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'The end of the range cannot fall before its start.',
        ];
    }

    /**
     * The circles the reader asked to count, if they narrowed it.
     *
     * @return list<string>
     */
    public function circleIds(): array
    {
        $ids = $this->validated('circles') ?? [];

        return array_values(array_unique(array_map(strval(...), $ids)));
    }

    /**
     * What was typed into the search box, if anything.
     *
     * Blank reads as absent: a box somebody cleared should list everybody
     * again, not look for a member named nothing at all.
     */
    public function search(): ?string
    {
        $search = trim($this->string('search')->value());

        return $search === '' ? null : $search;
    }

    /**
     * Which complete runs the reader asked to see, if any.
     *
     * `exclude_referenced` is folded away where `complete` is not ticked: it is
     * a qualification on that box rather than a filter of its own, and left
     * standing on its own it would read as an award nobody asked for.
     *
     * @return array{with_reference: bool, complete: bool, exclude_referenced: bool}
     */
    public function completionFilters(): array
    {
        $complete = $this->boolean('complete');

        return [
            'with_reference' => $this->boolean('complete_with_reference'),
            'complete' => $complete,
            'exclude_referenced' => $complete && $this->boolean('exclude_referenced'),
        ];
    }

    /**
     * Everything narrowing who is listed, gathered in one place.
     *
     * All of it narrows the rows rather than the range: a filtered page counts
     * the same days in the same circles, with fewer people standing next to
     * the numbers. Only the search is kept out, because it travels with the
     * text box rather than with the filter panel.
     *
     * @return array{
     *     completion: array{with_reference: bool, complete: bool, exclude_referenced: bool},
     *     activity: list<string>,
     *     kinds: list<string>,
     *     min_points: int|null,
     *     min_days: int|null,
     *     streaking: bool,
     *     min_streak: int|null,
     * }
     */
    public function filters(): array
    {
        return [
            'completion' => $this->completionFilters(),
            'activity' => $this->ticked('activity', self::ACTIVITY),
            'kinds' => $this->ticked('kinds', Post::WIN_TYPES),
            'min_points' => $this->threshold('min_points'),
            'min_days' => $this->threshold('min_days'),
            'streaking' => $this->boolean('streaking'),
            'min_streak' => $this->threshold('min_streak'),
        ];
    }

    /**
     * Which column orders the table, and which way.
     *
     * Names climb and numbers fall, which is what each is asked for: a list of
     * people reads alphabetically, and a leaderboard reads from the top.
     *
     * @return array{by: string, direction: string}
     */
    public function sort(): array
    {
        $by = $this->validated('sort') ?? 'name';

        return [
            'by' => $by,
            'direction' => $this->validated('direction')
                ?? ($by === 'name' ? 'asc' : 'desc'),
        ];
    }

    /**
     * Whether anything here is asked of the wins rather than of the people.
     *
     * What decides whether every member's days have to be gathered before the
     * page is cut. Both activity boxes together ask nothing — that is everybody
     * — so it takes exactly one of them to count.
     */
    public function weighsEveryone(): bool
    {
        $filters = $this->filters();

        return $filters['completion']['with_reference']
            || $filters['completion']['complete']
            || count($filters['activity']) === 1
            || $filters['kinds'] !== []
            || $filters['min_points'] !== null
            || $filters['min_days'] !== null
            || in_array($this->sort()['by'], ['days', 'points'], true);
    }

    /**
     * Whether the table is ordered by one of the win columns, which is the one
     * thing the days cannot answer — those columns count wins, not days.
     */
    public function ranksByKind(): bool
    {
        return in_array($this->sort()['by'], Post::WIN_TYPES, true);
    }

    /**
     * The boxes ticked out of those on offer, in the order the page offers them.
     *
     * A filter is the set that was chosen rather than the order it arrived in,
     * so a hand-written query string cannot shuffle or repeat its way into a
     * different answer.
     *
     * @param  list<string>  $offered
     * @return list<string>
     */
    protected function ticked(string $key, array $offered): array
    {
        $chosen = $this->validated($key) ?? [];

        return array_values(array_filter(
            $offered,
            fn (string $option): bool => in_array($option, $chosen, true)
        ));
    }

    /**
     * A floor typed into one of the number boxes, or null where it was left
     * empty. Zero reads as absent — at least none is not a narrowing.
     */
    protected function threshold(string $key): ?int
    {
        $value = $this->validated($key);

        return $value === null || (int) $value === 0 ? null : (int) $value;
    }

    /**
     * The first day counted.
     *
     * Whole days rather than a rolling window of hours, so a win logged this
     * morning and one logged last night both count for today — which is how a
     * streak counts them too.
     */
    public function from(): CarbonInterface
    {
        return $this->filled('from')
            ? $this->date('from')->startOfDay()
            : today()->subDays(self::DEFAULT_DAYS - 1);
    }

    /**
     * The last day counted, taken to the end of it so a win logged at teatime
     * on the closing day is not left out of its own range.
     */
    public function to(): CarbonInterface
    {
        return $this->filled('to')
            ? $this->date('to')->endOfDay()
            : today()->endOfDay();
    }

    /**
     * How many days the range covers, both ends included.
     */
    public function days(): int
    {
        return (int) $this->from()->startOfDay()->diffInDays($this->to()->startOfDay()) + 1;
    }
}
