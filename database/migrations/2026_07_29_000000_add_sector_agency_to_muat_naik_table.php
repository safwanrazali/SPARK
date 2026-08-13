<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Legacy migration retained as no-op because these columns already exist
        // in the base muat_naik table created in 2026_07_20_000000_create_muat_naiks_table.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op to maintain safe migration history.
    }
};
