<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OperationalAlert extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $title,
        public string $message,
        public string $level = 'info',
        public array $context = [],
        public ?string $url = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'level' => $this->level,
            'context' => $this->context,
            'url' => $this->url,
        ];
    }
}
