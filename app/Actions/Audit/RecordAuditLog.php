<?php

namespace App\Actions\Audit;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecordAuditLog
{
    /**
     * Record a sensitive action. This is the single entry point for writing audit logs.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function __invoke(
        string $action,
        ?Model $subject = null,
        ?string $reason = null,
        array $metadata = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= auth()->user();

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'metadata' => $metadata,
            'ip_address' => request()->ip(),
        ]);
    }
}
