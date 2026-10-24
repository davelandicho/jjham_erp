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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('version');

            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedInteger('size');

            $table->date('expiry_date')->nullable();
            
            $table->string('status')->default('pending')->index();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('upload_source')->nullable();

            $table->timestamps();

            $table->unique(['application_id', 'document_type_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
