<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Countries, their states/provinces and the cities of each state, read from
 * `resources/data/locations`.
 *
 * The data comes from a world locations list that was cleaned up (broken text encoding repaired,
 * duplicate states merged, duplicate cities dropped) and split into one small file per country, so
 * a request only reads the country it needs. `countries.json` lists the countries; each
 * `<file>.json` holds `{"states": {"State": ["City", ...]}}`. Some countries have no states and
 * some states have no cities; callers must handle empty lists.
 */
class Locations
{
    /** @var list<array{name: string, file: string}>|null */
    private static ?array $index = null;

    /**
     * The states of the country read last. One country is enough: a request works with one country,
     * and keeping more would only hold memory for nothing.
     *
     * @var array<string, array<string, list<string>>>
     */
    private static array $states = [];

    /**
     * @return list<string>
     */
    public static function countries(): array
    {
        return array_column(self::index(), 'name');
    }

    public static function hasCountry(string $country): bool
    {
        return in_array($country, self::countries(), true);
    }

    /**
     * @return list<string>
     */
    public static function states(string $country): array
    {
        return array_keys(self::statesOf($country));
    }

    /**
     * @return list<string>
     */
    public static function cities(string $country, string $state): array
    {
        return self::statesOf($country)[$state] ?? [];
    }

    /**
     * @return array<string, list<string>>
     */
    private static function statesOf(string $country): array
    {
        if (isset(self::$states[$country])) {
            return self::$states[$country];
        }

        $file = collect(self::index())->firstWhere('name', $country)['file'] ?? null;

        if ($file === null) {
            return [];
        }

        $states = File::json(resource_path("data/locations/{$file}.json"))['states'] ?? [];

        self::$states = [$country => $states];

        return $states;
    }

    /**
     * @return list<array{name: string, file: string}>
     */
    private static function index(): array
    {
        return self::$index ??= File::json(resource_path('data/locations/countries.json'));
    }
}
