<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->boolean('show_registration_role_guide')->default(true)->after('show_public_theme_toggle');
        });

        SiteSetting::query()->update([
            'show_registration_role_guide' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn('show_registration_role_guide');
        });
    }
};
