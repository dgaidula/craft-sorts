<?php

namespace iceboxind\sorts\engine\rules;

/** Apostrophes, including leading ones: don’t, ’90s, ’tis, rock ’n’ roll. */
final class ApostrophesRule extends QuoteFamilyRule
{
    public function handle(): string
    {
        return 'apostrophes';
    }

    public function priority(): int
    {
        return 80;
    }

    public function defaultEnabled(): bool
    {
        return true;
    }
}
