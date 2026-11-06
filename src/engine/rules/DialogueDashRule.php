<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Run;

/**
 * A line of dialogue opens with an em dash and a thin space (Bringhurst 5.2.3): `-- So this is`
 * or `— So this is` at the start of a block becomes an em dash, a narrow no-break space (so it
 * cannot strand), and the words. Whatever the language. Off by default: the Bringhurst preset
 * turns it on. A dash run (`---`, `——`), a dash with no space after it and a single hyphen (a typed
 * list bullet) are left alone.
 */
final class DialogueDashRule extends AbstractRule
{
    public function handle(): string
    {
        return 'dialogue-dash';
    }

    public function priority(): int
    {
        return 25;
    }

    public function defaultEnabled(): bool
    {
        return false;
    }

    public function triggers(): string
    {
        return '-—';
    }

    public function apply(Run $run, Context $context): void
    {
        // Only the first run of a block can open it; a run after an inline tag has text before it.
        if ($context->before(1) !== '' || !preg_match('/^(?:--|—)\x20(?=\S)/u', $run->text(), $m)) {
            return;
        }
        $run->replace(0, strlen($m[0]), "—\u{202F}");
    }
}
