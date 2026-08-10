<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Carbon;

/**
 * Where a day starts.
 *
 * Timestamps are stored in UTC and that does not change. What changes is the
 * clock the *boundaries* are drawn on: a win logged at one in the morning
 * belongs to today, and in UTC+8 the UTC date does not turn over until eight
 * in the morning — so a streak judged in UTC expires eight hours late, a win
 * logged before breakfast is filed under yesterday, and the week strip spends
 * every morning showing the wrong day as today.
 *
 * Every day-boundary question goes through here so there is one answer to it.
 * The rule for using it:
 *
 *   - comparing or naming days → work in `now()`/`zone()`, which is local
 *   - handing bounds to the database → `::utc()` them first, because the
 *     column is UTC and a local-timezone bound would be off by the offset
 *   - taking the date off a stored value → `::on()`, which moves it onto the
 *     display clock before the date is read
 */
class Day
{
    /**
     * The clock days are measured on.
     */
    public static function zone(): DateTimeZone
    {
        return new DateTimeZone(config('app.display_timezone'));
    }

    /**
     * Now, on that clock.
     */
    public static function now(): Carbon
    {
        return Carbon::now(self::zone());
    }

    /**
     * Midnight at the start of the day the given moment falls on, local.
     *
     * Defaults to today. The argument is what lets a caller judge a streak
     * from a day other than this one without reaching for the clock itself.
     */
    public static function startOf(?CarbonInterface $at = null): Carbon
    {
        return ($at === null ? self::now() : self::on($at))->startOfDay();
    }

    /**
     * A stored moment, moved onto the display clock.
     *
     * Reading `toDateString()` off a UTC value answers the UTC question. This
     * is what turns it back into the day the person who logged it was living
     * in.
     */
    public static function on(CarbonInterface $at): Carbon
    {
        return Carbon::instance($at->toDateTime())->setTimezone(self::zone());
    }

    /**
     * The date a stored moment falls on, local, as `2026-08-10`.
     */
    public static function dateOf(CarbonInterface $at): string
    {
        return self::on($at)->toDateString();
    }

    /**
     * Local midnight on a calendar date that was stored without one.
     *
     * `last_win_on` is a date, not an instant: it is read back tagged UTC
     * because that is the application timezone, but nothing about it means
     * midnight-in-UTC. Converting it would be the mistake — at a negative
     * offset it would slide onto the day before. This rebuilds the same
     * calendar date on the display clock so it can be compared with one.
     */
    public static function startOfDate(CarbonInterface $date): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $date->format('Y-m-d'), self::zone())
            ->startOfDay();
    }

    /**
     * Midnight local, expressed in UTC — a bound safe to hand a query.
     */
    public static function utc(CarbonInterface $at): Carbon
    {
        return Carbon::instance($at->toDateTime())->utc();
    }
}
