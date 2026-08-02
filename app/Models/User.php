<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Concerns\ServesMediaUrls;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property string $id
 * @property string $full_name
 * @property string|null $username
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password_hash
 * @property-read string|null $avatar_url
 * @property string|null $bio
 * @property string|null $cover_gradient
 * @property-read string|null $cover_url
 * @property int $streak_days
 * @property int $longest_streak
 * @property Carbon|null $last_win_on
 * @property int $followers_count
 * @property int $following_count
 * @property int $wins_count
 * @property bool $is_private
 * @property bool $is_admin
 * @property Carbon|null $last_active_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['full_name', 'username', 'email', 'password_hash', 'bio', 'cover_gradient', 'is_private', 'is_admin', 'last_active_at'])]
#[Hidden(['password_hash', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'media'])]
class User extends Authenticatable implements HasMedia, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUuids, InteractsWithMedia, Notifiable, PasskeyAuthenticatable, ServesMediaUrls, SoftDeletes, TwoFactorAuthenticatable;

    /**
     * The collections a profile's own photos are kept in.
     */
    public const AVATAR_COLLECTION = 'avatar';

    public const COVER_COLLECTION = 'cover';

    /**
     * The accessors appended when a user is serialised on its own.
     *
     * Both used to be columns, so they arrived in the payload without asking.
     * Inertia shares the signed-in user as the model itself, and the app reads
     * `avatar_url` straight off it — dropping the columns without this would
     * quietly take the header photo off every page.
     *
     * @var list<string>
     */
    protected $appends = ['avatar_url', 'cover_url'];

    /**
     * Always load the photos with the person.
     *
     * `avatar_url` is read on nearly every payload the application sends — a
     * feed, a member list, a leaderboard — and it is worked out from the media
     * rather than kept in a column. Left to load on demand it would be one
     * query per person on the page.
     *
     * The cost is one extra query wherever a user is fetched at all, including
     * the places that never look at the photo. That is the trade being made
     * deliberately: one query too many everywhere beats a hundred somewhere.
     *
     * @var list<string>
     */
    protected $with = ['media'];

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'streak_days' => 0,
        'longest_streak' => 0,
        'followers_count' => 0,
        'following_count' => 0,
        'wins_count' => 0,
        'is_private' => false,
        'is_admin' => false,
    ];

    /**
     * Get the name of the column holding the user's hashed password.
     *
     * The schema stores it as `password_hash` rather than Laravel's default
     * `password`, so both the column name and the accessor have to be pointed
     * at it for the guard, the password broker and the `current_password` rule.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    /**
     * Get the user's hashed password.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_hash' => 'hashed',
            'streak_days' => 'integer',
            'longest_streak' => 'integer',
            'last_win_on' => 'date',
            'followers_count' => 'integer',
            'following_count' => 'integer',
            'wins_count' => 'integer',
            'is_private' => 'boolean',
            'is_admin' => 'boolean',
            'last_active_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Register the profile photo collections.
     *
     * Both hold one file. `singleFile` is what makes changing a photo a
     * replacement rather than an addition: the one before it goes, file and
     * all. Uploading a new avatar used to leave the old one on the disk with
     * nothing left pointing at it.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::AVATAR_COLLECTION)->singleFile();
        $this->addMediaCollection(self::COVER_COLLECTION)->singleFile();
    }

    /**
     * The address of this person's profile photo, or null where there is none.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        return $this->mediaUrl(self::AVATAR_COLLECTION);
    }

    /**
     * The address of the photo across the top of their profile.
     *
     * Null leaves the header falling back to `cover_gradient`, which is why
     * removing a cover does not touch it.
     */
    public function getCoverUrlAttribute(): ?string
    {
        return $this->mediaUrl(self::COVER_COLLECTION);
    }

    /**
     * The small wins this user has posted.
     *
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * The comments this user has left on any post.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * The likes this user has given.
     *
     * @return HasMany<PostLike, $this>
     */
    public function postLikes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    /**
     * The users this user follows.
     *
     * @return BelongsToMany<User, $this>
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'followee_id')
            ->withTimestamps();
    }

    /**
     * The users following this user.
     *
     * @return BelongsToMany<User, $this>
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'followee_id', 'follower_id')
            ->withTimestamps();
    }

    /**
     * The circles this user has joined.
     *
     * @return BelongsToMany<Circle, $this>
     */
    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class, 'circle_memberships')
            ->withPivot('joined_at')
            ->withTimestamps();
    }

    /**
     * The membership rows tying this user to circles.
     *
     * @return HasMany<CircleMembership, $this>
     */
    public function circleMemberships(): HasMany
    {
        return $this->hasMany(CircleMembership::class);
    }

    /**
     * The circles this user made.
     *
     * @return HasMany<Circle, $this>
     */
    public function ownedCircles(): HasMany
    {
        return $this->hasMany(Circle::class, 'owner_id');
    }

    /**
     * The devices this user can be reached on.
     *
     * @return HasMany<PushToken, $this>
     */
    public function pushTokens(): HasMany
    {
        return $this->hasMany(PushToken::class);
    }

    /**
     * Circle invitations waiting on this user, or already answered by them.
     *
     * @return HasMany<CircleInvitation, $this>
     */
    public function circleInvitations(): HasMany
    {
        return $this->hasMany(CircleInvitation::class, 'invitee_id');
    }

    /**
     * The stories this user has posted, expired ones included.
     *
     * @return HasMany<Story, $this>
     */
    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    /**
     * The habits this user is tracking.
     *
     * @return HasMany<Habit, $this>
     */
    public function habits(): HasMany
    {
        return $this->hasMany(Habit::class);
    }

    /**
     * Every habit entry this user has logged.
     *
     * @return HasMany<HabitLog, $this>
     */
    public function habitLogs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    /**
     * The streak the user is actually on right now.
     *
     * `streak_days` only moves when a win is posted, so a user who last showed
     * up a week ago still carries the number they stopped at. Reading the
     * column straight would tell them they are on a seven day streak they in
     * fact broke; this reports what is still standing.
     *
     * A win yesterday still counts: the day is not missed until it is over, so
     * the streak survives until a whole day passes with nothing on it.
     *
     * @param  Carbon|null  $on  The day to judge from, defaulting to today.
     */
    public function currentStreak(?Carbon $on = null): int
    {
        $lastWin = $this->last_win_on?->copy()->startOfDay();

        if ($lastWin === null) {
            return 0;
        }

        $yesterday = ($on ?? Carbon::now())->copy()->startOfDay()->subDay();

        return $lastWin->greaterThanOrEqualTo($yesterday)
            ? $this->streak_days
            : 0;
    }

    /**
     * Load the active-story flag onto this user.
     *
     * The scope answers the question for a query; this answers it for a model
     * already in hand, such as the signed-in user.
     */
    public function loadActiveStory(): static
    {
        return $this->loadExists([
            'stories as has_active_story' => fn (Builder $stories) => $stories->active(),
        ]);
    }

    /**
     * Load whether there is anything new about this person's own story.
     *
     * What lights the ring around your own bubble. Everyone else's ring means
     * "not watched yet", which cannot mean anything about your own — so this
     * means the two things you might not have caught up on:
     *
     * - a story you have posted and not yet opened the viewers on, which is
     *   what makes a fresh one light up before anybody has watched it;
     * - somebody watching since the last time you did open them.
     *
     * Both are the same question asked of `viewers_checked_at`: never looked,
     * or looked before the newest view arrived. Correlated against the story
     * row rather than a fixed time, so each story is measured from its own
     * last look and checking one does not dim a ring another earned.
     */
    public function loadNewStoryActivity(): static
    {
        return $this->loadExists([
            'stories as has_new_story_activity' => fn (Builder $stories) => $stories
                ->active()
                ->where(fn (Builder $unchecked) => $unchecked
                    ->whereNull('stories.viewers_checked_at')
                    ->orWhereHas('views', fn (Builder $views) => $views->whereColumn(
                        'story_views.viewed_at',
                        '>',
                        'stories.viewers_checked_at',
                    ))
                ),
        ]);
    }

    /**
     * Flag whether the user has a story still running.
     *
     * A subquery rather than a loaded relation: the answer is one boolean per
     * user, and pulling the stories themselves back to count them would drag
     * every image URL of every author into a feed that never shows them.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function withActiveStory(Builder $query): void
    {
        $query->withExists([
            'stories as has_active_story' => fn (Builder $stories) => $stories->active(),
        ]);
    }

    /**
     * Flag whether the user has a story the reader has not watched yet.
     *
     * What decides the colour of the ring around an avatar: something new is
     * worth drawing brightly, and a run already watched through is worth
     * showing as still there but spent. Needs to know who is asking, since the
     * answer is different for every reader — unlike `withActiveStory`, which is
     * the same for everyone.
     *
     * @param  Builder<User>  $query
     * @param  User  $reader  The person the ring is being drawn for.
     */
    #[Scope]
    protected function withUnseenStory(Builder $query, User $reader): void
    {
        $query->withExists([
            'stories as has_unseen_story' => fn (Builder $stories) => $stories
                ->active()
                ->whereDoesntHave(
                    'views',
                    fn (Builder $views) => $views->where('viewer_id', $reader->getKey()),
                ),
        ]);
    }

    /**
     * The in-app notifications addressed to this user.
     *
     * This deliberately shadows the database-notification relation from
     * `Notifiable`; the app stores its own notification shape in `notifications`
     * and only uses `Notifiable` for the mail channel.
     *
     * @return HasMany<Notification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
