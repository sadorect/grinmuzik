<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make purchases provider-agnostic, and start recording what artists are owed.
 *
 * Two things forced this:
 *
 * 1. The donations table assumed Stripe. Every purchase carried a
 *    stripe_payment_intent_id, and "was this paid for" meant "does a Stripe
 *    row exist". Google Play purchases identify themselves with a purchase
 *    token instead, so the provider becomes explicit and the Stripe columns
 *    become one provider's detail rather than the shape of the table.
 *
 * 2. Stripe Connect destination charges pay the artist directly — the money
 *    never belongs to the platform. Google Play pays the developer ONLY. The
 *    artist's share therefore becomes a debt the platform owes, and a debt
 *    that is not written down is a debt that gets reconstructed wrongly later.
 *    artist_ledger_entries records it at the moment it is incurred.
 *
 * The ledger is deliberately settlement-agnostic: it records what is owed, not
 * how it will be paid. No artist has connected a Stripe account yet, so the
 * first payouts will be manual; when Connect onboarding exists, a payout run
 * reads outstanding balances and writes negative entries. Nothing here needs
 * to change for that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donations', function (Blueprint $table): void {
            // Which payment rail this purchase came through. Defaults to
            // stripe so every existing row keeps its meaning.
            $table->string('provider', 16)->default('stripe')->after('status')->index();

            // Google's identifier for the purchase. Unique because replaying a
            // token must never create a second entitlement or a second ledger
            // credit — this constraint is the last line of defence behind the
            // application check.
            $table->string('play_purchase_token', 255)->nullable()->unique()->after('provider');

            // The Play Console product that was bought, e.g. unlock_tier_500.
            $table->string('play_product_id', 64)->nullable()->after('play_purchase_token');

            // Google auto-refunds anything not acknowledged within 3 days, so
            // the acknowledgement state has to be durable, not in-memory.
            $table->timestamp('play_acknowledged_at')->nullable()->after('play_product_id');
        });

        Schema::create('artist_ledger_entries', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('artist_profile_id')->constrained()->cascadeOnDelete();

            // Null for manual adjustments and payouts, which have no purchase.
            $table->foreignId('donation_id')->nullable()->constrained()->nullOnDelete();

            // credit  = a purchase earned the artist money
            // payout  = the platform settled some of it (negative net_amount)
            // adjustment = manual correction, refund clawback, etc.
            $table->string('type', 16)->index();

            $table->string('provider', 16)->nullable();
            $table->string('currency', 10);

            // What the buyer actually paid.
            $table->decimal('gross_amount', 12, 2)->default(0);

            // The store's cut (Google/Stripe). Recorded, not inferred, so the
            // ledger still reconciles if the rate changes later.
            $table->decimal('provider_fee', 12, 2)->default(0);

            // The platform's cut.
            $table->decimal('platform_fee', 12, 2)->default(0);

            // The signed amount that moves the artist's balance: positive for
            // credits, negative for payouts. Balance is SUM(net_amount), which
            // keeps "what do we owe this artist" a single indexed aggregate.
            $table->decimal('net_amount', 12, 2);

            $table->string('description')->nullable();

            // Set when this entry has been settled/paid out.
            $table->timestamp('settled_at')->nullable();
            $table->string('payout_reference')->nullable();

            $table->timestamps();

            // The two questions actually asked: this artist's balance, and the
            // outstanding (unsettled) entries across everyone.
            $table->index(['artist_profile_id', 'created_at']);
            $table->index(['settled_at', 'artist_profile_id']);
        });

        // A purchase may only ever credit an artist once. Enforced in the
        // database because a duplicate credit is money, and the application
        // layer is not the right place to be the only guard.
        DB::statement(
            'CREATE UNIQUE INDEX artist_ledger_unique_donation_credit
             ON artist_ledger_entries (donation_id, type)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_ledger_entries');

        Schema::table('donations', function (Blueprint $table): void {
            $table->dropIndex(['provider']);
            $table->dropUnique(['play_purchase_token']);
            $table->dropColumn([
                'provider',
                'play_purchase_token',
                'play_product_id',
                'play_acknowledged_at',
            ]);
        });
    }
};
