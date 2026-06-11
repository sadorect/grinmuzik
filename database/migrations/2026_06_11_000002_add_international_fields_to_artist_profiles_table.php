<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artist_profiles', function (Blueprint $table) {
            // Artist's own currency preference for receiving donations
            $table->string('preferred_currency', 10)->nullable()->after('total_donations');
            // Admin override takes precedence over artist preference
            $table->string('admin_currency_override', 10)->nullable()->after('preferred_currency');
            // ISO 3166-1 alpha-2 country code (used for Stripe Connect account creation)
            $table->string('country_code', 2)->nullable()->after('admin_currency_override');
            // Stripe Connect Express account for receiving payouts
            $table->string('stripe_connect_account_id')->nullable()->after('country_code');
            // not_connected | pending | active | restricted
            $table->string('stripe_connect_status', 20)->default('not_connected')->after('stripe_connect_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('artist_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'preferred_currency',
                'admin_currency_override',
                'country_code',
                'stripe_connect_account_id',
                'stripe_connect_status',
            ]);
        });
    }
};
