<?php

use App\Support\Locations;

it('lists the countries alphabetically with repaired names', function () {
    $countries = Locations::countries();

    expect($countries)->toHaveCount(250)
        ->and($countries)->toContain('Ukraine', 'Curaçao')
        ->and($countries[0])->toBe('Afghanistan')
        ->and(array_unique($countries))->toHaveCount(250);
});

it('returns the states of a country and the cities of a state', function () {
    expect(Locations::hasCountry('Ukraine'))->toBeTrue()
        ->and(Locations::hasCountry('Atlantis'))->toBeFalse()
        ->and(Locations::states('Ukraine'))->toContain('Kyiv', 'Cherkaska')
        ->and(Locations::cities('Ukraine', 'Cherkaska'))->toContain('Cherkasy')
        ->and(Locations::states('Atlantis'))->toBe([])
        ->and(Locations::cities('Ukraine', 'Atlantis'))->toBe([]);
});

it('has countries without states and states without cities', function () {
    expect(Locations::states('Gibraltar'))->toBe([])
        ->and(Locations::states('Spain'))->toContain('Andalusia')
        ->and(Locations::cities('Spain', 'Andalusia'))->toBe([]);
});

it('keeps accented names readable instead of broken by a double encoding', function () {
    expect(Locations::cities('Andorra', 'Sant Julià de Lòria'))->toBe(['Sant Julià de Lòria'])
        ->and(Locations::cities('Finland', 'Central Finland'))->toContain('Äänekoski');
});

it('lists every state and city once', function () {
    foreach (['Azerbaijan', 'Russia', 'Spain', 'Indonesia'] as $country) {
        $states = Locations::states($country);

        expect($states)->toBe(array_values(array_unique($states)));

        foreach ($states as $state) {
            $cities = Locations::cities($country, $state);

            expect($cities)->toBe(array_values(array_unique($cities)));
        }
    }
});
