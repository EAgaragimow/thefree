<?php

use App\Actions\Blog\DiscardBlogPostChanges;
use App\Actions\Blog\PublishBlogPostChanges;
use App\Actions\Blog\UnpublishBlogPost;
use App\Enums\BlogPostStatus;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Models\BlogPost;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

function publishedArticle(array $attributes = []): BlogPost
{
    return BlogPost::factory()->published()->create([
        'title' => 'Original title',
        'excerpt' => 'Original excerpt',
        'body' => '<p>Original body</p>',
        ...$attributes,
    ]);
}

it('labels an article Draft, Published or In Draft', function () {
    $draft = BlogPost::factory()->create();
    $live = publishedArticle();
    $edited = publishedArticle(['draft' => ['title' => 'Edited']]);

    expect($draft->statusLabel())->toBe('Draft')
        ->and($draft->statusColor())->toBe('gray')
        ->and($live->statusLabel())->toBe('Published')
        ->and($live->statusColor())->toBe('success')
        ->and($edited->statusLabel())->toBe('In Draft')
        ->and($edited->statusColor())->toBe('orange');
});

it('moves a published article to In Draft as soon as it is edited, without touching what clients see', function () {
    $post = publishedArticle();

    Livewire::test(EditBlogPost::class, ['record' => $post->getKey()])
        ->fillForm(['title' => 'Edited title', 'body' => '<p>Edited body</p>']);

    $post->refresh();

    expect($post->statusLabel())->toBe('In Draft')
        ->and($post->title)->toBe('Original title')
        ->and($post->body)->toBe('<p>Original body</p>')
        ->and($post->draft['title'])->toBe('Edited title')
        ->and($post->draft['body'])->toBe('<p>Edited body</p>');
});

it('keeps showing clients the published version while changes are in draft', function () {
    $post = publishedArticle();

    Livewire::test(EditBlogPost::class, ['record' => $post->getKey()])
        ->fillForm(['title' => 'Edited title', 'body' => '<p>Edited body</p>']);

    $client = User::factory()->client()->paid()->create();

    $this->actingAs($client)
        ->get(route('blog.show', $post->slug))
        ->assertOk()
        ->assertSee('Original title')
        ->assertSee('Original body')
        ->assertDontSee('Edited title')
        ->assertDontSee('Edited body');

    $this->get(route('blog.index'))->assertSee('Original title')->assertDontSee('Edited title');
});

it('shows the pending changes when the article is opened again', function () {
    $post = publishedArticle(['draft' => [
        'title' => 'Pending title',
        'slug' => 'original-slug',
        'excerpt' => 'Original excerpt',
        'body' => '<p>Original body</p>',
        'cover_path' => null,
    ]]);

    Livewire::test(EditBlogPost::class, ['record' => $post->getKey()])
        ->assertFormSet(['title' => 'Pending title']);
});

it('publishes the changes with the Publish changes button', function () {
    $post = publishedArticle();

    Livewire::test(EditBlogPost::class, ['record' => $post->getKey()])
        ->assertSee('Publish changes')
        ->assertDontSee('Save changes')
        ->fillForm(['title' => 'Edited title'])
        ->call('save')
        ->assertHasNoFormErrors();

    $post->refresh();

    expect($post->title)->toBe('Edited title')
        ->and($post->draft)->toBeNull()
        ->and($post->statusLabel())->toBe('Published');

    $this->actingAs(User::factory()->client()->paid()->create())
        ->get(route('blog.show', $post->slug))
        ->assertSee('Edited title');
});

it('does not publish changes that are invalid', function () {
    $post = publishedArticle();

    Livewire::test(EditBlogPost::class, ['record' => $post->getKey()])
        ->fillForm(['title' => ''])
        ->call('save')
        ->assertHasFormErrors(['title' => 'required']);

    expect($post->refresh()->title)->toBe('Original title')
        ->and($post->draft)->toBeNull();
});

it('keeps an edit that does not validate yet out of the draft', function () {
    $post = publishedArticle();

    Livewire::test(EditBlogPost::class, ['record' => $post->getKey()])
        ->fillForm(['excerpt' => '']);

    expect($post->refresh()->draft)->toBeNull();
});

