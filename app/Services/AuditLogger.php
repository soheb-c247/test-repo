<?php

namespace App\Services;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Records who did what to which record. Metadata must only contain
 * identifiers and non-clinical facts — never patient names or results.
 */
class AuditLogger
{
    public function record(string $action, ?Model $subject = null, array $metadata = []): void
    {
        AuditEvent::create([
            'user_id' => Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata ?: null,
            'ip_address' => Request::ip(),
        ]);
    }
}
