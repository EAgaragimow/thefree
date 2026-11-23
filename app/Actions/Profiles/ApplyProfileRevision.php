<?php

namespace App\Actions\Profiles;

use App\Enums\ProfileStatus;
use App\Models\Category;
use App\Models\FreelancerProfile;
use App\Models\FreelancerProfileRevision;
use App\Models\Skill;
use Closure;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ApplyProfileRevision
{
    public function __construct(private ProfileMedia $media) {}

    /**
     * Write an approved revision's content to the live profile and close the revision.
     *
     * Categories or skills that were deleted while the revision was open are dropped silently.
     * Highlights and services are updated in place by `id`; rows missing from the revision are
     * removed (soft-deleted for services) and new items are created. Consultations are updated by type.
     */
    public function __invoke(FreelancerProfileRevision $revision): FreelancerProfile
    {
        return DB::transaction(function () use ($revision): FreelancerProfile {
            $profile = FreelancerProfile::query()->lockForUpdate()->findOrFail($revision->freelancer_profile_id);

            $before = $profile->liveContent();
            $after = $revision->content();

            $profile->fill($after->scalarAttributes());
            $profile->published_at ??= now();
            $profile->content_updated_at = now();
            $profile->save();

            $profile->categories()->sync(Category::query()->whereIn('id', $after->categoryIds())->pluck('id')->all());
            $profile->skills()->sync(Skill::query()->whereIn('id', $after->skillIds())->pluck('id')->all());

            $profile->experiences()->delete();

            foreach ($after->experiences() as $position => $experience) {
                $profile->experiences()->create([...$experience, 'sort_order' => $position]);
            }

            $this->syncItems($profile->highlights(), $after->highlights(), fn (array $item): array => [
                'title' => $item['title'],
                'cover_path' => $item['cover_path'],
                'description' => $item['description'],
                'gallery' => $item['gallery'],
                'video_urls' => $item['video_urls'],
                'links' => $item['links'],
            ]);

            $this->syncItems($profile->services(), $after->services(), fn (array $item): array => [
                'title' => $item['title'],
                'description' => $item['description'],
                'pricing_mode' => $item['pricing_mode'],
                'price_cents' => $item['price_cents'],
            ]);

            // The two consultation types are never created or removed by the freelancer, only
            // configured, so their rows are updated by type and never deleted.
            foreach ($after->consultationOffers() as $offer) {
                $profile->consultationOffers()->updateOrCreate(['type' => $offer['type']], [
                    'is_enabled' => $offer['enabled'],
                    'duration_minutes' => $offer['duration_minutes'],
                    'price_cents' => $offer['price_cents'],
                    'description' => $offer['description'],
                    'availability' => $offer['availability'],
                ]);
            }

            $revision->forceFill([
                'status' => ProfileStatus::Published,
                'applied_at' => now(),
                'last_change_request' => null,
            ])->save();

            $this->media->deleteDropped($before, $after, $after);

            return $profile;
        });
    }

    /**
     * Make the live rows of a relation match an ordered list of items.
     *
     * @param  HasMany<*, FreelancerProfile>  $relation
     * @param  list<array<string, mixed>>  $items
     * @param  Closure(array<string, mixed>): array<string, mixed>  $attributes
     */
    private function syncItems(HasMany $relation, array $items, Closure $attributes): void
    {
        $existing = $relation->get()->keyBy('id');

        $keep = collect($items)
            ->pluck('id')
            ->filter(fn (?int $id): bool => $id !== null && $existing->has($id))
            ->all();

        $existing->except($keep)->each->delete();

        foreach ($items as $position => $item) {
            $values = [...$attributes($item), 'sort_order' => $position];

            // An id that is not one of this profile's live rows is treated as a new item.
            if ($item['id'] !== null && $existing->has($item['id'])) {
                $existing->get($item['id'])->update($values);
            } else {
                $relation->create($values);
            }
        }
    }
}
