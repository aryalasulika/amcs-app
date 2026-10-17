<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movement_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('movement_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('stand_code', 32)->nullable();
            $table->string('flight_number', 32)->nullable();
            $table->string('action', 64)->index();
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movement_logs');
    }
};
