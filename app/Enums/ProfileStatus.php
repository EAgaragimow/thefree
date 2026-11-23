<?php

namespace App\Enums;

/**
 * Review state of a profile revision. `Published` means the revision was approved and applied
 * to the live profile.
 */
enum ProfileStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending-review';
    case ChangesRequested = 'changes-requested';
    case Published = 'published';

    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::ChangesRequested], true);
    }

    public function canBeSubmitted(): bool
    {
        return $this->isEditable();
    }

    /**
     * An open revision is still being worked on or reviewed; at most one exists per profile.
     */
    public function isOpen(): bool
    {
        return $this !== self::Published;
    }
}
