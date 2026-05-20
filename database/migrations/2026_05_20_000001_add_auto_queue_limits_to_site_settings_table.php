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
            $table->unsignedSmallInteger('auto_queue_total_limit')->default(10)->after('auto_playlist_enabled');
            $table->unsignedSmallInteger('auto_queue_album_limit')->default(2)->after('auto_queue_total_limit');
            $table->unsignedSmallInteger('auto_queue_artist_limit')->default(3)->after('auto_queue_album_limit');
            $table->unsignedSmallInteger('auto_queue_genre_limit')->default(4)->after('auto_queue_artist_limit');
        });

        SiteSetting::query()->update([
            'auto_queue_total_limit'  => 10,
            'auto_queue_album_limit'  => 2,
            'auto_queue_artist_limit' => 3,
            'auto_queue_genre_limit'  => 4,
        ]);
    }

    public function down(): void
    {
        Schema::table('site_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'auto_queue_total_limit',
                'auto_queue_album_limit',
                'auto_queue_artist_limit',
                'auto_queue_genre_limit',
            ]);
        });
    }
};
