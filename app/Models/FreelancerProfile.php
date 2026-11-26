<?php

namespace App\Models;

use App\Actions\Profiles\GenerateProfileSlug;
use App\Actions\Profiles\ProfileContent;
use App\Enums\ProfileStatus;
use App\Enums\WeeklyHours;
use Database\Factories\FreelancerProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The live profile: only content an administrator has approved. Pending edits live in
 * {@see FreelancerProfileRevision}; directory, search and public pages read this model.
 *
 * Timestamps: `created_at` is when the profile was created (account activation),
 * `published_at` is when it was first approved (never reset, and the sole visibility flag),
 * `content_updated_at` is when an approval last changed the live content, and `updated_at`
 * is Eloquent's "row last saved" (it also moves on score edits).
 */
#[Fillable([
    'photo_path',
    'professional_title',
    'about',
    'location',
    'timezone',
    'website',
    'social_links',
    'professional_function_id',
    'hourly_rate_cents',
    'offers_monthly_retainers',
    'monthly_retainers_description',
    'monthly_retainers_from_cents',
    'weekly_hours',
    'open_to_full_time',
    'best_fit',
])]
class FreelancerProfile extends Model
{
    /** @use HasFactory<FreelancerProfileFactory> */
    use HasFactory;

    /**
     * Default attribute values for newly instantiated models.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'timezone' => 'UTC',
        'score' => 0,
        'is_newbie' => false,
    ];

    /** How long a new profile counts as a newbie and gets a head start in the directory. */
    public const NEWBIE_MONTHS = 1;

    protected static function booted(): void
    {
        static::creating(function (FreelancerProfile $profile): void {
            if (blank($profile->slug)) {
                $profile->slug = app(GenerateProfileSlug::class)((string) $profile->user?->name);
            }
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
            'social_links' => 'array',
            'best_fit' => 'array',
            'hourly_rate_cents' => 'integer',
            'monthly_retainers_from_cents' => 'integer',
            'offers_monthly_retainers' => 'boolean',
            'open_to_full_time' => 'boolean',
            'weekly_hours' => WeeklyHours::class,
            'published_at' => 'datetime',
            'content_updated_at' => 'datetime',
            'score' => 'integer',
            'is_newbie' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(FreelancerApplication::class, 'freelancer_application_id');
    }

    public function professionalFunction(): BelongsTo
    {
        return $this->belongsTo(ProfessionalFunction::class);
    }

    /**
     * Main role: the first role the freelancer picked. It is a technical field filled from the
     * "roles" selection, not edited directly.
     */
    public function mainRole(): BelongsTo
    {
        return $this->belongsTo(ProfessionalRole::class, 'main_role_id');
    }

    public function secondaryRole(): BelongsTo
    {
        return $this->belongsTo(ProfessionalRole::class, 'secondary_role_id');
    }

    public function thirdRole(): BelongsTo
    {
        return $this->belongsTo(ProfessionalRole::class, 'third_role_id');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'freelancer_profile_skill');
    }

    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(Tool::class, 'freelancer_profile_tool');
    }

    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class, 'freelancer_profile_industry');
    }

    public function experiences(): HasMany
    {
        return $this->hasMany(FreelancerExperience::class)->orderBy('sort_order');
    }

    public function highlights(): HasMany
    {
        return $this->hasMany(Highlight::class)->orderBy('sort_order')->orderBy('id');
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class)->orderBy('sort_order')->orderBy('id');
    }

    public function consultationOffers(): HasMany
    {
        return $this->hasMany(ConsultationOffer::class)->orderBy('type');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(FreelancerProfileRevision::class)->latest('id');
    }

    /**
     * The revision currently being edited or reviewed, if any (at most one).
     */
    public function openRevision(): HasOne
    {
        return $this->hasOne(FreelancerProfileRevision::class)->whereNotNull('open_for_profile_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(ProfileReview::class)->latest();
    }

    /**
     * Profiles that clients may see: approved at least once, owner still in good standing.
     *
     * @param  Builder<FreelancerProfile>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNotNull('published_at')
            ->whereHas('user', fn (Builder $users) => $users
                ->where('status', 'active')
                ->whereNull('deactivated_at'));
    }

    /**
     * What a directory card needs, loaded up front so a list of cards costs a fixed number of queries.
     *
     * @param  Builder<FreelancerProfile>  $query
     */
    public function scopeForCard(Builder $query): void
    {
        $query->with(['user', 'professionalFunction', 'mainRole', 'skills' => fn ($skills) => $skills->orderBy('name')]);
    }

    /**
     * The approved content currently shown to clients.
     */
    public function liveContent(): ProfileContent
    {
        return ProfileContent::fromProfile($this);
    }

    /**
     * The content the freelancer sees in their editor: the open revision if there is one,
     * otherwise what is live.
     */
    public function workingContent(): ProfileContent
    {
        return $this->openRevision?->content() ?? $this->liveContent();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }

    /**
     * The state to show the freelancer: that of the open revision, otherwise Published once live.
     */
    public function displayStatus(): ProfileStatus
    {
        return $this->openRevision?->status
            ?? ($this->isPublished() ? ProfileStatus::Published : ProfileStatus::Draft);
    }
}
