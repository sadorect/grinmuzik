<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit trail for artist and admin actions.
 *
 * Written after a track vanished from the site and the only way to establish
 * who removed it was to compare users.last_seen_at against tracks.deleted_at.
 * That worked by luck: sessions expire, and last_seen_at is overwritten on the
 * next visit, so the same question asked a week later would have been
 * unanswerable. This table records the answer at the moment it is still known.
 *
 * subject_label is deliberately denormalised. The whole point of the log is to
 * survive the disappearance of the thing it describes — a row reading
 * "deleted Track #242" is useless once 242 is gone, so the title is copied in
 * at write time and never refreshed.
 *
 * artist_profile_id is likewise denormalised so "show me everything this artist
 * ever did" stays a single indexed lookup even for subjects that are not owned
 * through an obvious relation (a login, say), and keeps working after the
 * subject row is purged.
 *
 * Polymorphic, and deliberately NOT morph-mapped: see the note in
 * AppServiceProvider::boot(). Relation::morphMap is global and aliasing Track
 * or ArtistProfile would orphan every existing media row. Fully-qualified
 * class names are what the rest of this app stores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();

            // Semantic event key, e.g. track.deleted, artist.verified, auth.login.
            // Not indexed here: the (event, created_at) composite below covers
            // single-column lookups as a leftmost prefix, and this table is
            // append-heavy enough that a redundant index is real write cost.
            $table->string('event', 64);

            // Null for console/scheduled work, where there is no authenticated actor.
            // Same reasoning: (actor_id, created_at) below satisfies both the
            // foreign key's index requirement and the "what did this user do"
            // lookup, so the constraint does not need its own.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            // artist | admin | listener | system — how the actor was acting at
            // the time. Stored rather than derived because roles get reassigned,
            // and the log should say what was true then, not what is true now.
            $table->string('actor_type', 16)->default('system')->index();

            // Copied in at write time so the log survives the actor's deletion,
            // which nullOnDelete above would otherwise make anonymous.
            $table->string('actor_label')->nullable();

            $table->nullableMorphs('subject');

            // Human-readable name of the subject, frozen at write time.
            $table->string('subject_label')->nullable();

            $table->foreignId('artist_profile_id')->nullable()->constrained('artist_profiles')->nullOnDelete();

            // Changed attributes as {"field": {"old": …, "new": …}}, plus any
            // extra context the recorder chose to attach.
            $table->json('properties')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->nullable()->index();

            // The log is read newest-first, usually narrowed to one artist or
            // one kind of event. Both are covered here.
            $table->index(['artist_profile_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });

        // Make the log assignable in the Roles UI. Super admins bypass
        // permission checks entirely, so this exists for delegating read
        // access to non-super admins without also handing over the panel.
        $now = now();

        $exists = DB::table('permissions')
            ->where('name', 'view audit log')
            ->where('guard_name', 'web')
            ->exists();

        if (! $exists) {
            DB::table('permissions')->insert([
                'name' => 'view audit log',
                'guard_name' => 'web',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');

        DB::table('permissions')
            ->where('name', 'view audit log')
            ->where('guard_name', 'web')
            ->delete();
    }
};
