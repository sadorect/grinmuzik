<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deletion requests raised from the public page at /account/delete.
 *
 * Google Play requires a web URL where account deletion can be requested
 * without installing the app, so the request has to be capturable from a
 * signed-out browser. That means it cannot be trusted on submission — anyone
 * can type anyone's address — and must be confirmed from the mailbox before
 * anything is destroyed. This table holds the request in between.
 *
 * user_id is nullable and nullOnDelete: once the deletion completes, the row
 * survives as an audit record of a request that was honoured, with no way back
 * to the person.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('account_deletion_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Kept separate from the user row so an unmatched request (someone
            // typing an address that has no account) is still rate-limitable.
            $table->string('email')->index();

            // Hashed, never stored raw: the mailbox is the only place the usable
            // token should exist, so a database leak cannot be used to delete
            // other people's accounts.
            $table->string('token_hash', 64)->unique();

            $table->string('status')->default('pending')->index();
            $table->string('requested_ip', 45)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_deletion_requests');
    }
};
