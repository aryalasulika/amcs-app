<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stand_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parking_stand_id')->constrained()->cascadeOnDelete();
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->string('status', 16)->default('ACTIVE')->index();
            $table->timestamps();

            $table->index(['parking_stand_id', 'start_time', 'end_time'], 'stand_reservation_window');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stand_reservations');
    }
};
