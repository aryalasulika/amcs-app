<?php

namespace Tests;

use App\Models\Movement;
use App\Models\ParkingStand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

abstract class AmcsTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    protected function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    protected function actingAsRole(string $email): User
    {
        $user = $this->user($email);
        Sanctum::actingAs($user);

        return $user;
    }

    protected function admin(): User
    {
        return $this->actingAsRole('admin@amcs.local');
    }

    protected function supervisor(): User
    {
        return $this->actingAsRole('supervisor@amcs.local');
    }

    protected function operator(): User
    {
        return $this->actingAsRole('operator@amcs.local');
    }

    protected function atcViewer(): User
    {
        return $this->actingAsRole('atc@amcs.local');
    }

    protected function stand(string $code): ParkingStand
    {
        return ParkingStand::where('code', $code)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function movementPayload(array $overrides = []): array
    {
        return array_merge([
            'flight_number' => 'IW-114',
            'airline_name' => 'Wings Air',
            'registration' => 'PK-WGO',
            'aircraft_type_name' => 'ATR72',
            'flight_type' => 'SCHEDULED',
            'origin' => 'WAAA',
            'destination' => 'WAMW',
            'parking_stand_id' => $this->stand('STAND-2')->id,
            'eta' => '10:00',
            'etd' => '11:00',
            'runway' => 'RWY 04',
            'taxiway' => 'TWY A',
        ], $overrides);
    }

    protected function createMovement(array $overrides = []): Movement
    {
        $response = $this->postJson('/api/v1/movements', $this->movementPayload($overrides));
        $response->assertCreated();

        return Movement::findOrFail($response->json('data.id'));
    }
}
