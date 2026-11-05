<?php

namespace iceboxind\sorts\engine\rules;

/** Feet and inches, minutes and seconds: 5′10″, 45°30′. */
final class PrimesRule extends QuoteFamilyRule
{
    public function handle(): string
    {
        return 'primes';
    }

    public function priority(): int
    {
        return 70;
    }

    public function defaultEnabled(): bool
    {
        return true;
    }
}
