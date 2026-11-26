<?php

use App\Models\BlogPost;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Blog / Resources')] class extends Component {
    public int $postId;

    /**
     * Any client can open a published article; what they see of it depends on their plan. Drafts
     * and scheduled articles do not exist for clients.
     */
    public function mount(string $slug): void
    {
        $this->postId = BlogPost::query()->published()->where('slug', $slug)->firstOrFail()->id;
    }

    /**
     * Loaded without the body: only a client with full access ever reads it from the database.
     */
    public function post(): BlogPost
    {
        return BlogPost::query()->published()->withoutBody()->findOrFail($this->postId);
    }

    /**
     * The article text as safe HTML, or null when the client's plan does not include it.
     */
    public function body(): ?string
    {
        if (Gate::denies('read-full-blog-article')) {
            return null;
        }

        $body = BlogPost::query()->whereKey($this->postId)->value('body');

        return RichContentRenderer::make($body)->toHtml();
    }
};
?>

<div class="mx-auto w-full max-w-3xl space-y-6">
    @php($post = $this->post())
    @php($body = $this->body())

    <div>
        <flux:button size="sm" variant="ghost" icon="arrow-left" href="{{ route('blog.index') }}">All articles</flux:button>
    </div>

    @if ($post->cover_path)
        <img src="{{ Storage::disk('public')->url($post->cover_path) }}" alt="" class="aspect-video w-full rounded-lg object-cover">
    @endif

    <div class="space-y-2">
        <flux:text size="sm">{{ $post->published_at->format('Y-m-d') }}</flux:text>
        <flux:heading size="xl" level="1">{{ $post->title }}</flux:heading>
        <flux:text class="text-lg">{{ $post->excerpt }}</flux:text>
    </div>

    @if ($body !== null)
        <article class="space-y-4 leading-relaxed [&_a]:underline [&_blockquote]:border-s-4 [&_blockquote]:ps-4 [&_h2]:mt-6 [&_h2]:text-2xl [&_h2]:font-semibold [&_h3]:mt-4 [&_h3]:text-xl [&_h3]:font-semibold [&_ol]:list-decimal [&_ol]:ps-6 [&_ul]:list-disc [&_ul]:ps-6">
            {!! $body !!}
        </article>
    @else
        <flux:callout icon="lock-closed" heading="The full article is part of the Paid Plan">
            <flux:modal.trigger name="upgrade">
                <flux:button size="sm" variant="primary" class="mt-2">Upgrade to read</flux:button>
            </flux:modal.trigger>
        </flux:callout>

        <x-upgrade-modal />
    @endif
</div>
