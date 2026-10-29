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
        Schema::create('exit_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();

            $table->string('rfp_reference')->nullable();
            $table->date('submitted_on');

            $table->string('clearance_number')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('clearance_path')->nullable();
            $table->string('clearance_name')->nullable();

            $table->string('returned_reason')->nullable();

            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exit_clearances');
    }
};
