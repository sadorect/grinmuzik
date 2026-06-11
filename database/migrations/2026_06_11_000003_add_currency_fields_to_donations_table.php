<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            // payout_currency is what the artist receives (may differ from charged currency via Connect FX)
            $table->string('payout_currency', 10)->nullable()->after('currency');
        });

        // Update existing `currency` column default from 'usd' to 'NGN'
        Schema::table('donations', function (Blueprint $table) {
            $table->string('currency', 10)->default('NGN')->change();
        });
    }

    public function down(): void
    {
        Schema::table('donations', function (Blueprint $table) {
            $table->dropColumn('payout_currency');
            $table->string('currency', 3)->default('usd')->change();
        });
    }
};
