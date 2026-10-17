<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Airport identity
    |--------------------------------------------------------------------------
    */

    'airport' => [
        'name' => env('AMCS_AIRPORT_NAME', 'Bandara Syukuran Aminuddin Amir Luwuk'),
        'icao' => env('AMCS_AIRPORT_ICAO', 'WAMW'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Operational rules
    |--------------------------------------------------------------------------
    */

    'delay_threshold_minutes' => (int) env('AMCS_DELAY_THRESHOLD_MINUTES', 30),

    /*
    | Delay reasons offered to operators (FR-10).
    */
    'delay_reasons' => [
        'WEATHER' => 'Cuaca buruk',
        'TECHNICAL' => 'Kerusakan / maintenance pesawat',
        'COMMERCIAL' => 'Handling penumpang / bagasi',
        'LATE_ARRIVAL' => 'Keterlambatan dari bandara asal',
        'ATC_TRAFFIC' => 'Kepadatan lalu lintas udara',
        'SLOT' => 'Menunggu slot / ATC clearance',
        'GROUND_HANDLING' => 'Ground handling / peralatan',
        'OTHER' => 'Lainnya',
    ],

    /*
    | Realtime transport. The ATC monitor and dashboard poll a lightweight
    | status endpoint at this interval (seconds). Target: <= 3 seconds.
    */
    'realtime' => [
        'poll_seconds' => (int) env('AMCS_REALTIME_POLL_SECONDS', 3),
    ],

    /*
    | Default turnaround used to build a stand reservation when an ETD is
    | not yet known.
    */
    'default_turnaround_minutes' => (int) env('AMCS_DEFAULT_TURNAROUND_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    'roles' => [
        'admin' => 'Administrator',
        'supervisor' => 'Supervisor',
        'amc_operator' => 'AMC Operator',
        'atc_viewer' => 'ATC Viewer',
    ],

    /*
    |--------------------------------------------------------------------------
    | Report export
    |--------------------------------------------------------------------------
    */

    'export' => [
        'filename' => 'LAPORAN_TERPADU_AMCS',
    ],
];
