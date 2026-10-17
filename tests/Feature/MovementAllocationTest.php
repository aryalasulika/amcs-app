<?php

namespace Tests\Feature;

use Tests\AmcsTestCase;

class MovementAllocationTest extends AmcsTestCase
{
    public function test_operator_can_create_movement(): void
    {
        $this->operator();

        $response = $this->postJson('/api/v1/movements', $this->movementPayload());

        $response->assertCreated()
            ->assertJsonPath('data.flight_number', 'IW-114')
            ->assertJsonPath('data.state', 'ENTERING');

        $this->assertDatabaseHas('movements', ['flight_number' => 'IW-114']);
    }

    public function test_occupied_stand_is_rejected(): void
    {
        $this->operator();
        $this->createMovement(['flight_number' => 'IW-100']);

        $this->postJson('/api/v1/movements', $this->movementPayload(['flight_number' => 'IW-200']))
            ->assertStatus(422)
            ->assertJsonPath('errors.parking_stand_id.0', 'Parking Stand 2 sedang terisi.');
    }

    public function test_incompatible_aircraft_is_rejected_on_stand_one(): void
    {
        $this->operator();

        $this->postJson('/api/v1/movements', $this->movementPayload([
            'flight_number' => 'GA-400',
            'aircraft_type_name' => 'A320',
            'parking_stand_id' => $this->stand('STAND-1')->id,
        ]))->assertStatus(422);
    }

    public function test_stand_one_accepts_atr72(): void
    {
        $this->operator();

        $this->postJson('/api/v1/movements', $this->movementPayload([
            'flight_number' => 'IW-777',
            'aircraft_type_name' => 'ATR72',
            'parking_stand_id' => $this->stand('STAND-1')->id,
        ]))->assertCreated();
    }

    public function test_supervisor_can_override_with_reason(): void
    {
        $this->supervisor();

        $this->postJson('/api/v1/movements', $this->movementPayload([
            'flight_number' => 'GA-500',
            'aircraft_type_name' => 'A320',
            'parking_stand_id' => $this->stand('STAND-1')->id,
            'override' => true,
            'override_reason' => 'Emergency diversion, tidak ada stand lain.',
        ]))->assertCreated();

        $this->assertDatabaseHas('movements', ['flight_number' => 'GA-500']);
    }

    public function test_operator_cannot_override(): void
    {
        $this->operator();

        $this->postJson('/api/v1/movements', $this->movementPayload([
            'flight_number' => 'GA-600',
            'aircraft_type_name' => 'A320',
            'parking_stand_id' => $this->stand('STAND-1')->id,
            'override' => true,
            'override_reason' => 'coba-coba',
        ]))->assertStatus(403);
    }

    public function test_override_requires_reason(): void
    {
        $this->supervisor();

        $this->postJson('/api/v1/movements', $this->movementPayload([
            'override' => true,
        ]))->assertStatus(422)
            ->assertJsonValidationErrors('override_reason');
    }

    public function test_manual_aircraft_type_is_created_and_validated_by_category(): void
    {
        $this->operator();

        $this->postJson('/api/v1/movements', $this->movementPayload([
            'flight_number' => 'SI-300',
            'aircraft_type_name' => 'C208B',
            'aircraft_category' => 'LIGHT',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.aircraft_type.code', 'C208B');

        $this->assertDatabaseHas('aircraft_types', [
            'code' => 'C208B',
            'category' => 'LIGHT',
        ]);

        $this->assertDatabaseHas('audit_logs', ['action' => 'aircraft_type.created']);
    }

    public function test_manual_aircraft_type_exceeding_stand_capacity_is_rejected(): void
    {
        $this->operator();

        $this->postJson('/api/v1/movements', $this->movementPayload([
            'flight_number' => 'XX-301',
            'aircraft_type_name' => 'B777X',
            'aircraft_category' => 'WIDE_BODY',
            'parking_stand_id' => $this->stand('STAND-2')->id,
        ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('parking_stand_id');

        // The failed allocation must not leave behind an orphan master record.
        $this->assertDatabaseMissing('aircraft_types', ['code' => 'B777X']);
    }

    public function test_options_expose_airline_default_aircraft_for_autofill(): void
    {
        $this->operator();

        $response = $this->getJson('/api/v1/options')->assertOk();

        $wingsAir = collect($response->json('airlines'))->firstWhere('code', 'WON');

        $this->assertNotNull($wingsAir);
        $this->assertSame('IW', $wingsAir['flight_prefix']);
        $this->assertSame('ATR72', $wingsAir['default_aircraft_type']['code']);
    }

    public function test_stand_recommendation_returns_ranked_stands(): void
    {
        $this->operator();

        $response = $this->postJson('/api/v1/stands/recommend', [
            'aircraft_type_name' => 'A320',
            'eta' => '10:00',
            'etd' => '11:00',
        ]);

        $response->assertOk();
        $this->assertNotEmpty($response->json('data'));
        $this->assertArrayHasKey('recommended', $response->json('data.0'));
    }

    public function test_overlap_is_reported_as_warning(): void
    {
        $this->operator();
        $this->createMovement([
            'flight_number' => 'IW-111',
            'aircraft_type_name' => 'A320',
            'parking_stand_id' => $this->stand('STAND-2')->id,
            'eta' => '10:00',
            'etd' => '12:00',
        ]);

        // Supervisor override forces a second, overlapping allocation so the
        // system must surface a schedule-overlap warning instead of silently
        // accepting it.
        $this->supervisor();

        $response = $this->postJson('/api/v1/movements', $this->movementPayload([
            'flight_number' => 'IW-222',
            'aircraft_type_name' => 'A320',
            'parking_stand_id' => $this->stand('STAND-2')->id,
            'eta' => '11:00',
            'etd' => '13:00',
            'override' => true,
            'override_reason' => 'Uji tumpang tindih jadwal.',
        ]));

        $response->assertCreated();
        $this->assertNotEmpty($response->json('warnings'));
    }
}
