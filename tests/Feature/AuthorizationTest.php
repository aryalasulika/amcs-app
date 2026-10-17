<?php

namespace Tests\Feature;

use App\Models\Movement;
use Tests\AmcsTestCase;

class AuthorizationTest extends AmcsTestCase
{
    public function test_operator_cannot_mass_reset(): void
    {
        $this->operator();
        $this->createMovement(['flight_number' => 'IW-700']);

        $this->postJson('/api/v1/stands/reset', [
            'reason' => 'Percobaan reset',
            'confirm' => true,
        ])->assertStatus(403);

        $this->assertDatabaseHas('movements', ['flight_number' => 'IW-700', 'state' => 'ENTERING']);
    }

    public function test_supervisor_can_mass_reset_with_reason(): void
    {
        $this->operator();
        $this->createMovement(['flight_number' => 'IW-701']);
        $this->createMovement(['flight_number' => 'IW-702', 'parking_stand_id' => $this->stand('STAND-3')->id]);

        $this->supervisor();

        $this->postJson('/api/v1/stands/reset', [
            'reason' => 'Pergantian shift operasional',
            'confirm' => true,
        ])->assertOk()->assertJsonPath('affected', 2);

        $this->assertDatabaseHas('audit_logs', ['action' => 'stands.reset_all']);
    }

    public function test_reset_requires_reason(): void
    {
        $this->supervisor();

        $this->postJson('/api/v1/stands/reset', ['confirm' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_reset_requires_confirmation(): void
    {
        $this->supervisor();

        $this->postJson('/api/v1/stands/reset', ['reason' => 'Tanpa konfirmasi'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('confirm');
    }

    public function test_atc_viewer_cannot_create_movement(): void
    {
        $this->atcViewer();

        $this->postJson('/api/v1/movements', $this->movementPayload())->assertStatus(403);
    }

    public function test_only_admin_can_manage_master_data(): void
    {
        $this->operator();
        $this->postJson('/api/v1/airlines', ['code' => 'ZZZ', 'name' => 'Zed Air'])->assertStatus(403);

        $this->admin();
        $this->postJson('/api/v1/airlines', ['code' => 'ZZZ', 'name' => 'Zed Air'])->assertCreated();
    }

    public function test_operator_cannot_manage_users(): void
    {
        $this->operator();

        $this->getJson('/api/v1/users')->assertStatus(403);
    }

    public function test_second_allocation_to_same_stand_is_blocked(): void
    {
        $this->operator();

        $this->createMovement(['flight_number' => 'IW-710']);
        $this->postJson('/api/v1/movements', $this->movementPayload(['flight_number' => 'IW-711']))
            ->assertStatus(422);

        $this->assertSame(1, Movement::where('state', 'ENTERING')->count());
    }
}
