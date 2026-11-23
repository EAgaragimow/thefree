<?php

namespace App\Actions\Profiles;

use App\Enums\ConsultationType;
use App\Enums\PricingMode;
use App\Enums\Weekday;
use App\Models\FreelancerProfile;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * The freelancer-editable content of a profile: what a revision's payload stores and what
 * an approval writes to the live profile. Admin- and system-controlled columns (score,
 * featured_*, published_at) are deliberately not part of it.
 *
 * Highlights and services are ordered lists. Each item has a `uid` that is stable within a revision
 * and an `id` that points at the live row it was copied from (null for items that are new), so
 * approving a revision updates live rows in place instead of recreating them.
 *
 * Consultations are not a list the freelancer edits: there are always exactly two offers, one per
 * {@see ConsultationType} (free first, then paid), each switched on or off and configured with a
 * length, price, description and weekly availability (24-hour times in the profile's time zone).
 *
 * @phpstan-type Experience array{company: string, role: string, started_at: string, ended_at: string|null, description: string|null}
 * @phpstan-type Link array{label: string, url: string}
 * @phpstan-type Highlight array{id: int|null, uid: string, title: string, cover_path: string|null, description: string|null, gallery: list<string>, video_urls: list<string>, links: list<Link>}
 * @phpstan-type Service array{id: int|null, uid: string, title: string, description: string|null, pricing_mode: string, price_cents: int|null}
 * @phpstan-type Window array{day: string, start: string, end: string}
 * @phpstan-type ConsultationOffer array{type: string, enabled: bool, duration_minutes: int, price_cents: int, description: string|null, availability: list<Window>}
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
 *     highlights: list<Highlight>,
 *     services: list<Service>,
 *     consultation_offers: list<ConsultationOffer>,
 * }
 */
final class ProfileContent
{
    public const VERSION = 1;

    public const MAX_HIGHLIGHTS = 12;

    public const MAX_GALLERY_IMAGES = 12;

    public const MAX_VIDEOS = 5;

    public const MAX_LINKS = 5;

    public const MAX_SERVICES = 20;

    public const MIN_CONSULTATION_MINUTES = 15;

    public const MAX_CONSULTATION_MINUTES = 480;

    public const DEFAULT_CONSULTATION_MINUTES = 30;

