<?php

use App\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Blog / Resources')] class extends Component {
    use WithPagination;

    public const PER_PAGE = 9;

    /**
     * Published articles, newest first. The body is never loaded here: the list only needs the
     * excerpt, for every client.
     */
    public function posts(): LengthAwarePaginator
    {
        return BlogPost::query()
            ->published()
            ->withoutBody()
            ->latest('published_at')
            ->paginate(self::PER_PAGE);
    }
};
?>

<div class="space-y-6">
    @php($posts = $this->posts())
    @php($hasFullAccess = Gate::allows('read-full-blog-article'))

    <div>
        <flux:heading size="xl" level="1">Blog / Resources</flux:heading>
        <flux:text class="mt-1">
            @if ($hasFullAccess)
                Guides and insights for working with freelancers.
            @else
                Guides and insights for working with freelancers. Full articles are part of the Paid Plan.
            @endif
        </flux:text>
    </div>

    @if ($posts->isEmpty())
        <flux:callout icon="newspaper" heading="No articles yet">
            Nothing has been published yet. Please check back soon.
        </flux:callout>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($posts as $post)
                <a
                    wire:key="post-{{ $post->id }}"
                    href="{{ route('blog.show', $post->slug) }}"
                    class="flex flex-col overflow-hidden rounded-lg border border-zinc-200 transition hover:border-zinc-400 dark:border-zinc-700 dark:hover:border-zinc-500"
                >
                    @if ($post->cover_path)
                        <img src="{{ Storage::disk('public')->url($post->cover_path) }}" alt="" class="aspect-video w-full object-cover" loading="lazy">
                    @endif

                    <div class="flex flex-1 flex-col gap-2 p-4">
                        <flux:text size="sm">{{ $post->published_at->format('Y-m-d') }}</flux:text>
                        <flux:heading size="lg">{{ $post->title }}</flux:heading>
                        <flux:text class="line-clamp-4">{{ $post->excerpt }}</flux:text>
                    </div>
                </a>
            @endforeach
        </div>

        <flux:pagination :paginator="$posts" />
    @endif
</div>
