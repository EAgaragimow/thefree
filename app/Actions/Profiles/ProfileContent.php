<?php

namespace App\Actions\Profiles;

use App\Models\FreelancerProfile;
use Carbon\CarbonInterface;

/**
 * The freelancer-editable content of a profile: what a revision's payload stores and what
 * an approval writes to the live profile. Admin- and system-controlled columns (score,
 * featured_*, published_at) are deliberately not part of it.
 *
 * @phpstan-type Experience array{company: string, role: string, started_at: string, ended_at: string|null, description: string|null}
 * @phpstan-type Payload array{
 *     v: int,
 *     photo_path: string|null,
 *     professional_title: string|null,
 *     about: string|null,
 *     location: string|null,
 *     timezone: string,
 *     website: string|null,
 *     social_links: array<string, string>,
 *     category_ids: list<int>,
 *     skill_ids: list<int>,
 *     experiences: list<Experience>,
 * }
 */
final class ProfileContent
{
    public const VERSION = 1;

    /**
     * Scalar columns stored directly on the live profile.
     *
     * @var list<string>
     */
    public const SCALAR_FIELDS = [
        'photo_path',
        'professional_title',
        'about',
        'location',
        'timezone',
        'website',
        'social_links',
    ];

    /**
     * @param  Payload  $data
     */
    private function __construct(private readonly array $data) {}

    public static function fromProfile(FreelancerProfile $profile): self
    {
        return self::fromArray([
            'photo_path' => $profile->photo_path,
            'professional_title' => $profile->professional_title,
            'about' => $profile->about,
            'location' => $profile->location,
            'timezone' => $profile->timezone,
            'website' => $profile->website,
            'social_links' => $profile->social_links ?? [],
            'category_ids' => $profile->categories()->pluck('categories.id')->all(),
            'skill_ids' => $profile->skills()->pluck('skills.id')->all(),
            'experiences' => $profile->experiences()->get()->map(fn ($experience): array => [
                'company' => $experience->company,
                'role' => $experience->role,
                'started_at' => $experience->started_at->toDateString(),
                'ended_at' => $experience->ended_at?->toDateString(),
                'description' => $experience->description,
            ])->all(),
        ]);
    }

    /**
     * Normalise any (possibly partial) payload so equal content always compares equal.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $ids = fn (mixed $values): array => collect($values ?? [])
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $nullable = fn (mixed $value): ?string => filled($value) ? (string) $value : null;

        return new self([
            'v' => self::VERSION,
            'photo_path' => $nullable($payload['photo_path'] ?? null),
            'professional_title' => $nullable($payload['professional_title'] ?? null),
            'about' => $nullable($payload['about'] ?? null),
            'location' => $nullable($payload['location'] ?? null),
            'timezone' => (string) ($payload['timezone'] ?? 'UTC'),
            'website' => $nullable($payload['website'] ?? null),
            'social_links' => collect($payload['social_links'] ?? [])
                ->filter(fn ($url) => filled($url))
                ->map(fn ($url): string => (string) $url)
                ->sortKeys()
                ->all(),
            'category_ids' => $ids($payload['category_ids'] ?? []),
            'skill_ids' => $ids($payload['skill_ids'] ?? []),
            'experiences' => collect($payload['experiences'] ?? [])->map(fn (array $experience): array => [
                'company' => (string) $experience['company'],
                'role' => (string) $experience['role'],
                'started_at' => self::date($experience['started_at']),
                'ended_at' => filled($experience['ended_at'] ?? null) ? self::date($experience['ended_at']) : null,
                'description' => $nullable($experience['description'] ?? null),
            ])->values()->all(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $changes  Keys of the payload to replace.
     */
    public function with(array $changes): self
    {
        return self::fromArray([...$this->data, ...$changes]);
    }

    /**
     * @return Payload
     */
    public function toArray(): array
    {
        return $this->data;
    }

    /**
     * @return array<string, mixed>
     */
    public function scalarAttributes(): array
    {
        return array_intersect_key($this->data, array_flip(self::SCALAR_FIELDS));
    }

    /**
     * @return list<int>
     */
    public function categoryIds(): array
    {
        return $this->data['category_ids'];
    }

    /**
     * @return list<int>
     */
    public function skillIds(): array
    {
        return $this->data['skill_ids'];
    }

    /**
     * @return list<Experience>
     */
    public function experiences(): array
    {
        return $this->data['experiences'];
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function equals(self $other): bool
    {
        return $this->data == $other->data;
    }

    /**
     * Payload keys whose value differs from the given content.
     *
     * @return list<string>
     */
    public function changedKeys(self $other): array
    {
        return collect($this->data)
            ->except('v')
            ->filter(fn (mixed $value, string $key): bool => $value != $other->data[$key])
            ->keys()
            ->all();
    }

    /**
     * Storage paths of every uploaded file the content refers to.
     *
     * @return list<string>
     */
    public function mediaPaths(): array
    {
        return array_values(array_filter([$this->data['photo_path']]));
    }

    private static function date(mixed $value): string
    {
        return $value instanceof CarbonInterface ? $value->toDateString() : substr((string) $value, 0, 10);
    }
}
