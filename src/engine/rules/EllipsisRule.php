<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/** Three dots to …; four to … followed by a full stop. Longer runs are left alone. */
final class EllipsisRule extends AbstractRule
{
    public function handle(): string
    {
        return 'ellipsis';
    }

    public function priority(): int
    {
        return 20;
    }

    public function defaultEnabled(): bool
    {
        return true;
    }

    public function triggers(): string
    {
        return '.';
    }

    public function apply(Run $run, Context $context): void
    {
        $run->replaceAll('/(?<!\.)\.\.\.(\.)?(?!\.)/', static fn (array $m): string => '…' . ($m[1] ?? ''));
    }
}
