<?php

namespace App\Models;

use App\Enums\ReviewDecision;
use Database\Factories\ProfileReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['freelancer_profile_id', 'freelancer_profile_revision_id', 'reviewer_id', 'decision', 'comment'])]
class ProfileReview extends Model
{
    /** @use HasFactory<ProfileReviewFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_profile_id');
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfileRevision::class, 'freelancer_profile_revision_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
