<?php

use App\Models\SiteSetting;
use App\Support\ThemePalette;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->boolean('payments_enabled')->default(false)->after('show_search_popular_artists');
            $table->string('primary_color', 7)->nullable()->after('payments_enabled');
            $table->string('secondary_color', 7)->nullable()->after('primary_color');
        });

        SiteSetting::query()->update([
            'payments_enabled' => false,
            'primary_color' => ThemePalette::DEFAULT_PRIMARY,
            'secondary_color' => ThemePalette::DEFAULT_SECONDARY,
        ]);
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'payments_enabled',
                'primary_color',
                'secondary_color',
            ]);
        });
    }
};
