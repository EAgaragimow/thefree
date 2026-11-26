<?php

use App\Actions\Blog\PublishBlogPost;
use App\Actions\Blog\UnpublishBlogPost;
use App\Enums\BlogPostStatus;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Models\BlogPost;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('has a Blog / Resources page in the Content group', function () {
    $this->get('/admin/blog-posts')->assertOk()->assertSee('Blog / Resources');

    expect(BlogPostResource::getNavigationGroup())->toBe('Content');
});

it('keeps everyone but admins out', function (string $role) {
    $this->actingAs(User::factory()->{$role}()->create())->get('/admin/blog-posts')->assertForbidden();
})->with(['client', 'freelancer']);

it('lists articles with their status', function () {
    $draft = BlogPost::factory()->create();
    $live = BlogPost::factory()->published()->create();

    Livewire::test(ListBlogPosts::class)
        ->assertCanSeeTableRecords([$draft, $live])
        ->filterTable('status', 'published')
        ->assertCanSeeTableRecords([$live])
        ->assertCanNotSeeTableRecords([$draft]);
});

it('creates an article as a draft written by the signed-in admin', function () {
    Livewire::test(CreateBlogPost::class)
        ->fillForm([
            'title' => 'Scoping a project',
            'slug' => 'scoping-a-project',
            'excerpt' => 'How to scope.',
            'body' => '<p>Start with the outcome.</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $post = BlogPost::query()->where('slug', 'scoping-a-project')->firstOrFail();

    expect($post->status)->toBe(BlogPostStatus::Draft)
        ->and($post->published_at)->toBeNull()
        ->and($post->author_id)->toBe($this->admin->id);
});

it('requires a unique slug and the main fields', function () {
    BlogPost::factory()->create(['slug' => 'taken']);

    Livewire::test(CreateBlogPost::class)
        ->fillForm(['title' => '', 'slug' => 'taken', 'excerpt' => '', 'body' => ''])
        ->call('create')
        ->assertHasFormErrors(['title' => 'required', 'excerpt' => 'required', 'body', 'slug' => 'unique']);
});

it('edits an article', function () {
    $post = BlogPost::factory()->create();

    Livewire::test(EditBlogPost::class, ['record' => $post->getKey()])
        ->fillForm(['title' => 'A better title'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($post->refresh()->title)->toBe('A better title');
});

it('publishes and unpublishes from the list', function () {
    $post = BlogPost::factory()->create();

    Livewire::test(ListBlogPosts::class)
        ->assertTableActionVisible('publish', $post)
        ->assertTableActionHidden('unpublish', $post)
        ->callTableAction('publish', $post);

    expect($post->refresh()->status)->toBe(BlogPostStatus::Published)
        ->and($post->published_at)->not->toBeNull();

    Livewire::test(ListBlogPosts::class)
        ->assertTableActionHidden('publish', $post)
        ->callTableAction('unpublish', $post);

    expect($post->refresh()->status)->toBe(BlogPostStatus::Draft);
});

it('keeps the first publication date when an article goes live again', function () {
    $post = BlogPost::factory()->published()->create(['published_at' => now()->subDays(10)]);
    $firstDate = $post->published_at;

    app(UnpublishBlogPost::class)($post);
    app(PublishBlogPost::class)($post->refresh());

    expect($post->refresh()->published_at->equalTo($firstDate))->toBeTrue();
});

it('refuses to publish twice or unpublish a draft', function () {
    $live = BlogPost::factory()->published()->create();
    $draft = BlogPost::factory()->create();

    expect(fn () => app(PublishBlogPost::class)($live))->toThrow(DomainException::class)
        ->and(fn () => app(UnpublishBlogPost::class)($draft))->toThrow(DomainException::class);
});

it('stops showing an unpublished article to clients', function () {
    $post = BlogPost::factory()->published()->create();

    app(UnpublishBlogPost::class)($post);

    $this->actingAs(User::factory()->client()->paid()->create())
        ->get(route('blog.show', $post->slug))
        ->assertNotFound();
});

it('deletes an article', function () {
    $post = BlogPost::factory()->create();

    Livewire::test(ListBlogPosts::class)->callTableAction('delete', $post);

    expect(BlogPost::query()->count())->toBe(0);
});
