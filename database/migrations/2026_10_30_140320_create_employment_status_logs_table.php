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
        Schema::create('employment_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();

            $table->string('status')->index();
            $table->date('effective_on');
            $table->string('source');

            $table->text('remarks')->nullable();
            $table->string('notified')->nullable();
            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();

            $table->foreignId('corrects_id')->nullable()->constrained('employment_status_logs')->restrictOnDelete();
            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employment_status_logs');
    }
};
