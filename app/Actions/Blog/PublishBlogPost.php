<?php

namespace App\Actions\Blog;

use App\Enums\BlogPostStatus;
use App\Models\BlogPost;
use DomainException;

class PublishBlogPost
{
    /**
     * Make a draft visible to clients. The first publication date is kept when a post goes live
     * again after being unpublished.
     *
     * @throws DomainException
     */
    public function __invoke(BlogPost $post): void
    {
        if ($post->status === BlogPostStatus::Published) {
            throw new DomainException('The article is already published.');
        }

        $post->forceFill([
            'status' => BlogPostStatus::Published,
            'published_at' => $post->published_at ?? now(),
        ])->save();
    }
}
