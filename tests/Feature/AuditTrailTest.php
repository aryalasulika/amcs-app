<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use RuntimeException;
use Tests\AmcsTestCase;

class AuditTrailTest extends AmcsTestCase
{
    public function test_creating_movement_writes_audit_log(): void
    {
        $operator = $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-600']);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'movement.created',
            'entity_type' => 'Movement',
            'entity_id' => (string) $movement->id,
            'user_id' => $operator->id,
        ]);
    }

    public function test_audit_log_cannot_be_updated_or_deleted(): void
    {
        $this->operator();
        $this->createMovement(['flight_number' => 'IW-601']);

        $log = AuditLog::firstOrFail();

        $this->expectException(RuntimeException::class);
        $log->delete();
    }

    public function test_operator_cannot_view_audit_trail(): void
    {
        $this->operator();

        $this->getJson('/api/v1/audit-logs')->assertStatus(403);
    }

    public function test_supervisor_can_view_audit_trail(): void
    {
        $this->supervisor();
        $this->createMovement(['flight_number' => 'IW-602']);

        $this->getJson('/api/v1/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'movement.created');
    }

    public function test_every_change_records_actor_and_timestamp(): void
    {
        $operator = $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-603']);
        $this->postJson("/api/v1/movements/{$movement->id}/block-on")->assertOk();

        $transition = AuditLog::where('action', 'movement.transitioned')->firstOrFail();

        $this->assertSame($operator->id, $transition->user_id);
        $this->assertSame($operator->name, $transition->user_name);
        $this->assertNotNull($transition->created_at);
    }
}
