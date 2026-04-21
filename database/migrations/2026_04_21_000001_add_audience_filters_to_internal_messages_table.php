<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('internal_messages', function (Blueprint $table): void {
            $table->json('audience_filters')
                ->nullable()
                ->after('audience');
        });
    }

    public function down(): void
    {
        Schema::table('internal_messages', function (Blueprint $table): void {
            $table->dropColumn('audience_filters');
        });
    }
};
