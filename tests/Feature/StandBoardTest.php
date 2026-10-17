<?php

namespace Tests\Feature;

use App\Events\StandBoardUpdated;
use Illuminate\Support\Facades\Event;
use Tests\AmcsTestCase;

class StandBoardTest extends AmcsTestCase
{
    public function test_board_shows_vacant_stands_initially(): void
    {
        $this->atcViewer();

        $response = $this->getJson('/api/v1/stands')->assertOk();

        $this->assertSame(5, $response->json('counts.total'));
        $this->assertSame(5, $response->json('counts.vacant'));
        $this->assertSame(0, $response->json('counts.active'));
    }

    public function test_board_reflects_active_movement(): void
    {
        $this->operator();
        $this->createMovement(['flight_number' => 'IW-500']);

        $this->atcViewer();
        $response = $this->getJson('/api/v1/stands')->assertOk();

        $stand = collect($response->json('stands'))->firstWhere('code', 'STAND-2');

        $this->assertSame('ENTERING', $stand['state']);
        $this->assertSame('APPROACHING', $stand['atc_label']);
        $this->assertSame('IW-500', $stand['movement']['flight_number']);
        $this->assertSame(1, $response->json('counts.active'));
    }

    public function test_board_reports_ron_count(): void
    {
        $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-501']);
        $this->postJson("/api/v1/movements/{$movement->id}/block-on")->assertOk();
        $this->patchJson("/api/v1/movements/{$movement->id}/state", ['state' => 'RON'])->assertOk();

        $response = $this->getJson('/api/v1/stands')->assertOk();

        $this->assertSame(1, $response->json('counts.ron'));
    }

    public function test_operational_changes_broadcast_a_board_update(): void
    {
        Event::fake([StandBoardUpdated::class]);

        $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-510']);

        Event::assertDispatched(StandBoardUpdated::class, function (StandBoardUpdated $event) use ($movement) {
            return $event->movementId === $movement->id && $event->action === 'created';
        });

        $this->postJson("/api/v1/movements/{$movement->id}/block-off")->assertOk();

        Event::assertDispatched(StandBoardUpdated::class, fn (StandBoardUpdated $event) => $event->action === 'transitioned');
    }
}
