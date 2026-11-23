<?php

namespace App\Models;

use App\Enums\ConsultationType;
use Database\Factories\ConsultationOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of a freelancer's two consultation types (free or paid): its length, price (USD, in cents;
 * 0 for free), description and the weekly windows it can be booked in.
 */
#[Fillable(['type', 'is_enabled', 'duration_minutes', 'price_cents', 'description', 'availability'])]
class ConsultationOffer extends Model
{
    /** @use HasFactory<ConsultationOfferFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConsultationType::class,
            'is_enabled' => 'boolean',
            'duration_minutes' => 'integer',
            'price_cents' => 'integer',
            'availability' => 'array',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_profile_id');
    }
}
