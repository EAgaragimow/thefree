<?php

namespace App\Models;

use App\Actions\Profiles\ProfileContent;
use App\Enums\ProfileStatus;
use Database\Factories\FreelancerProfileRevisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A freelancer's proposed new state of their profile. The payload is the complete editable
 * content (see ProfileContent); approving the revision writes it to the live profile.
 * Approved revisions are kept as history.
 */
#[Fillable(['freelancer_profile_id', 'status', 'payload', 'submitted_at', 'applied_at', 'last_change_request'])]
class FreelancerProfileRevision extends Model
{
    /** @use HasFactory<FreelancerProfileRevisionFactory> */
    use HasFactory;

    /**
     * Default attribute values for newly instantiated models.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected static function booted(): void
    {
        // The unique `open_for_profile_id` column is what guarantees one open revision per
        // profile, so it is derived from the status here rather than set by each caller.
        static::saving(function (self $revision): void {
            $revision->open_for_profile_id = $revision->status->isOpen()
                ? $revision->freelancer_profile_id
                : null;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProfileStatus::class,
            'payload' => 'array',
            'submitted_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_profile_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProfileReview::class)->latest();
    }

    public function content(): ProfileContent
    {
        return ProfileContent::fromArray($this->payload ?? []);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }
}
