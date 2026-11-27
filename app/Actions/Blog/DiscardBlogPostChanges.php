<?php

namespace App\Actions\Blog;

use App\Models\BlogPost;
use DomainException;

class DiscardBlogPostChanges
{
    /**
     * Throw away a published article's unpublished edits; it goes back to its published version.
     *
     * @throws DomainException
     */
    public function __invoke(BlogPost $post): void
    {
        if (! $post->hasUnpublishedChanges()) {
            throw new DomainException('There are no unpublished changes.');
        }

        $draftCover = $post->draft['cover_path'] ?? null;

        $post->forceFill(['draft' => null])->save();

        if ($draftCover !== $post->cover_path) {
            BlogPost::deleteCoverFile($draftCover);
        }
    }
}
