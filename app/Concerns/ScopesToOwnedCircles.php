<?php

namespace App\Concerns;

use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\Post;
use App\Models\User;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Narrows a console statistic to the circles the viewer owns.
 *
 * The console answers "how are my circles doing", so every figure is bounded by
 * `circles.owner_id`. Owning nothing is a legitimate state: each of these
 * returns an empty set rather than the whole app.
 */
trait ScopesToOwnedCircles
{
    /**
     * The ids of the circles this person owns, as a subquery.
     *
     * Left unresolved so it composes into a `whereIn` rather than dragging
     * every id into PHP to be sent straight back.
     */
    protected function ownedCircleIds(User $owner): BuilderContract
    {
        return Circle::query()
            ->where('owner_id', $owner->id)
            ->select('id');
    }

    /**
     * The circles this person owns.
     *
     * @return Builder<Circle>
     */
    protected function ownedCircles(User $owner): Builder
    {
        return Circle::query()->where('owner_id', $owner->id);
    }

    /**
     * The memberships held across the circles this person owns.
     *
     * @return Builder<CircleMembership>
     */
    protected function membershipsInOwnedCircles(User $owner): Builder
    {
        return CircleMembership::query()
            ->whereIn('circle_id', $this->ownedCircleIds($owner));
    }

    /**
     * The posts shared into any circle this person owns.
     *
     * An existence check rather than a join: a post shared into three of the
     * owner's circles matches the pivot three times, and joining would count
     * that one win three times over.
     *
     * @return Builder<Post>
     */
    protected function postsInOwnedCircles(User $owner): Builder
    {
        return Post::query()->whereHas(
            'circles',
            fn (Builder $circles) => $circles->where('circles.owner_id', $owner->id),
        );
    }

    /**
     * One win detail table, narrowed to wins posted into the owner's circles.
     *
     * Written against the query builder rather than Eloquent because the three
     * detail tables have no shared model to hang a scope on.
     */
    protected function winsInOwnedCircles(string $table, User $owner): QueryBuilder
    {
        return DB::table($table)->whereExists(
            fn (QueryBuilder $query) => $query
                ->from('circle_post')
                ->join('circles', 'circles.id', '=', 'circle_post.circle_id')
                ->whereColumn('circle_post.post_id', "{$table}.post_id")
                ->where('circles.owner_id', $owner->id),
        );
    }
}
