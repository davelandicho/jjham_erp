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
        Schema::create('visas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->string('status')->index();

            $table->date('applied_on');
            $table->string('visa_number')->nullable();
            $table->date('released_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('visa_path')->nullable();
            $table->string('visa_name')->nullable();
            $table->string('denied_reason')->nullable();

            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDeleete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visas');
    }
};
