<?php

namespace App\Actions\Profiles;

use App\Enums\Weekday;

/**
 * Helpers for a consultation's weekly availability: one window per available day, written as
 * 24-hour `HH:MM` strings in the freelancer's profile time zone. A window may end at `24:00`.
 *
 * @phpstan-type Window array{day: string, start: string, end: string}
 */
final class ConsultationSchedule
{
    /** Minutes between the choices offered in the editor. */
    public const STEP_MINUTES = 30;

    public const DEFAULT_START = '09:00';

    public const DEFAULT_END = '17:00';

    /**
     * Minutes after midnight for a 24-hour `H:MM` / `HH:MM` time, or null when it is not one.
     * `24:00` (end of day) is valid.
     */
    public static function minutes(string $time): ?int
    {
        if (preg_match('/^(\d{1,2}):([0-5]\d)$/', trim($time), $parts) !== 1) {
            return null;
        }

        $minutes = (int) $parts[1] * 60 + (int) $parts[2];

        return $minutes <= 24 * 60 ? $minutes : null;
    }

    /**
     * The canonical `HH:MM` form of a valid time, or null.
     */
    public static function normalize(string $time): ?string
    {
        $minutes = self::minutes($time);

        return $minutes === null ? null : sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * Times that can be picked in the editor. A window starts anywhere before midnight and ends
     * anywhere after midnight, up to and including 24:00.
     *
     * @return list<string>
     */
    public static function options(bool $forEnd = false): array
    {
        $times = [];

        for ($minutes = $forEnd ? self::STEP_MINUTES : 0; $minutes <= ($forEnd ? 24 * 60 : 24 * 60 - self::STEP_MINUTES); $minutes += self::STEP_MINUTES) {
            $times[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }

        return $times;
    }

    /**
     * Group days that share a window into short lines, for example `Mon–Fri 09:00–17:00`.
     *
     * @param  list<Window>  $availability
     * @return list<string>
     */
    public static function summary(array $availability): array
    {
        $groups = [];

        foreach ($availability as $window) {
            $groups["{$window['start']}–{$window['end']}"][] = Weekday::from($window['day']);
        }

        $lines = [];

        foreach ($groups as $hours => $days) {
            usort($days, fn (Weekday $a, Weekday $b): int => $a->position() <=> $b->position());

            $lines[] = self::dayRuns($days).' '.$hours;
        }

        return $lines;
    }

    /**
     * @param  list<Weekday>  $days  Sorted Monday first.
     */
    private static function dayRuns(array $days): string
    {
        $runs = [];
        $run = [];

        foreach ($days as $day) {
            if ($run !== [] && $day->position() !== end($run)->position() + 1) {
                $runs[] = $run;
                $run = [];
            }

            $run[] = $day;
        }

        $runs[] = $run;

        return collect($runs)
            ->map(fn (array $run): string => count($run) > 2
                ? $run[0]->short().'–'.end($run)->short()
                : implode(', ', array_map(fn (Weekday $day): string => $day->short(), $run)))
            ->implode(', ');
    }
}
