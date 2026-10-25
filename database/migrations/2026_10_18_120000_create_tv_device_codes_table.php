<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending TV sign-ins (device-code flow, after RFC 8628).
 *
 * Typing a password with a remote is miserable, so the TV asks for a code,
 * shows the short user_code on screen, and polls with the long device_code
 * while the listener approves the short one at /tv on their phone. Once the
 * TV has collected its token the row is spent and only waits to be pruned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tv_device_codes', function (Blueprint $table): void {
            $table->id();

            // The TV's secret. Hashed, never stored raw: whoever holds it
            // collects the token once the code is approved.
            $table->string('device_code_hash', 64)->unique();

            // What the person types. Stored without the display dash.
            $table->string('user_code', 8)->unique();

            $table->string('device_name', 80)->nullable();
            $table->string('requested_ip', 45)->nullable();

            // Set on approval; null until then.
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('status')->default('pending')->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_polled_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tv_device_codes');
    }
};
