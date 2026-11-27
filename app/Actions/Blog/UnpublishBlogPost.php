<?php

namespace App\Actions\Blog;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use DomainException;

class UnpublishBlogPost
{
    /**
     * Take an article back to draft; clients stop seeing it at once. Edits that were still waiting
     * to be published become the article's content, since a draft has no separate pending version.
     *
     * @throws DomainException
     */
    public function __invoke(BlogPost $post): void
    {
        if ($post->status === BlogPostStatus::Draft) {
            throw new DomainException('The article is not published.');
        }

        $post->forceFill([
            ...($post->draft ?? []),
            'status' => BlogPostStatus::Draft,
            'draft' => null,
        ])->save();
    }
}
