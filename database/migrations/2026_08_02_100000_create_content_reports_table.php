<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User reports against published content.
 *
 * Google Play's User Generated Content policy requires an in-app way to report
 * objectionable content and a process for acting on it. Polymorphic because the
 * three things a listener can encounter — a track, an artist profile, a comment
 * — need the same queue, and a reviewer should not have to look in three places.
 *
 * reporter_id is nullable so a signed-out listener can still report: requiring
 * an account to flag illegal content would mean the content stays up until
 * someone signs up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_reports', function (Blueprint $table): void {
            $table->id();

            $table->morphs('reportable');

            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_email')->nullable();
            $table->string('reporter_ip', 45)->nullable();

            $table->string('reason')->index();
            $table->text('details')->nullable();

            $table->string('status')->default('pending')->index();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('moderator_note')->nullable();
            $table->string('action_taken')->nullable();

            $table->timestamps();

            // The queue is always read newest-pending-first.
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
    }
};
