<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stop an artist's account deletion from destroying payment history.
 *
 * donations.artist_profile_id was created cascadeOnDelete, so deleting an
 * artist removed every donation ever made to them — including donations made by
 * other people, and including the financial records we are obliged to retain
 * for tax and accounting. donations.user_id was already nullOnDelete for exactly
 * that reason; the artist side was simply inconsistent with it.
 *
 * This also makes the public disclosure at /account/delete true. That page
 * promises donation records survive with identifying details removed, which was
 * not the case for the artist side of the relationship.
 *
 * Applied while the donations table is empty, so there is nothing to migrate —
 * doing this later would mean rewriting live financial rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            $table->dropForeign(['artist_profile_id']);
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->foreignId('artist_profile_id')->nullable()->change();
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->foreign('artist_profile_id')
                ->references('id')
                ->on('artist_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Rows orphaned while this migration was applied cannot satisfy the old
        // NOT NULL constraint, so they are cleared rather than blocking the
        // rollback with a constraint violation.
        \Illuminate\Support\Facades\DB::table('donations')->whereNull('artist_profile_id')->delete();

        Schema::table('donations', function (Blueprint $table): void {
            $table->dropForeign(['artist_profile_id']);
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->foreignId('artist_profile_id')->nullable(false)->change();
        });

        Schema::table('donations', function (Blueprint $table): void {
            $table->foreign('artist_profile_id')
                ->references('id')
                ->on('artist_profiles')
                ->cascadeOnDelete();
        });
    }
};
