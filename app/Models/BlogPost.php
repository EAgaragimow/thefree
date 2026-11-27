<?php

namespace App\Models;

use App\Enums\BlogPostStatus;
use Database\Factories\BlogPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['title', 'slug', 'excerpt', 'body', 'cover_path', 'status', 'published_at', 'author_id'])]
class BlogPost extends Model
{
    /** @use HasFactory<BlogPostFactory> */
    use HasFactory;

    /**
     * The columns an editor changes. On a published article, edits to these wait in `draft`.
     */
    public const CONTENT_FIELDS = ['title', 'slug', 'excerpt', 'body', 'cover_path'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BlogPostStatus::class,
            'published_at' => 'datetime',
            'draft' => 'array',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Articles clients can see: published, and not scheduled for later. Clients always read the live
     * columns, never the pending draft.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', BlogPostStatus::Published)
            ->where('published_at', '<=', now());
    }

    /**
     * The columns every client may read. The body is left out on purpose, so a Free client's page
     * never even loads it.
     */
    public function scopeWithoutBody(Builder $query): Builder
    {
        return $query->select(['id', 'title', 'slug', 'excerpt', 'cover_path', 'status', 'published_at', 'author_id']);
    }

    public function isPublished(): bool
    {
        return $this->status === BlogPostStatus::Published
            && $this->published_at !== null
            && $this->published_at->lte(now());
    }

    /**
     * A published article whose edits have not been published yet.
     */
    public function hasUnpublishedChanges(): bool
    {
        return $this->status === BlogPostStatus::Published && $this->draft !== null;
    }

    /**
     * Draft, Published, or In Draft (published, with edits that are not published yet).
     */
    public function statusLabel(): string
    {
        return match (true) {
            $this->hasUnpublishedChanges() => 'In Draft',
            $this->status === BlogPostStatus::Published => 'Published',
            default => 'Draft',
        };
    }

    public function statusColor(): string
    {
        return match ($this->statusLabel()) {
            'In Draft' => 'orange',
            'Published' => 'success',
            default => 'gray',
        };
    }

    /**
     * The live content with any pending edits laid over it: what the editor form shows.
     *
     * @return array<string, mixed>
     */
    public function editableContent(): array
    {
        return [...$this->only(self::CONTENT_FIELDS), ...($this->draft ?? [])];
    }

    /**
     * Remove an uploaded cover that no version of the article uses any more.
     */
    public static function deleteCoverFile(?string $path): void
    {
        if (filled($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
