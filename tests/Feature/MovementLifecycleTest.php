<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\AmcsTestCase;

class MovementLifecycleTest extends AmcsTestCase
{
    public function test_block_on_records_ata_and_changes_state(): void
    {
        $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-300']);

        $response = $this->postJson("/api/v1/movements/{$movement->id}/block-on");

        $response->assertOk()
            ->assertJsonPath('data.state', 'ON');

        $this->assertNotNull($response->json('data.ata'));
    }

    public function test_block_off_records_atd_and_frees_stand(): void
    {
        $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-301']);
        $this->postJson("/api/v1/movements/{$movement->id}/block-on")->assertOk();

        $response = $this->postJson("/api/v1/movements/{$movement->id}/block-off");
        $response->assertOk()->assertJsonPath('data.state', 'OFF');

        $this->assertNotNull($response->json('data.atd'));

        $board = $this->getJson('/api/v1/stands')->assertOk()->json('stands');
        $stand = collect($board)->firstWhere('code', 'STAND-2');

        $this->assertSame('VACANT', $stand['state']);
        $this->assertNull($stand['movement']);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-302']);

        // ENTERING -> RON is not a valid transition.
        $this->patchJson("/api/v1/movements/{$movement->id}/state", ['state' => 'RON'])
            ->assertStatus(422);
    }

    public function test_delay_records_minutes_and_notifies_supervisor(): void
    {
        $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-303']);
        $this->postJson("/api/v1/movements/{$movement->id}/block-on")->assertOk();

        $response = $this->postJson("/api/v1/movements/{$movement->id}/delay", [
            'delay_minutes' => 45,
            'delay_reason' => 'TECHNICAL',
            'delay_notes' => 'Menunggu spare part',
        ]);

        $response->assertOk()->assertJsonPath('data.state', 'DELAY');
        $this->assertSame(45, $response->json('data.delay_minutes'));

        $supervisor = $this->user('supervisor@amcs.local');
        $this->assertGreaterThan(0, DB::table('notifications')->where('notifiable_id', $supervisor->id)->count());
    }

    public function test_entering_can_be_cancelled_to_off(): void
    {
        $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-304']);

        $this->patchJson("/api/v1/movements/{$movement->id}/state", ['state' => 'OFF'])
            ->assertOk()
            ->assertJsonPath('data.state', 'OFF');
    }

    public function test_movement_history_logs_are_recorded(): void
    {
        $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-305']);
        $this->postJson("/api/v1/movements/{$movement->id}/block-on")->assertOk();

        $this->getJson("/api/v1/movements/{$movement->id}/logs")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas('movement_logs', [
            'movement_id' => $movement->id,
            'action' => 'BLOCK ON',
        ]);
    }
}
