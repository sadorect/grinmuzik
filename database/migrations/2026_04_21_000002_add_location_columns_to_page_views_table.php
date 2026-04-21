<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table): void {
            $table->string('country', 100)->nullable()->after('user_agent');
            $table->string('country_code', 2)->nullable()->after('country');
            $table->string('region', 120)->nullable()->after('country_code');
            $table->string('city', 120)->nullable()->after('region');
            $table->string('timezone', 120)->nullable()->after('city');
            $table->decimal('latitude', 10, 7)->nullable()->after('timezone');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('location_source', 40)->nullable()->after('longitude');
            $table->timestamp('resolved_location_at')->nullable()->after('location_source');

            $table->index(['country', 'created_at']);
            $table->index(['ip_address', 'resolved_location_at']);
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table): void {
            $table->dropIndex(['country', 'created_at']);
            $table->dropIndex(['ip_address', 'resolved_location_at']);
            $table->dropColumn([
                'country',
                'country_code',
                'region',
                'city',
                'timezone',
                'latitude',
                'longitude',
                'location_source',
                'resolved_location_at',
            ]);
        });
    }
};
