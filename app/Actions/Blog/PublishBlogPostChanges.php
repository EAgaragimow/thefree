<?php

namespace App\Actions\Blog;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use DomainException;
use Illuminate\Support\Arr;

class PublishBlogPostChanges
{
    /**
     * Make a published article's edits visible to clients. Without `$content` the saved draft is
     * published; the editor's Publish changes button passes the form's content instead.
     *
     * @param  array<string, mixed>|null  $content
     *
     * @throws DomainException
     */
    public function __invoke(BlogPost $post, ?array $content = null): void
    {
        if ($post->status !== BlogPostStatus::Published) {
            throw new DomainException('Only a published article has changes to publish.');
        }

        $content ??= $post->draft;

        if ($content === null) {
            throw new DomainException('There are no unpublished changes.');
        }

        $liveCover = $post->cover_path;
        $draftCover = $post->draft['cover_path'] ?? null;
        $content = [...$post->only(BlogPost::CONTENT_FIELDS), ...Arr::only($content, BlogPost::CONTENT_FIELDS)];

        $post->forceFill([...$content, 'draft' => null])->save();

        foreach (array_unique(array_filter([$liveCover, $draftCover])) as $cover) {
            if ($cover !== $post->cover_path) {
                BlogPost::deleteCoverFile($cover);
            }
        }
    }
}
