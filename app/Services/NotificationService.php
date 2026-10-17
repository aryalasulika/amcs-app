<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\OperationalAlert;
use Illuminate\Support\Facades\Notification;

/**
 * Operational notifications (FR-16). Delivered through the database channel so
 * they surface in the AMC dashboard bell.
 */
class NotificationService
{
    /**
     * @param  string|array<int, string>  $roles
     * @param  array<string, mixed>  $context
     */
    public function toRoles(
        string|array $roles,
        string $title,
        string $message,
        string $level = 'info',
        array $context = [],
        ?string $url = null,
    ): void {
        $roles = (array) $roles;

        $recipients = User::query()
            ->where('active', true)
            ->role($roles)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new OperationalAlert($title, $message, $level, $context, $url));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function toSupervisors(
        string $title,
        string $message,
        string $level = 'warning',
        array $context = [],
        ?string $url = null,
    ): void {
        $this->toRoles(['admin', 'supervisor'], $title, $message, $level, $context, $url);
    }
}
