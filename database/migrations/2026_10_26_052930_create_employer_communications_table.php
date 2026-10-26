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
        Schema::create('employer_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();

            $table->string('type')->index();
            $table->string('channel');
            $table->date('communicated_on');

            $table->string('contact_name')->nullable();
            $table->string('contact_number')->nullable();

            $table->string('evidence_path')->nullable();
            $table->string('evidence_name')->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employer_communications');
    }
};
