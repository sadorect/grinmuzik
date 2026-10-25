<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A scheduled release is an unpublished track/album with a future go-live
     * time. `releases:publish-scheduled` flips it live when the time arrives,
     * so every existing visibility check (is_published) keeps working as-is.
     */
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->timestamp('scheduled_release_at')->nullable()->after('published_at')->index();
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->timestamp('scheduled_release_at')->nullable()->after('is_published')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropIndex(['scheduled_release_at']);
            $table->dropColumn('scheduled_release_at');
        });

        Schema::table('albums', function (Blueprint $table) {
            $table->dropIndex(['scheduled_release_at']);
            $table->dropColumn('scheduled_release_at');
        });
    }
};
