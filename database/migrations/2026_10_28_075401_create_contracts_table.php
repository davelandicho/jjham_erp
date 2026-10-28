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
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->string('type')->index();

            $table->string('contract_number')->nullable();
            $table->date('issued_on');
            $table->string('contract_path');
            $table->string('contract_name');

            $table->date('signed_on')->nullable();
            $table->string('signed_path')->nullable();
            $table->string('signed_name')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('mwo_status')->nullable()->index();
            $table->date('mwo_submitted_on')->nullable();
            $table->date('mwo_verified_on')->nullable();
            $table->string('mwo_proof_path')->nullable();
            $table->string('mwo_proof_name')->nullable();
            $table->string('mwo_return_reason')->nullable();


            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
