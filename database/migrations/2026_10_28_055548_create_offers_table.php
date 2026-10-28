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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();

            $table->date('released_on');
            $table->string('position');
            $table->decimal('salary', 10 , 2);
            $table->string('currency', 3)->default('AED');
            $table->date('signing_deadline')->nullable();

            $table->string('offer_path');
            $table->string('offer_name');

            $table->string('status')->default('pending')->index();
            $table->date('signed_on')->nullable();
            $table->string('signed_path')->nullable();
            $table->string('signed_name')->nullable();
            $table->text('declined_reason')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
