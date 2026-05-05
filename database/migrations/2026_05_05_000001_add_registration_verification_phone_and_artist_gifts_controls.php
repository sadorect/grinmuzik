<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 32)->nullable()->after('email');
        });

        Schema::table('tracks', function (Blueprint $table): void {
            $table->boolean('allow_artist_gifts')->default(true)->after('donation_amount');
        });

        Schema::table('site_settings', function (Blueprint $table): void {
            $table->boolean('registration_requires_email_verification')
                ->default(true)
                ->after('show_registration_role_guide');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn('registration_requires_email_verification');
        });

        Schema::table('tracks', function (Blueprint $table): void {
            $table->dropColumn('allow_artist_gifts');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });
    }
};