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
        Schema::table('muat_naik', function (Blueprint $table) {
            $table->string('sector_code')->nullable()->after('tarikh_import');
            $table->string('sector_name')->nullable()->after('sector_code');
            $table->string('agency_code')->nullable()->after('sector_name');
            $table->string('agency_name')->nullable()->after('agency_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('muat_naik', function (Blueprint $table) {
            $table->dropColumn([
                'sector_code',
                'sector_name',
                'agency_code',
                'agency_name',
            ]);
        });
    }
};
