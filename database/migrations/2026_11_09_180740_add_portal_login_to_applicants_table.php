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
        Schema::table('applicants', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email');
            $table->rememberToken()->after('password');

            $table->dropIndex(['mobile']);
            $table->dropIndex(['email']);
            $table->unique('mobile');
            $table->unique('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropUnique(['mobile']);
            $table->dropUnique(['email']);
            $table->index('mobile');
            $table->index('email');

            $table->dropColumn(['password', 'remember_token']);
        });
    }
};
