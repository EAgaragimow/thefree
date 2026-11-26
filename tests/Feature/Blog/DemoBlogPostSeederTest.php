<?php

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use Database\Seeders\DemoBlogPostSeeder;

beforeEach(function () {
    app()->detectEnvironment(fn () => 'local');
});

it('seeds five published articles and one draft, without duplicating on a second run', function () {
    (new DemoBlogPostSeeder)->run();
    (new DemoBlogPostSeeder)->run();

    expect(BlogPost::query()->count())->toBe(6)
        ->and(BlogPost::query()->published()->count())->toBe(5)
        ->and(BlogPost::query()->where('status', BlogPostStatus::Draft)->count())->toBe(1);
});

it('refuses to run outside the local environment', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new DemoBlogPostSeeder)->run())->toThrow(RuntimeException::class);
    expect(BlogPost::query()->count())->toBe(0);
});
