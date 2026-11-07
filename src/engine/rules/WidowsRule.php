<?php

namespace iceboxind\sorts\engine\rules;

use iceboxind\sorts\engine\Context;
use iceboxind\sorts\engine\Options;
use iceboxind\sorts\engine\Run;

/**
 * A no-break space between the last two words, so a heading or paragraph does
 * not end on a line of one word. On for headings by default.
 *
 * Settings (rule options for `widows`): `tags`, the block elements it applies
 * to (default h1–h6), and `minWords`, the fewest words a block needs (default 3).
 * Plain text has no block, so the rule does nothing there.
 */
final class WidowsRule extends AbstractRule
{
    public const DEFAULT_TAGS = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    public const DEFAULT_MIN_WORDS = 3;

    public function handle(): string
    {
        return 'widows';
    }

    public function priority(): int
    {
        return 120;
    }

    public function defaultEnabled(): bool
    {
        return true;
    }

    public function triggers(): string
    {
        return " \t\r\n";
    }

    public function apply(Run $run, Context $context): void
    {
        $block = $context->block();
        if ($block === null || !in_array($block, $context->option('widows', 'tags', self::DEFAULT_TAGS), true)) {
            return;
        }
        // Only the run that holds the last space acts: nothing after it may contain one.
        if ($context->spaceAfter()) {
            return;
        }
        $text = $run->text();
        $searchable = trim($context->after(1)) === '' && trim($context->textAfter()) === '' ? rtrim($text) : $text;
        if (!preg_match_all('/\s+/u', $searchable, $spaces, PREG_OFFSET_CAPTURE)) {
            return;
        }
        $last = end($spaces[0]);
        if (trim(substr($searchable, 0, $last[1])) === '' && trim($context->before(1)) === '') {
            return;
        }
        // The words of the text as it stands (D898), not as the page had it: `3--5` is three words once
        // it is `3 – 5`, and a second pass would count them so.
        $minWords = (int) $context->option('widows', 'minWords', self::DEFAULT_MIN_WORDS);
        $words = count(preg_split('/\s+/u', trim($context->before(PHP_INT_MAX) . $text . $context->textAfter()), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        if ($words < $minWords) {
            return;
        }
        if ($context->mode === Options::TEXT && preg_match('/[\r\n]/', $last[0])) {
            return;
        }
        // A no-break space is already there: the locale’s narrow one (French `quoi ?`, `« oui »`) stays as it is.
        if ($last[0] !== "\u{00A0}" && $last[0] !== "\u{202F}") {
            $run->replace($last[1], strlen($last[0]), "\u{00A0}");
        }
    }
}
