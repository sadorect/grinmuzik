<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('period_type', 16);
            $table->string('period_key', 32);
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->dateTime('generated_at');
            $table->foreignId('previous_snapshot_id')->nullable()->constrained('chart_snapshots')->nullOnDelete();
            $table->unsignedInteger('total_tracks')->default(0);
            $table->unsignedBigInteger('total_plays')->default(0);
            $table->unsignedBigInteger('total_likes')->default(0);
            $table->unsignedBigInteger('total_downloads')->default(0);
            $table->timestamps();

            $table->unique(['period_type', 'period_key']);
            $table->index(['period_type', 'period_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_snapshots');
    }
};