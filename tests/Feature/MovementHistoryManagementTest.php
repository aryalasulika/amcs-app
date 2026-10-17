<?php

namespace Tests\Feature;

use Tests\AmcsTestCase;

class MovementHistoryManagementTest extends AmcsTestCase
{
    public function test_admin_can_correct_a_movement_from_history(): void
    {
        $this->admin();
        $movement = $this->createMovement(['flight_number' => 'IW-610']);

        $this->putJson("/api/v1/movements/{$movement->id}/history", [
            'notes' => 'Koreksi histori oleh administrator.',
        ])
            ->assertOk()
            ->assertJsonPath('data.notes', 'Koreksi histori oleh administrator.');

        $this->assertDatabaseHas('movements', [
            'id' => $movement->id,
            'notes' => 'Koreksi histori oleh administrator.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'movement.updated',
            'entity_id' => (string) $movement->id,
        ]);
    }

    public function test_admin_can_archive_a_movement_from_history(): void
    {
        $this->admin();
        $movement = $this->createMovement(['flight_number' => 'IW-611']);

        $this->deleteJson("/api/v1/movements/{$movement->id}/history", [
            'reason' => 'Koreksi data uji dari tabel histori.',
        ])->assertOk();

        $this->assertSoftDeleted('movements', ['id' => $movement->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'movement.archived',
            'entity_id' => (string) $movement->id,
        ]);
    }

    public function test_supervisor_cannot_manage_movement_history(): void
    {
        $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-612']);

        $this->supervisor();

        $this->putJson("/api/v1/movements/{$movement->id}/history", [
            'notes' => 'Percobaan supervisor.',
        ])->assertForbidden();

        $this->deleteJson("/api/v1/movements/{$movement->id}/history", [
            'reason' => 'Percobaan supervisor.',
        ])->assertForbidden();

        $this->assertDatabaseHas('movements', [
            'id' => $movement->id,
            'deleted_at' => null,
        ]);
    }

    public function test_operator_cannot_manage_movement_history(): void
    {
        $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-613']);

        $this->putJson("/api/v1/movements/{$movement->id}/history", [
            'notes' => 'Percobaan operator.',
        ])->assertForbidden();

        $this->deleteJson("/api/v1/movements/{$movement->id}/history", [
            'reason' => 'Percobaan operator.',
        ])->assertForbidden();
    }
}