it('is Published again when the edits are put back as they were', function () {
    $post = publishedArticle();

    Livewire::test(EditBlogPost::class, ['record' => $post->getKey()])
        ->fillForm(['title' => 'Edited title'])
        ->fillForm(['title' => 'Original title']);

    expect($post->refresh()->draft)->toBeNull()
        ->and($post->statusLabel())->toBe('Published');
});

it('saves a draft article straight away and calls the button Save changes', function () {
    $post = BlogPost::factory()->create(['title' => 'Draft title']);

    Livewire::test(EditBlogPost::class, ['record' => $post->getKey()])
        ->assertSee('Save changes')
        ->assertDontSee('Publish changes')
        ->fillForm(['title' => 'Better draft title']);

    expect($post->refresh()->draft)->toBeNull()
        ->and($post->title)->toBe('Draft title');

    Livewire::test(EditBlogPost::class, ['record' => $post->getKey()])
        ->fillForm(['title' => 'Better draft title'])
        ->call('save');

    expect($post->refresh()->title)->toBe('Better draft title')
        ->and($post->status)->toBe(BlogPostStatus::Draft);
});

it('discards the changes and goes back to the published version', function () {
    $post = publishedArticle(['draft' => ['title' => 'Pending title']]);

    Livewire::test(EditBlogPost::class, ['record' => $post->getKey()])
        ->callAction('discardChanges')
        ->assertFormSet(['title' => 'Original title']);

    expect($post->refresh()->draft)->toBeNull()
        ->and($post->title)->toBe('Original title');
});

it('publishes or discards pending changes from the list', function () {
    $toPublish = publishedArticle(['draft' => ['title' => 'Pending title']]);
    $toDiscard = publishedArticle(['draft' => ['title' => 'Other pending title']]);
    $untouched = publishedArticle();

    Livewire::test(ListBlogPosts::class)
        ->assertTableActionHidden('publishChanges', $untouched)
        ->assertTableActionHidden('discardChanges', $untouched)
        ->callTableAction('publishChanges', $toPublish)
        ->callTableAction('discardChanges', $toDiscard);

    expect($toPublish->refresh()->title)->toBe('Pending title')
        ->and($toPublish->draft)->toBeNull()
        ->and($toDiscard->refresh()->title)->toBe('Original title')
        ->and($toDiscard->draft)->toBeNull();
});

it('filters the list by Draft, Published and In Draft', function () {
    $draft = BlogPost::factory()->create();
    $live = publishedArticle();
    $edited = publishedArticle(['draft' => ['title' => 'Pending title']]);

    Livewire::test(ListBlogPosts::class)
        ->filterTable('status', 'draft')
        ->assertCanSeeTableRecords([$draft])
        ->assertCanNotSeeTableRecords([$live, $edited])
        ->filterTable('status', 'published')
        ->assertCanSeeTableRecords([$live])
        ->assertCanNotSeeTableRecords([$draft, $edited])
        ->filterTable('status', 'in_draft')
        ->assertCanSeeTableRecords([$edited])
        ->assertCanNotSeeTableRecords([$draft, $live]);
});

it('shows the status as a badge in the list', function () {
    publishedArticle(['draft' => ['title' => 'Pending title']]);

    Livewire::test(ListBlogPosts::class)->assertSee('In Draft');
});

it('refuses to publish or discard changes that do not exist', function () {
    $live = publishedArticle();
    $draft = BlogPost::factory()->create();

    expect(fn () => app(PublishBlogPostChanges::class)($live))->toThrow(DomainException::class)
        ->and(fn () => app(DiscardBlogPostChanges::class)($live))->toThrow(DomainException::class)
        ->and(fn () => app(PublishBlogPostChanges::class)($draft, ['title' => 'x']))->toThrow(DomainException::class);
});

it('turns pending changes into the content when an In Draft article is unpublished', function () {
    $post = publishedArticle(['draft' => ['title' => 'Pending title']]);

    app(UnpublishBlogPost::class)($post);

    $post->refresh();

    expect($post->status)->toBe(BlogPostStatus::Draft)
        ->and($post->title)->toBe('Pending title')
        ->and($post->draft)->toBeNull();
});
