<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\FreelancerApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'email',
    'full_name',
    'phone',
    'location',
    'specialty',
    'years_experience',
    'experience_summary',
    'about',
    'skills',
    'portfolio_links',
    'social_links',
])]
#[Hidden(['invitation_token_hash'])]
class FreelancerApplication extends Model
{
    /** @use HasFactory<FreelancerApplicationFactory> */
    use HasFactory, Notifiable;

    /**
     * Default attribute values for newly instantiated models.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'skills' => 'array',
            'portfolio_links' => 'array',
            'social_links' => 'array',
            'reviewed_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(FreelancerProfile::class);
    }

    public function invitationIsUsable(): bool
    {
        return $this->status === ApplicationStatus::Approved
            && $this->activated_at === null
            && $this->invitation_token_hash !== null
            && $this->invitation_expires_at !== null
            && $this->invitation_expires_at->isFuture();
    }
}
