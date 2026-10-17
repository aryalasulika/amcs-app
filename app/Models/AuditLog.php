<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit trail (FR-12). Records are append-only: the model blocks
 * updates and deletes at the application layer in addition to policy checks.
 */
class AuditLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'user_name',
        'action',
        'entity_type',
        'entity_id',
        'before_data',
        'after_data',
        'reason',
        'ip_address',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        static::updating(static function () {
            throw new \RuntimeException('Audit log bersifat permanen dan tidak dapat diubah.');
        });

        static::deleting(static function () {
            throw new \RuntimeException('Audit log bersifat permanen dan tidak dapat dihapus.');
        });
    }
}
