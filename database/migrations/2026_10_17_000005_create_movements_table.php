<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movements', function (Blueprint $table) {
            $table->id();
            $table->date('report_date')->index();
            $table->string('flight_number', 32)->index();
            $table->foreignId('airline_id')->nullable()->constrained()->nullOnDelete();
            $table->string('airline_name')->nullable();
            $table->string('registration', 32)->nullable();
            $table->foreignId('aircraft_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('aircraft_type_name')->nullable();
            $table->string('flight_type', 32)->default('SCHEDULED')->index();
            $table->string('origin', 8)->nullable();
            $table->string('destination', 8)->nullable();
            $table->foreignId('parking_stand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('state', 16)->default('ENTERING')->index();
            $table->time('eta')->nullable();
            $table->time('ata')->nullable();
            $table->time('etd')->nullable();
            $table->time('atd')->nullable();
            $table->string('runway', 16)->nullable();
            $table->string('taxiway', 16)->nullable();
            $table->unsignedInteger('delay_minutes')->default(0);
            $table->string('delay_reason', 64)->nullable();
            $table->text('delay_notes')->nullable();
            $table->text('notes')->nullable();
            $table->string('override_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parking_stand_id', 'state']);
            $table->index(['report_date', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movements');
    }
};
