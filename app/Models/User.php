<?php

namespace App\Models;

use App\Enums\BillingState;
use App\Enums\SubscriptionType;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'timezone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

    /**
     * Default attribute values for newly instantiated models.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'client',
        'status' => 'active',
        'timezone' => 'UTC',
        'subscription_type' => 'free-plan',
        'billing_state' => 'ok',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'subscription_type' => SubscriptionType::class,
            'billing_state' => BillingState::class,
            'notification_preferences' => 'array',
            'deactivated_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
        ];
    }

    public function brandProfile(): HasOne
    {
        return $this->hasOne(BrandProfile::class);
    }

    public function freelancerProfile(): HasOne
    {
        return $this->hasOne(FreelancerProfile::class);
    }

    /**
     * Freelancers this client has saved, most recently saved first.
     */
    public function favoriteFreelancers(): BelongsToMany
    {
        return $this->belongsToMany(FreelancerProfile::class, 'favorites')
            ->withTimestamps()
            ->orderByPivot('created_at', 'desc');
    }

    public function avatarUrl(): ?string
    {
        return $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null;
    }

    public function isClient(): bool
    {
        return $this->role === UserRole::Client;
    }

    public function isFreelancer(): bool
    {
        return $this->role === UserRole::Freelancer;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /**
     * Whether the user is a Client with full Paid access.
     *
     * A Paid subscription whose renewal failed (payment-restricted) has the same
     * functional restrictions as the Free plan.
     */
    public function hasPaidAccess(): bool
    {
        return $this->isClient()
            && $this->subscription_type === SubscriptionType::PaidPlan
            && $this->billing_state === BillingState::Ok;
    }

    public function isPaymentRestricted(): bool
    {
        return $this->billing_state === BillingState::PaymentRestricted;
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function isSuspended(): bool
    {
        return $this->status === UserStatus::Suspended;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin()
            && $this->status === UserStatus::Active
            && ! $this->isDeactivated()
            && $this->hasVerifiedEmail();
    }
}