    /** A paid consultation costs at least $1.00. */
    public const MIN_PAID_CONSULTATION_CENTS = 100;

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
            'highlights' => $profile->highlights()->get()->map(fn ($highlight): array => [
                'id' => $highlight->id,
                'title' => $highlight->title,
                'cover_path' => $highlight->cover_path,
                'description' => $highlight->description,
                'gallery' => $highlight->gallery ?? [],
                'video_urls' => $highlight->video_urls ?? [],
                'links' => $highlight->links ?? [],
            ])->all(),
            'services' => $profile->services()->get()->map(fn ($service): array => [
                'id' => $service->id,
                'title' => $service->title,
                'description' => $service->description,
                'pricing_mode' => $service->pricing_mode->value,
                'price_cents' => $service->price_cents,
            ])->all(),
            'consultation_offers' => $profile->consultationOffers()->get()->map(fn ($offer): array => [
                'type' => $offer->type->value,
                'enabled' => $offer->is_enabled,
                'duration_minutes' => $offer->duration_minutes,
                'price_cents' => $offer->price_cents,
                'description' => $offer->description,
                'availability' => $offer->availability ?? [],
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

        return new self([
            'v' => self::VERSION,
            'photo_path' => self::nullable($payload['photo_path'] ?? null),
            'professional_title' => self::nullable($payload['professional_title'] ?? null),
            'about' => self::nullable($payload['about'] ?? null),
            'location' => self::nullable($payload['location'] ?? null),
            'timezone' => (string) ($payload['timezone'] ?? 'UTC'),
            'website' => self::nullable($payload['website'] ?? null),
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
                'description' => self::nullable($experience['description'] ?? null),
            ])->values()->all(),
            'highlights' => collect($payload['highlights'] ?? [])->map(fn (array $item): array => self::highlight($item))->values()->all(),
            'services' => collect($payload['services'] ?? [])->map(fn (array $item): array => self::service($item))->values()->all(),
            'consultation_offers' => self::normalizeConsultationOffers($payload['consultation_offers'] ?? []),
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

    /**
     * @return list<Highlight>
     */
    public function highlights(): array
    {
        return $this->data['highlights'];
    }

    /**
     * @return list<Service>
     */
    public function services(): array
    {
        return $this->data['services'];
    }

    /**
     * @return list<ConsultationOffer>
     */
    public function consultationOffers(): array
    {
        return $this->data['consultation_offers'];
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
        return collect([$this->data['photo_path']])
            ->merge(collect($this->data['highlights'])->flatMap(fn (array $highlight): array => [$highlight['cover_path'], ...$highlight['gallery']]))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Reasons the offer lists cannot be reviewed as they are, in plain language. Pages validate
     * input as it is typed; this is the server-side guard before a revision is submitted.
     *
     * @return list<string>
     */
    public function offerProblems(): array
    {
        $problems = [];

        foreach ($this->data['services'] as $service) {
            if (blank($service['title'])) {
                $problems[] = 'Every service needs a title.';
            }

            if (PricingMode::from($service['pricing_mode'])->requiresPrice() && ($service['price_cents'] ?? 0) < 1) {
                $problems[] = "The service \"{$service['title']}\" needs a price.";
            }
        }

        foreach ($this->data['consultation_offers'] as $offer) {
            array_push($problems, ...self::consultationProblems($offer));
        }

        foreach ($this->data['highlights'] as $highlight) {
            if (blank($highlight['title'])) {
                $problems[] = 'Every highlight needs a title.';
            }

            foreach ($highlight['video_urls'] as $url) {
                if (VideoEmbed::embedUrl($url) === null) {
                    $problems[] = "The video link \"{$url}\" is not a supported YouTube, Vimeo or Loom link.";
                }
            }
        }

        return array_values(array_unique($problems));
    }

    /**
     * Reasons a consultation that is switched on cannot be reviewed as it is. A switched-off
     * consultation is never published, so it has no problems.
     *
     * @param  ConsultationOffer  $offer
     * @return list<string>
     */
    public static function consultationProblems(array $offer): array
    {
        if (! $offer['enabled']) {
            return [];
        }

        $type = ConsultationType::from($offer['type']);
        $name = Str::lower($type->label());
        $minutes = $offer['duration_minutes'];
        $problems = [];

        if ($minutes < self::MIN_CONSULTATION_MINUTES || $minutes > self::MAX_CONSULTATION_MINUTES) {
            $problems[] = "The {$name} must last between ".self::MIN_CONSULTATION_MINUTES.' and '.self::MAX_CONSULTATION_MINUTES.' minutes.';
        }

        if ($type->isPaid() && $offer['price_cents'] < self::MIN_PAID_CONSULTATION_CENTS) {
            $problems[] = "The {$name} needs a price of at least $1.";
        }

        if ($offer['availability'] === []) {
            $problems[] = "The {$name} needs at least one available day.";
        }

        foreach ($offer['availability'] as $window) {
            $day = Weekday::from($window['day'])->label();
            $length = ConsultationSchedule::minutes($window['end']) - ConsultationSchedule::minutes($window['start']);

            if ($length <= 0) {
                $problems[] = "On {$day} the {$name} must end after it starts.";
            } elseif ($length < $minutes) {
                $problems[] = "On {$day} the hours {$window['start']}–{$window['end']} are shorter than the {$minutes}-minute {$name}.";
            }
        }

        return $problems;
    }

    /**
     * Always one offer per consultation type, free first. Anything in the payload that is not
     * a known type is ignored; a missing type becomes a switched-off offer with defaults.
     *
     * @return list<ConsultationOffer>
     */
    private static function normalizeConsultationOffers(mixed $items): array
    {
        $byType = collect($items)
            ->filter(fn (mixed $item): bool => is_array($item) && ConsultationType::tryFrom((string) ($item['type'] ?? '')) !== null)
            ->keyBy(fn (array $item): string => (string) $item['type']);

        return collect(ConsultationType::cases())
            ->map(fn (ConsultationType $type): array => self::consultationOffer($type, $byType->get($type->value, [])))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return ConsultationOffer
     */
    private static function consultationOffer(ConsultationType $type, array $item): array
    {
        return [
            'type' => $type->value,
            'enabled' => filter_var($item['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'duration_minutes' => (int) ($item['duration_minutes'] ?? self::DEFAULT_CONSULTATION_MINUTES),
            'price_cents' => $type->isPaid() ? max(0, (int) ($item['price_cents'] ?? 0)) : 0,
            'description' => self::nullable($item['description'] ?? null),
            'availability' => self::availability($item['availability'] ?? []),
        ];
    }

    /**
     * One window per day, Monday first. Windows with an unknown day or a time that is not a
     * 24-hour `HH:MM` are dropped.
     *
     * @return list<Window>
     */
    private static function availability(mixed $windows): array
    {
        return collect($windows)
            ->filter(fn (mixed $window): bool => is_array($window))
            ->map(function (array $window): ?array {
                $day = Weekday::tryFrom((string) ($window['day'] ?? ''));
                $start = ConsultationSchedule::normalize((string) ($window['start'] ?? ''));
                $end = ConsultationSchedule::normalize((string) ($window['end'] ?? ''));

                return $day === null || $start === null || $end === null
                    ? null
                    : ['day' => $day->value, 'start' => $start, 'end' => $end];
            })
            ->filter()
            ->unique('day')
            ->sortBy(fn (array $window): int => Weekday::from($window['day'])->position())
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return Highlight
     */
    private static function highlight(array $item): array
    {
        $id = self::itemId($item);

        return [
            'id' => $id,
            'uid' => self::itemUid($item, $id),
            'title' => (string) ($item['title'] ?? ''),
            'cover_path' => self::nullable($item['cover_path'] ?? null),
            'description' => self::nullable($item['description'] ?? null),
            'gallery' => array_values(array_filter(array_map('strval', $item['gallery'] ?? []))),
            'video_urls' => array_values(array_filter(array_map(fn ($url): string => trim((string) $url), $item['video_urls'] ?? []))),
            'links' => collect($item['links'] ?? [])
                ->filter(fn ($link): bool => filled($link['url'] ?? null))
                ->map(fn ($link): array => ['label' => (string) ($link['label'] ?? ''), 'url' => (string) $link['url']])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return Service
     */
    private static function service(array $item): array
    {
        $id = self::itemId($item);
        $mode = PricingMode::tryFrom((string) ($item['pricing_mode'] ?? '')) ?? PricingMode::Fixed;

        return [
            'id' => $id,
            'uid' => self::itemUid($item, $id),
            'title' => (string) ($item['title'] ?? ''),
            'description' => self::nullable($item['description'] ?? null),
            'pricing_mode' => $mode->value,
            'price_cents' => $mode->requiresPrice() && isset($item['price_cents']) ? (int) $item['price_cents'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function itemId(array $item): ?int
    {
        return filled($item['id'] ?? null) ? (int) $item['id'] : null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private static function itemUid(array $item, ?int $id): string
    {
        return (string) ($item['uid'] ?? ($id !== null ? "live-{$id}" : 'new-'.Str::lower(Str::random(12))));
    }

    private static function nullable(mixed $value): ?string
    {
        return filled($value) ? (string) $value : null;
    }

    private static function date(mixed $value): string
    {
        return $value instanceof CarbonInterface ? $value->toDateString() : substr((string) $value, 0, 10);
    }
}
