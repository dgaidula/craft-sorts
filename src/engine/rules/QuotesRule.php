<?php

namespace iceboxind\sorts\engine\rules;

/** Straight double and single quotes to the locale’s opening and closing quotes: by the mark typed, or by nesting depth (see QuoteScanner). */
final class QuotesRule extends QuoteFamilyRule
{
    public function handle(): string
    {
        return 'quotes';
    }

    public function priority(): int
    {
        return 90;
    }

    public function defaultEnabled(): bool
    {
        return true;
    }
}
