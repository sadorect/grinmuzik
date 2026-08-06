<?php

namespace App\Support;

/**
 * Maps between Play Console products and the app's amount-based pricing.
 *
 * The app lets an artist name any price; Play Billing only sells pre-registered
 * products at fixed prices. This class is the whole of that impedance mismatch:
 * everything else in the codebase keeps working in amounts.
 */
class PlayBilling
{
    public const TYPE_UNLOCK = 'unlock';

    public const TYPE_GIFT = 'gift';

    public static function enabled(): bool
    {
        return (bool) config('play_billing.enabled');
    }

    public static function currency(): string
    {
        return strtoupper((string) config('play_billing.currency', 'NGN'));
    }

    /**
     * All products of a given type, as product_id => amount.
     *
     * @return array<string, float>
     */
    public static function tiers(string $type): array
    {
        $tiers = config("play_billing.tiers.{$type}", []);

        return array_map(static fn ($amount): float => (float) $amount, $tiers);
    }

    /**
     * The amounts an artist may choose from, ascending.
     *
     * @return array<int, float>
     */
    public static function amounts(string $type = self::TYPE_UNLOCK): array
    {
        $amounts = array_values(static::tiers($type));
        sort($amounts);

        return $amounts;
    }

    /**
     * The Play product for an exact amount, or null if it is not a valid tier.
     *
     * Deliberately exact rather than nearest-match: silently rounding a price
     * an artist chose would change what a listener is charged, and rounding
     * the other way would sell a track for less than the artist set.
     */
    public static function productForAmount(float $amount, string $type = self::TYPE_UNLOCK): ?string
    {
        foreach (static::tiers($type) as $productId => $tierAmount) {
            // Compare in minor units to dodge float equality.
            if ((int) round($tierAmount * 100) === (int) round($amount * 100)) {
                return $productId;
            }
        }

        return null;
    }

    /**
     * The amount a Play product represents, or null if unknown.
     *
     * Used when verifying a purchase: the client says which product it bought,
     * and the server decides what that is worth. Never trust a client-supplied
     * amount.
     */
    public static function amountForProduct(string $productId): ?float
    {
        foreach ([self::TYPE_UNLOCK, self::TYPE_GIFT] as $type) {
            $tiers = static::tiers($type);

            if (isset($tiers[$productId])) {
                return (float) $tiers[$productId];
            }
        }

        return null;
    }

    /**
     * Whether a product grants a permanent entitlement to a track.
     *
     * A gift must never unlock anything, so purchase handling branches on this
     * rather than on anything the client sends.
     */
    public static function typeForProduct(string $productId): ?string
    {
        foreach ([self::TYPE_UNLOCK, self::TYPE_GIFT] as $type) {
            if (array_key_exists($productId, static::tiers($type))) {
                return $type;
            }
        }

        return null;
    }

    /**
     * Is this amount sellable through the Android app?
     *
     * A track priced off-tier is still sellable on the web via Stripe; it just
     * cannot be bought in the app. Callers use this to explain that rather
     * than to reject the price outright.
     */
    public static function isPurchasableAmount(float $amount, string $type = self::TYPE_UNLOCK): bool
    {
        return static::productForAmount($amount, $type) !== null;
    }

    /**
     * Tiers that sit below the platform's minimum payment for this currency.
     *
     * Play Console pricing and CurrencyHelper::minimum() are edited in
     * different places by different people, and nothing stops them drifting
     * apart — a tier under the minimum would let the app sell at a price the
     * web flow rejects. Callers surface this rather than failing at purchase
     * time, which is where the mismatch would otherwise show up.
     *
     * @return array<string, float>
     */
    public static function tiersBelowMinimum(string $type = self::TYPE_UNLOCK): array
    {
        $minimum = CurrencyHelper::minimum(static::currency());

        return array_filter(
            static::tiers($type),
            static fn (float $amount): bool => $amount < $minimum,
        );
    }

    /**
     * Split a gross amount into provider fee, platform fee and artist net.
     *
     * Rounded to minor units at each step and the remainder given to the
     * artist, so the three parts always sum to exactly the gross — a ledger
     * that is a kobo out is a ledger nobody trusts.
     *
     * @return array{gross: float, provider_fee: float, platform_fee: float, net: float}
     */
    public static function split(float $gross, string $provider = 'play'): array
    {
        $grossMinor = (int) round($gross * 100);

        $providerRate = $provider === 'play'
            ? (float) config('play_billing.provider_fee_rate', 0.15)
            : 0.0;

        $providerMinor = (int) round($grossMinor * $providerRate);
        $platformMinor = (int) round($grossMinor * (float) config('play_billing.platform_fee_rate', 0.10));

        // Whatever is left is the artist's, so rounding can never invent or
        // destroy money.
        $netMinor = $grossMinor - $providerMinor - $platformMinor;

        return [
            'gross' => $grossMinor / 100,
            'provider_fee' => $providerMinor / 100,
            'platform_fee' => $platformMinor / 100,
            'net' => $netMinor / 100,
        ];
    }
}
