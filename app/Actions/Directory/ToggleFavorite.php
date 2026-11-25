<?php

namespace App\Actions\Directory;

use App\Models\FreelancerProfile;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class ToggleFavorite
{
    /**
     * Save a freelancer for the client, or remove them if already saved. Only clients with Paid
     * access may do either; what a lapsed client saved earlier stays untouched.
     *
     * @return bool Whether the freelancer is saved afterwards.
     *
     * @throws AuthorizationException
     * @throws DomainException
     */
    public function __invoke(User $user, FreelancerProfile $profile): bool
    {
        Gate::forUser($user)->authorize('favorite-freelancers');

        if ($user->favoriteFreelancers()->whereKey($profile->getKey())->exists()) {
            $user->favoriteFreelancers()->detach($profile->getKey());

            return false;
        }

        if (! FreelancerProfile::query()->visible()->whereKey($profile->getKey())->exists()) {
            throw new DomainException('This freelancer is not available.');
        }

        $user->favoriteFreelancers()->attach($profile->getKey());

        return true;
    }
}
