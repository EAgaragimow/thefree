<?php

namespace App\Actions\Blog;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use DomainException;

class UnpublishBlogPost
{
    /**
     * Take an article back to draft; clients stop seeing it at once.
     *
     * @throws DomainException
     */
    public function __invoke(BlogPost $post): void
    {
        if ($post->status === BlogPostStatus::Draft) {
            throw new DomainException('The article is not published.');
        }

        $post->forceFill(['status' => BlogPostStatus::Draft])->save();
    }
}
