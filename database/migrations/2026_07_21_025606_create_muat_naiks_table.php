<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // This migration is skipped - actual muat_naik table created in 2026_07_20_000000
        // This migration historically tried to add columns that are now part of creation
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: table creation handled separately
    }
};
