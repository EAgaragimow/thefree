<?php

namespace App\Models;

use Database\Factories\HighlightFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A portfolio card on the live profile. It has no page of its own: clients open it in a modal.
 */
#[Fillable(['title', 'cover_path', 'description', 'gallery', 'video_urls', 'links', 'sort_order'])]
class Highlight extends Model
{
    /** @use HasFactory<HighlightFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'video_urls' => 'array',
            'links' => 'array',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_profile_id');
    }
}
