<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Tests\AmcsTestCase;

class ReportTest extends AmcsTestCase
{
    public function test_daily_report_totals(): void
    {
        $this->operator();
        $movement = $this->createMovement(['flight_number' => 'IW-800']);
        $this->postJson("/api/v1/movements/{$movement->id}/block-on")->assertOk();
        $this->postJson("/api/v1/movements/{$movement->id}/delay", [
            'delay_minutes' => 40,
            'delay_reason' => 'WEATHER',
        ])->assertOk();

        $today = CarbonImmutable::now()->toDateString();

        $this->getJson('/api/v1/reports/daily?date='.$today)
            ->assertOk()
            ->assertJsonPath('totals.movements', 1)
            ->assertJsonPath('totals.delays', 1)
            ->assertJsonPath('totals.total_delay_minutes', 40);
    }

    public function test_monthly_report_lists_period_and_stands(): void
    {
        $this->operator();
        $this->createMovement(['flight_number' => 'IW-801']);

        $now = CarbonImmutable::now();

        $this->getJson('/api/v1/reports/monthly?year='.$now->year.'&month='.$now->month)
            ->assertOk()
            ->assertJsonStructure(['period', 'totals', 'per_stand', 'by_day'])
            ->assertJsonPath('totals.movements', 1);
    }

    public function test_history_can_be_filtered_by_flight_number(): void
    {
        $this->operator();
        $this->createMovement(['flight_number' => 'IW-810']);
        $this->createMovement(['flight_number' => 'GA-811', 'parking_stand_id' => $this->stand('STAND-3')->id, 'aircraft_type_name' => 'A320']);

        $this->getJson('/api/v1/reports/history?flight_number=IW-810')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.flight_number', 'IW-810');
    }

    public function test_summary_totals_respect_active_filters(): void
    {
        $this->operator();
        $this->createMovement(['flight_number' => 'IW-830', 'report_date' => '2026-01-05']);
        $this->createMovement([
            'flight_number' => 'IW-831',
            'report_date' => '2026-02-05',
            'parking_stand_id' => $this->stand('STAND-3')->id,
        ]);

        $this->getJson('/api/v1/reports/summary?date_from=2026-01-01&date_to=2026-01-31')
            ->assertOk()
            ->assertJsonPath('totals.movements', 1)
            ->assertJsonPath('periods.0.period', '2026-01');

        $this->getJson('/api/v1/reports/summary?flight_number=IW-831')
            ->assertOk()
            ->assertJsonPath('totals.movements', 1);
    }

    public function test_export_csv_returns_download(): void
    {
        $this->supervisor();
        $this->createMovement(['flight_number' => 'IW-820']);

        $response = $this->get('/api/v1/reports/export?format=csv');

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }

    public function test_export_xlsx_returns_download(): void
    {
        $this->supervisor();
        $this->createMovement(['flight_number' => 'IW-821']);

        $response = $this->get('/api/v1/reports/export?format=xlsx');

        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
    }
}
