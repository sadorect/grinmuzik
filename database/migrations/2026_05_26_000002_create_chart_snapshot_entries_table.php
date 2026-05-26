<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_snapshot_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chart_snapshot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('rank');
            $table->unsignedSmallInteger('previous_rank')->nullable();
            $table->string('movement', 16)->default('new');
            $table->smallInteger('movement_delta')->nullable();
            $table->unsignedInteger('plays')->default(0);
            $table->unsignedInteger('likes_count')->default(0);
            $table->unsignedInteger('downloads_count')->default(0);
            $table->decimal('score', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['chart_snapshot_id', 'rank']);
            $table->unique(['chart_snapshot_id', 'track_id']);
            $table->index(['track_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_snapshot_entries');
    }
};