<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushed to the AMC dashboard and ATC monitor whenever the live stand board
 * changes (FR-11). The payload is intentionally small: clients refresh the
 * board through the versioned REST API, which keeps authorization and data
 * shaping in one place.
 */
class StandBoardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public ?int $standId = null,
        public ?int $movementId = null,
        public string $action = 'updated',
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('amcs.stands')];
    }

    public function broadcastAs(): string
    {
        return 'stand-board.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'stand_id' => $this->standId,
            'movement_id' => $this->movementId,
            'action' => $this->action,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
