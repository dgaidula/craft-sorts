<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * Base of the three rules that read straight quotes: {@see PrimesRule},
 * {@see ApostrophesRule} and {@see QuotesRule}. They are separate rules, each
 * with its own handle and default, but they share one scan of the run (see
 * {@see QuoteScanner}); whichever runs first does the work for all that are on.
 */
abstract class QuoteFamilyRule extends AbstractRule
{
    public function triggers(): string
    {
        return QuoteScanner::TRIGGERS;
    }

    public function apply(Run $run, Context $context): void
    {
        QuoteScanner::scan($run, $context);
    }
}
