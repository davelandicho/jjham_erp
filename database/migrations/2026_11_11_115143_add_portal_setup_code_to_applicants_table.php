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
            $table->string('portal_setup_code_hash')->nullable()->after('remember_token');
            $table->timestamp('portal_setup_code_expires_at')->nullable()->after('portal_setup_code_hash');
            $table->timestamp('portal_activated_at')->nullable()->after('portal_setup_code_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applicants', function (Blueprint $table) {
            $table->dropColumn([
                'portal_setup_code_hash',
                'portal_setup_code_expires_at',
                'portal_activated_at',
            ]);
        });
    }
};
