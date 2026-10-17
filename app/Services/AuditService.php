<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Append-only audit trail writer (FR-12, NFR-04).
 */
class AuditService
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function record(
        string $action,
        ?Model $entity = null,
        array $before = [],
        array $after = [],
        ?string $reason = null,
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Auth::user();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name,
            'action' => $action,
            'entity_type' => $entity ? class_basename($entity) : null,
            'entity_id' => $entity?->getKey() !== null ? (string) $entity->getKey() : null,
            'before_data' => $this->normalize($before),
            'after_data' => $this->normalize($after),
            'reason' => $reason,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalize(array $data): array
    {
        if ($data === []) {
            return [];
        }

        return collect($data)
            ->map(function ($value) {
                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d H:i:s');
                }

                if ($value instanceof \BackedEnum) {
                    return $value->value;
                }

                if (is_array($value)) {
                    return $this->normalize($value);
                }

                return $value;
            })
            ->all();
    }
}
