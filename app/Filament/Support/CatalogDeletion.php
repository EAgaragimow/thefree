<?php

namespace App\Filament\Support;

use App\Models\FreelancerProfile;
use App\Models\Industry;
use App\Models\ProfessionalFunction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Delete actions for the catalog (functions, roles, skills, tools, industries) that warn
 * the administrator when a freelancer still uses the entry, and say which freelancers.
 *
 * The delete is still allowed once confirmed: the entry then disappears from those profiles.
 * Entries are expected to expose a `freelancerProfiles()` query or relation.
 */
class CatalogDeletion
{
    /** How many freelancers are named in the warning before it says "and N more". */
    public const LISTED = 10;

    public static function deleteAction(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription(fn (Model $record): ?Htmlable => self::warning([$record]));
    }

    public static function deleteBulkAction(): DeleteBulkAction
    {
        return DeleteBulkAction::make()
            ->modalDescription(fn (iterable $records): ?Htmlable => self::warning($records));
    }

    /**
     * The warning for the given entries, or null when nobody uses them.
     *
     * @param  iterable<Model>  $records
     */
    public static function warning(iterable $records): ?Htmlable
    {
        $blocks = [];

        foreach ($records as $record) {
            $usage = $record->freelancerProfiles();
            $total = $usage->count();
            $notes = [];

            if ($record instanceof ProfessionalFunction && ($roles = $record->roles()->count()) > 0) {
                $notes[] = '<p>'.e("It has {$roles} role(s). They stay in the catalog but no longer belong to any function.").'</p>';
            }

            if ($record instanceof Industry && ($brands = $record->brandProfiles()->count()) > 0) {
                $notes[] = '<p>'.e("It is the industry of {$brands} brand(s). They will be left without an industry.").'</p>';
            }

            if ($total === 0 && $notes === []) {
                continue;
            }

            $block = '<p><strong>'.e($record->name).'</strong>';

            if ($total > 0) {
                $people = $usage->with('user:id,name')->orderBy('freelancer_profiles.id')->limit(self::LISTED)->get()
                    ->map(fn (FreelancerProfile $profile): string => '<li>#'.e((string) $profile->user_id).' '.e((string) $profile->user?->name).'</li>')
                    ->implode('');
                $more = $total > self::LISTED ? '<li>…and '.($total - self::LISTED).' more</li>' : '';

                $block .= ' is used by '.$total.' freelancer(s) (id, name). It will be removed from their profiles:</p><ul class="list-disc ps-5">'.$people.$more.'</ul>';
            } else {
                $block .= '</p>';
            }

            $blocks[] = $block.implode('', $notes);
        }

        return $blocks === [] ? null : new HtmlString(implode('', $blocks));
    }
}
