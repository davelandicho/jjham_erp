<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();

            $table->string('ticket_number');
            $table->string('airline');
            $table->string('flight_number');
            $table->string('origin', 10);
            $table->string('destination', 10);
            $table->dateTime('departs_at');
            $table->dateTime('arrives_at');
            $table->string('ticket_path');
            $table->string('ticket_name');

            $table->dateTime('departed_at')->nullable();
            $table->dateTime('arrived_at')->nullable();
            $table->string('cancelled_reason')->nullable();

            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
