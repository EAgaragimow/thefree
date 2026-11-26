<?php

use App\Models\BlogPost;
use App\Models\User;

function articleWithBody(array $attributes = []): BlogPost
{
    return BlogPost::factory()->published()->create([
        'title' => 'Hiring your first freelancer',
        'excerpt' => 'A short summary anyone can read.',
        'body' => '<p>The secret full text of the article.</p>',
        ...$attributes,
    ]);
}

it('sends guests to the login page', function () {
    $post = articleWithBody();

    $this->get(route('blog.index'))->assertRedirect(route('login'));
    $this->get(route('blog.show', $post->slug))->assertRedirect(route('login'));
});

it('keeps freelancers and admins out of the client blog', function (string $role) {
    $post = articleWithBody();

    $this->actingAs(User::factory()->{$role}()->create());

    $this->get(route('blog.index'))->assertForbidden();
    $this->get(route('blog.show', $post->slug))->assertForbidden();
})->with(['freelancer', 'admin']);

it('lists published articles with their excerpts for a Free client', function () {
    articleWithBody();

    $this->actingAs(User::factory()->client()->create())
        ->get(route('blog.index'))
        ->assertOk()
        ->assertSee('Hiring your first freelancer')
        ->assertSee('A short summary anyone can read.')
        ->assertDontSee('The secret full text of the article.');
});

it('lists the newest articles first', function () {
    articleWithBody(['title' => 'Older article', 'published_at' => now()->subDays(5)]);
    articleWithBody(['title' => 'Newer article', 'published_at' => now()->subDay()]);

    $this->actingAs(User::factory()->client()->create())
        ->get(route('blog.index'))
        ->assertSeeInOrder(['Newer article', 'Older article']);
});

it('pages the list', function () {
    foreach (range(1, 11) as $day) {
        articleWithBody(['title' => "Article {$day}", 'published_at' => now()->subDays(20 - $day)]);
    }

    $this->actingAs(User::factory()->client()->create());

    $this->get(route('blog.index'))
        ->assertSee('Article 11')
        ->assertDontSee('Article 1<', false);

    $this->get(route('blog.index', ['page' => 2]))
        ->assertSee('Article 2')
        ->assertDontSee('Article 11');
});

it('shows a payment-restricted client only the excerpt and an upgrade prompt', function () {
    $post = articleWithBody();

    $this->actingAs(User::factory()->client()->paymentRestricted()->create())
        ->get(route('blog.show', $post->slug))
        ->assertOk()
        ->assertSee('Hiring your first freelancer')
        ->assertSee('A short summary anyone can read.')
        ->assertDontSee('The secret full text of the article.')
        ->assertSee('The full article is part of the Paid Plan')
        ->assertSee('data-modal="upgrade"', false);
});

it('shows a Free client only the excerpt', function () {
    $post = articleWithBody();

    $this->actingAs(User::factory()->client()->create())
        ->get(route('blog.show', $post->slug))
        ->assertOk()
        ->assertSee('A short summary anyone can read.')
        ->assertDontSee('The secret full text of the article.')
        ->assertSee('The full article is part of the Paid Plan');
});

it('shows a Paid client the whole article', function () {
    $post = articleWithBody();

    $this->actingAs(User::factory()->client()->paid()->create())
        ->get(route('blog.show', $post->slug))
        ->assertOk()
        ->assertSee('A short summary anyone can read.')
        ->assertSee('The secret full text of the article.')
        ->assertDontSee('The full article is part of the Paid Plan');
});

it('strips scripts from the article body', function () {
    $post = articleWithBody(['body' => '<p>Safe text</p><script>alert(1)</script>']);

    $this->actingAs(User::factory()->client()->paid()->create())
        ->get(route('blog.show', $post->slug))
        ->assertSee('Safe text')
        ->assertDontSee('<script>alert(1)</script>', false);
});

it('hides drafts and scheduled articles from clients', function () {
    $draft = BlogPost::factory()->create(['title' => 'Still a draft']);
    $scheduled = articleWithBody(['title' => 'Coming next week', 'published_at' => now()->addWeek()]);

    $client = User::factory()->client()->paid()->create();

    $this->actingAs($client)
        ->get(route('blog.index'))
        ->assertOk()
        ->assertDontSee('Still a draft')
        ->assertDontSee('Coming next week');

    $this->get(route('blog.show', $draft->slug))->assertNotFound();
    $this->get(route('blog.show', $scheduled->slug))->assertNotFound();
});

it('returns 404 for an article that does not exist', function () {
    $this->actingAs(User::factory()->client()->create())
        ->get(route('blog.show', 'no-such-article'))
        ->assertNotFound();
});

it('shows an empty state when nothing is published', function () {
    $this->actingAs(User::factory()->client()->create())
        ->get(route('blog.index'))
        ->assertOk()
        ->assertSee('No articles yet');
});

it('adds Blog to the brand navigation between Recommended and Brand settings', function () {
    $this->actingAs(User::factory()->client()->create())
        ->get(route('client.dashboard'))
        ->assertSeeInOrder(['Recommended', 'Blog', 'Brand settings']);
});
