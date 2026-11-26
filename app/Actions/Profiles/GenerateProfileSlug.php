<?php

namespace App\Actions\Profiles;

use App\Models\FreelancerProfile;
use Illuminate\Support\Str;

/**
 * The slug in a freelancer's public address (`/freelancers/{slug}`), made from their name:
 *
 *  1. the first name alone: `john`;
 *  2. if that is taken, the first and last name: `john-barnstone`;
 *  3. if that is taken too, a random six-letter code is added: `john-barnstone-jasqrt`.
 *
 * A name of one word skips the second step. The slug is made once, when the profile is created, so
 * a published address does not change when the name does.
 */
class GenerateProfileSlug
{
    /** Length of the random code added when the name alone is not unique. */
    public const SUFFIX_LENGTH = 6;

    /** Used when a name has nothing a slug can be made from. */
    public const FALLBACK = 'freelancer';

    public function __invoke(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $first = Str::slug($words[0] ?? '');
        $last = count($words) > 1 ? Str::slug($words[array_key_last($words)]) : '';
        $base = $first !== '' ? $first : self::FALLBACK;
        $full = $last !== '' ? "{$base}-{$last}" : $base;

        foreach (array_unique([$base, $full]) as $candidate) {
            if (! $this->isTaken($candidate)) {
                return $candidate;
            }
        }

        do {
            $candidate = $full.'-'.Str::lower(Str::random(self::SUFFIX_LENGTH));
        } while ($this->isTaken($candidate));

        return $candidate;
    }

    private function isTaken(string $slug): bool
    {
        return FreelancerProfile::query()->where('slug', $slug)->exists();
    }
}
