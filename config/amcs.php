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
    | Realtime transport. The ATC monitor and dashboard subscribe to a private
    | Reverb channel. A controlled poll remains as a fallback at
    | `poll_seconds` when the socket is unavailable and at
    | `fallback_poll_seconds` while it is connected (safety net for missed
    | events). Target: <= 3 seconds.
    */
    'realtime' => [
        'broadcast' => filter_var(env('AMCS_REALTIME_BROADCAST', true), FILTER_VALIDATE_BOOLEAN),
        'channel' => env('AMCS_REALTIME_CHANNEL', 'amcs.stands'),
        'poll_seconds' => (int) env('AMCS_REALTIME_POLL_SECONDS', 3),
        'fallback_poll_seconds' => (int) env('AMCS_REALTIME_FALLBACK_POLL_SECONDS', 15),

        // Public endpoint used by the browser. Kept separate from REVERB_HOST
        // so the server can broadcast over loopback (127.0.0.1:8080) while
        // browsers connect through the HTTPS reverse proxy.
        'client_host' => env('AMCS_REVERB_CLIENT_HOST', env('REVERB_HOST', 'localhost')),
        'client_port' => (int) env('AMCS_REVERB_CLIENT_PORT', env('REVERB_PORT', 8080)),
        'client_scheme' => env('AMCS_REVERB_CLIENT_SCHEME', env('REVERB_SCHEME', 'http')),
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
