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
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_order_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('registered')->index();
            $table->timestamp('status_changed_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['applicant_id', 'job_order_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
