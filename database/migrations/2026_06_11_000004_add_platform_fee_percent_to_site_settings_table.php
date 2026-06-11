<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            // Percentage (0–100) the platform retains from each Connect destination charge.
            // Stored as a decimal so admins can set e.g. 2.5%. Defaults to 5.
            $table->decimal('platform_fee_percent', 5, 2)->default(5.00)->after('platform_currency');
        });
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table) {
            $table->dropColumn('platform_fee_percent');
        });
    }
};
