<?php

namespace App\Support;

class CurrencyHelper
{
    public const SUPPORTED = [
        'NGN' => 'Nigerian Naira (₦)',
        'USD' => 'US Dollar ($)',
        'GBP' => 'British Pound (£)',
        'EUR' => 'Euro (€)',
        'GHS' => 'Ghanaian Cedi (₵)',
        'KES' => 'Kenyan Shilling (KSh)',
        'ZAR' => 'South African Rand (R)',
        'CAD' => 'Canadian Dollar (CA$)',
        'CFA' => 'Central African CFA Franc (XAF)',
    ];

    /** ISO 4217 currencies Stripe treats as zero-decimal (no subunit). */
    private const ZERO_DECIMAL = ['BIF', 'CLP', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'VND', 'XAF', 'XOF'];

    public static function symbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'NGN' => '₦',
            'GBP' => '£',
            'EUR' => '€',
            'GHS' => '₵',
            'KES' => 'KSh',
            'ZAR' => 'R',
            'CAD' => 'CA$',
            'XAF' => 'XAF',
            default => '$',
        };
    }

    public static function label(string $currency): string
    {
        return self::SUPPORTED[strtoupper($currency)] ?? strtoupper($currency);
    }

    /**
     * Convert a human-facing amount to Stripe's smallest unit (kobo, pence, cents, etc.).
     */
    public static function toSmallestUnit(float $amount, string $currency): int
    {
        if (in_array(strtoupper($currency), self::ZERO_DECIMAL, true)) {
            return (int) $amount;
        }

        return (int) round($amount * 100);
    }

    /**
     * Minimum sensible donation amount per currency (human-facing, not kobo/pence).
     * These are practical minimums, not Stripe's hard limits.
     */
    public static function minimum(string $currency): float
    {
        return match (strtoupper($currency)) {
            'NGN' => 1000.0,
            'GHS' => 1.0,
            'KES' => 50.0,
            'ZAR' => 5.0,
            'XAF' => 100.0,
            default => 0.50,
        };
    }

    /**
     * Format an amount with the currency symbol for display.
     */
    public static function format(float $amount, string $currency): string
    {
        $symbol = self::symbol($currency);
        $decimals = in_array(strtoupper($currency), self::ZERO_DECIMAL, true) ? 0 : 2;

        return $symbol . number_format($amount, $decimals);
    }

    /**
     * Return preset tip amounts suitable for the given currency.
     */
    public static function presets(string $currency): array
    {
        return match (strtoupper($currency)) {
            'NGN' => [500, 1000, 2000, 5000],
            'GHS' => [5, 10, 20, 50],
            'KES' => [100, 250, 500, 1000],
            'ZAR' => [20, 50, 100, 250],
            'XAF' => [100, 200, 500, 1000],
            default => [1, 2, 5, 10],
        };
    }

    public static function isSupported(string $currency): bool
    {
        return array_key_exists(strtoupper($currency), self::SUPPORTED);
    }

    public static function normalize(string $currency): string
    {
        return strtoupper($currency);
    }
}
