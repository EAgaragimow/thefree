<?php

use App\Actions\Profiles\ProfileContent;
use App\Actions\Profiles\VideoEmbed;
use App\Enums\PricingMode;
use App\Models\ConsultationOffer;
use App\Models\FreelancerProfile;
use App\Models\Highlight;
use App\Models\Service;

it('turns supported video links into embeddable player urls', function (string $url, string $expected) {
    expect(VideoEmbed::embedUrl($url))->toBe($expected)
        ->and(VideoEmbed::isSupported($url))->toBeTrue();
})->with([
    'youtube watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtube short link' => ['https://youtu.be/dQw4w9WgXcQ?t=10', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtube shorts on mobile' => ['https://m.youtube.com/shorts/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'youtube embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
    'vimeo' => ['https://vimeo.com/76979871', 'https://player.vimeo.com/video/76979871'],
    'vimeo unlisted' => ['https://vimeo.com/76979871/abcdef1234', 'https://player.vimeo.com/video/76979871'],
    'vimeo player' => ['https://player.vimeo.com/video/76979871', 'https://player.vimeo.com/video/76979871'],
    'loom share' => ['https://www.loom.com/share/0123456789abcdef0123456789abcdef', 'https://www.loom.com/embed/0123456789abcdef0123456789abcdef'],
    'loom embed' => ['https://loom.com/embed/0123456789ABCDEF0123456789abcdef', 'https://www.loom.com/embed/0123456789abcdef0123456789abcdef'],
]);

it('rejects video links that are not from a supported provider', function (string $url) {
    expect(VideoEmbed::embedUrl($url))->toBeNull()
        ->and(VideoEmbed::isSupported($url))->toBeFalse();
})->with([
    'other host' => 'https://evil.example/watch?v=dQw4w9WgXcQ',
    'lookalike host' => 'https://youtube.com.evil.example/watch?v=dQw4w9WgXcQ',
    'javascript scheme' => 'javascript:alert(1)',
    'ftp scheme' => 'ftp://youtu.be/dQw4w9WgXcQ',
    'short youtube id' => 'https://www.youtube.com/watch?v=short',
    'not a url' => 'not a url',
    'vimeo without id' => 'https://vimeo.com/abc',
    'loom with bad id' => 'https://loom.com/share/xyz',
    'empty' => '',
]);

it('normalises highlights, services and offers with stable identifiers', function () {
    $content = ProfileContent::fromArray([
        'highlights' => [['id' => 5, 'title' => 'Logo', 'gallery' => ['a.jpg', '']]],
        'services' => [
            ['title' => 'Branding', 'pricing_mode' => 'custom-quote', 'price_cents' => 99900],
            ['title' => 'Mystery', 'pricing_mode' => 'nonsense', 'price_cents' => '12000'],
        ],
        'consultation_offers' => [['type' => 'paid', 'enabled' => 'true', 'duration_minutes' => '60', 'price_cents' => '15000']],
    ]);

    expect($content->highlights()[0]['uid'])->toBe('live-5')
        ->and($content->highlights()[0]['gallery'])->toBe(['a.jpg'])
        ->and($content->services()[0]['uid'])->toStartWith('new-')
        ->and($content->services()[0]['id'])->toBeNull()
        ->and($content->services()[0]['price_cents'])->toBeNull()
        ->and($content->services()[1]['pricing_mode'])->toBe(PricingMode::Fixed->value)
        ->and($content->services()[1]['price_cents'])->toBe(12000)
        ->and($content->consultationOffers()[1])->toMatchArray(['type' => 'paid', 'enabled' => true, 'duration_minutes' => 60, 'price_cents' => 15000]);
});

it('keeps the uid of an item when content is merged again', function () {
    $content = ProfileContent::fromArray(['services' => [['title' => 'Branding', 'pricing_mode' => 'fixed', 'price_cents' => 5000]]]);
    $uid = $content->services()[0]['uid'];

    expect($content->with(['about' => 'Changed'])->services()[0]['uid'])->toBe($uid);
});

it('reads the live highlights, services and offers into content that equals itself', function () {
    $profile = FreelancerProfile::factory()->published()->create();
    Highlight::factory()->for($profile, 'profile')->create(['title' => 'Logo', 'sort_order' => 1]);
    Highlight::factory()->for($profile, 'profile')->create(['title' => 'App', 'sort_order' => 0]);
    Service::factory()->for($profile, 'profile')->customQuote()->create();
    ConsultationOffer::factory()->for($profile, 'profile')->paid()->create(['duration_minutes' => 30, 'price_cents' => 9000]);

    $content = $profile->liveContent();

    expect(array_column($content->highlights(), 'title'))->toBe(['App', 'Logo'])
        ->and($content->services()[0]['pricing_mode'])->toBe('custom-quote')
        ->and($content->consultationOffers()[1]['price_cents'])->toBe(9000)
        ->and(ProfileContent::fromArray($content->toArray())->equals($content))->toBeTrue()
        ->and($content->changedKeys($profile->liveContent()))->toBe([]);
});

it('reports which collections differ from the live content', function () {
    $profile = FreelancerProfile::factory()->published()->create();
    $service = Service::factory()->for($profile, 'profile')->create(['title' => 'Old']);
    $live = $profile->liveContent();

    $edited = $live->with(['services' => [[...$live->services()[0], 'title' => 'New']]]);

    expect($edited->changedKeys($live))->toBe(['services'])
        ->and($service->id)->toBe($edited->services()[0]['id']);
});

it('lists every uploaded file a profile refers to once', function () {
    $content = ProfileContent::fromArray([
        'photo_path' => 'photos/me.jpg',
        'highlights' => [
            ['title' => 'A', 'cover_path' => 'covers/a.jpg', 'gallery' => ['gallery/1.jpg', 'covers/a.jpg']],
            ['title' => 'B', 'gallery' => ['gallery/1.jpg', 'gallery/2.jpg']],
        ],
    ]);

    expect($content->mediaPaths())->toEqualCanonicalizing(['photos/me.jpg', 'covers/a.jpg', 'gallery/1.jpg', 'gallery/2.jpg']);
});

it('finds problems that would make offers unreviewable', function (array $payload, string $expected) {
    expect(ProfileContent::fromArray($payload)->offerProblems())->toContain($expected);
})->with([
    'service without price' => [['services' => [['title' => 'Branding', 'pricing_mode' => 'fixed']]], 'The service "Branding" needs a price.'],
    'starting from without price' => [['services' => [['title' => 'Logo', 'pricing_mode' => 'starting-from', 'price_cents' => 0]]], 'The service "Logo" needs a price.'],
    'service without title' => [['services' => [['title' => '', 'pricing_mode' => 'custom-quote']]], 'Every service needs a title.'],
    'paid consultation below one dollar' => [['consultation_offers' => [['type' => 'paid', 'enabled' => true, 'duration_minutes' => 30, 'price_cents' => 99, 'availability' => [['day' => 'mon', 'start' => '09:00', 'end' => '17:00']]]]], 'The paid consultation needs a price of at least $1.'],
    'consultation too short' => [['consultation_offers' => [['type' => 'free', 'enabled' => true, 'duration_minutes' => 5, 'availability' => [['day' => 'mon', 'start' => '09:00', 'end' => '17:00']]]]], 'The free consultation must last between 15 and 480 minutes.'],
    'consultation too long' => [['consultation_offers' => [['type' => 'free', 'enabled' => true, 'duration_minutes' => 600, 'availability' => [['day' => 'mon', 'start' => '09:00', 'end' => '17:00']]]]], 'The free consultation must last between 15 and 480 minutes.'],
    'consultation without days' => [['consultation_offers' => [['type' => 'free', 'enabled' => true, 'duration_minutes' => 30, 'availability' => []]]], 'The free consultation needs at least one available day.'],
    'window ends before it starts' => [['consultation_offers' => [['type' => 'free', 'enabled' => true, 'duration_minutes' => 30, 'availability' => [['day' => 'tue', 'start' => '17:00', 'end' => '09:00']]]]], 'On Tuesday the free consultation must end after it starts.'],
    'window shorter than the call' => [['consultation_offers' => [['type' => 'paid', 'enabled' => true, 'duration_minutes' => 60, 'price_cents' => 5000, 'availability' => [['day' => 'wed', 'start' => '09:00', 'end' => '09:30']]]]], 'On Wednesday the hours 09:00–09:30 are shorter than the 60-minute paid consultation.'],
    'highlight without title' => [['highlights' => [['title' => '']]], 'Every highlight needs a title.'],
    'unsupported video' => [['highlights' => [['title' => 'A', 'video_urls' => ['https://evil.example/v']]]], 'The video link "https://evil.example/v" is not a supported YouTube, Vimeo or Loom link.'],
]);

it('accepts well-formed offers', function () {
    $content = ProfileContent::fromArray([
        'highlights' => [['title' => 'A', 'video_urls' => ['https://youtu.be/dQw4w9WgXcQ']]],
        'services' => [['title' => 'Quote', 'pricing_mode' => 'custom-quote'], ['title' => 'Fixed', 'pricing_mode' => 'fixed', 'price_cents' => 1000]],
        'consultation_offers' => [
            ['type' => 'free', 'enabled' => true, 'duration_minutes' => 15, 'availability' => [['day' => 'mon', 'start' => '09:00', 'end' => '24:00']]],
            ['type' => 'paid', 'enabled' => true, 'duration_minutes' => 60, 'price_cents' => 9000, 'availability' => [['day' => 'fri', 'start' => '09:00', 'end' => '17:00']]],
        ],
    ]);

    expect($content->offerProblems())->toBe([]);
});

it('never reports problems for a consultation that is switched off', function () {
    $content = ProfileContent::fromArray(['consultation_offers' => [['type' => 'paid', 'enabled' => false, 'duration_minutes' => 1, 'price_cents' => 0]]]);

    expect($content->offerProblems())->toBe([]);
});

it('always has exactly one free and one paid consultation, free first', function () {
    expect(array_column(ProfileContent::fromArray([])->consultationOffers(), 'type'))->toBe(['free', 'paid'])
        ->and(array_column(ProfileContent::fromArray(['consultation_offers' => [['type' => 'paid'], ['type' => 'paid'], ['type' => 'free']]])->consultationOffers(), 'type'))->toBe(['free', 'paid'])
        ->and(ProfileContent::fromArray(['consultation_offers' => [['duration_minutes' => 60], ['type' => 'bogus']]])->consultationOffers()[0]['enabled'])->toBeFalse();
});

it('keeps a free consultation free and the schedule in 24-hour form', function () {
    $content = ProfileContent::fromArray(['consultation_offers' => [
        ['type' => 'free', 'enabled' => true, 'price_cents' => 5000, 'availability' => [
            ['day' => 'fri', 'start' => '9:00', 'end' => '17:30'],
            ['day' => 'mon', 'start' => '08:00', 'end' => '12:00'],
            ['day' => 'mon', 'start' => '13:00', 'end' => '14:00'],
            ['day' => 'sun', 'start' => '9:00 AM', 'end' => '5:00 PM'],
            ['day' => 'xyz', 'start' => '09:00', 'end' => '10:00'],
            ['day' => 'tue', 'start' => '09:00', 'end' => '25:00'],
        ]],
    ]]);

    expect($content->consultationOffers()[0]['price_cents'])->toBe(0)
        ->and($content->consultationOffers()[0]['availability'])->toBe([
            ['day' => 'mon', 'start' => '08:00', 'end' => '12:00'],
            ['day' => 'fri', 'start' => '09:00', 'end' => '17:30'],
        ]);
});
