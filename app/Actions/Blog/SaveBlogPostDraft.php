<?php

namespace App\Actions\Blog;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use DomainException;
use Illuminate\Support\Arr;

class SaveBlogPostDraft
{
    /**
     * Keep an editor's changes to a published article aside, so clients keep reading the published
     * version until the changes are published. Putting the content back exactly as it is live drops
     * the draft and the article is simply Published again.
     *
     * @param  array<string, mixed>  $content
     *
     * @throws DomainException
     */
    public function __invoke(BlogPost $post, array $content): void
    {
        if ($post->status !== BlogPostStatus::Published) {
            throw new DomainException('Only a published article keeps its changes as a draft.');
        }

        $live = $post->only(BlogPost::CONTENT_FIELDS);
        $content = [...$live, ...Arr::only($content, BlogPost::CONTENT_FIELDS)];
        $previousDraftCover = $post->draft['cover_path'] ?? null;

        $isChanged = $this->normalised($content) !== $this->normalised($live);

        $post->forceFill(['draft' => $isChanged ? $content : null])->save();

        if ($previousDraftCover !== ($content['cover_path'] ?? null) && $previousDraftCover !== $live['cover_path']) {
            BlogPost::deleteCoverFile($previousDraftCover);
        }
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, string>
     */
    private function normalised(array $content): array
    {
        return array_map(fn (mixed $value): string => (string) $value, $content);
    }
}
