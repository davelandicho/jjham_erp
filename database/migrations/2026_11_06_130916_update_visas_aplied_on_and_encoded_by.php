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
        Schema::table('visas', function (Blueprint $table) {
            $table->date('applied_on')->nullable()->change();

            $table->dropForeign(['encoded_by']);

            $table->foreign('encoded_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('visas', function (Blueprint $table) {
            $table->dropForeign(['encoded_by']);
            $table->foreign('encoded_by')->references('id')->on('users');

            $table->date('applied_on')->nullable(false)->change();
        });
    }
};
