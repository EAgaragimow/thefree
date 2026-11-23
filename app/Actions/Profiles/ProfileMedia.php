<?php

namespace App\Actions\Profiles;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps uploaded profile files in step with the live profile and its revisions: a file that
 * the live profile still shows is never deleted, however the revision changes.
 */
class ProfileMedia
{
    /**
     * Delete the files that `$before` referenced, `$after` no longer references and the
     * live profile does not show, once the surrounding transaction has committed.
     */
    public function deleteDropped(ProfileContent $before, ProfileContent $after, ProfileContent $live): void
    {
        $this->deleteFiles(array_diff($before->mediaPaths(), $after->mediaPaths(), $live->mediaPaths()));
    }

    /**
     * Delete every file referenced only by the given (discarded) content.
     */
    public function deleteUnpublished(ProfileContent $discarded, ProfileContent $live): void
    {
        $this->deleteFiles(array_diff($discarded->mediaPaths(), $live->mediaPaths()));
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteFiles(array $paths): void
    {
        $paths = array_values($paths);

        if ($paths === []) {
            return;
        }

        DB::afterCommit(fn () => Storage::disk('public')->delete($paths));
    }
}
