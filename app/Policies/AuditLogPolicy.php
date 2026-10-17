<?php

namespace App\Policies;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('audit.view');
    }

    public function view(User $user, AuditLog $auditLog): bool
    {
        return $user->can('audit.view');
    }

    /**
     * Audit trail is immutable – nobody can delete it (FR-12, MVP #9).
     */
    public function delete(User $user, AuditLog $auditLog): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $auditLog): bool
    {
        return false;
    }
}
