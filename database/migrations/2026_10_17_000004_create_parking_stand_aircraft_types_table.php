<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_stand_aircraft_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parking_stand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aircraft_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['parking_stand_id', 'aircraft_type_id'], 'stand_aircraft_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_stand_aircraft_types');
    }
};
