<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parking_stands', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->boolean('active')->default(true)->index();
            $table->string('capacity_category', 32)->nullable();
            $table->string('gate_area')->nullable();
            $table->decimal('position_x', 8, 2)->nullable();
            $table->decimal('position_y', 8, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parking_stands');
    }
};
