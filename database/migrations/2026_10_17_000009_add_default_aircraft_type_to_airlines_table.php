<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('airlines', function (Blueprint $table) {
            $table->foreignId('default_aircraft_type_id')
                ->nullable()
                ->after('flight_prefix')
                ->constrained('aircraft_types')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('airlines', function (Blueprint $table) {
            $table->dropForeign(['default_aircraft_type_id']);
            $table->dropColumn('default_aircraft_type_id');
        });
    }
};
