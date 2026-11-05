<?php

/**
 * Engine tests: plain PHP, no Craft. Run with `composer test-engine`.
 * Prints `RESULT: <n> passed, <m> failed` and exits non-zero on any failure.
 *
 * - corpus/*.txt: the typography corpus (see corpus/README for the format).
 * - unit.php: the API, the tokenizer’s lossless guarantee, locales, scopes.
 * - fuzz.php: seeded messy HTML through the engine, byte-identical checks.
 */

use iceboxind\sorts\engine\Engine;
use iceboxind\sorts\engine\Options;
use iceboxind\sorts\tests\engine\HyphenToEnDashRule;
use iceboxind\sorts\tests\engine\MarkupRule;
use iceboxind\sorts\tests\engine\GermanOnlyRule;
use iceboxind\sorts\tests\engine\RecipeFractionsRule;

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/FixtureRules.php';

$GLOBALS['passed'] = 0;
$GLOBALS['failed'] = 0;
$filter = $argv[1] ?? null;

function pass(): void
{
    $GLOBALS['passed']++;
}

function fail(string $name, string $message): void
{
    $GLOBALS['failed']++;
    echo "FAIL $name\n$message\n\n";
}

/** Make invisible characters visible in a failure message. */
function vis(string $s): string
{
    return str_replace(
        ["\u{00A0}", "\u{202F}", "\u{2009}", "\r", "\n", "\t", "\u{FEFF}"],
        ['\u{A0}', '\u{202F}', '\u{2009}', '\r', '\n', '\t', '\u{FEFF}'],
        $s
    );
}

function check(string $name, mixed $expected, mixed $actual): void
{
    if ($expected === $actual) {
        pass();
        return;
    }
    if (is_string($expected) && is_string($actual) && strlen($expected) > 300) {
        // a long page: show a window around the first difference
        $at = 0;
        $max = min(strlen($expected), strlen($actual));
        while ($at < $max && $expected[$at] === $actual[$at]) {
            $at++;
        }
        $from = max(0, $at - 60);
        fail($name, "  first difference at byte $at\n  expected: ..." . vis(substr($expected, $from, 140)) . "...\n  actual:   ..." . vis(substr($actual, $from, 140)) . '...');
        return;
    }
    fail($name, '  expected: ' . vis(var_export($expected, true)) . "\n  actual:   " . vis(var_export($actual, true)));
}

function newEngine(): Engine
{
    $engine = new Engine();
    $engine->register(new RecipeFractionsRule());
    $engine->register(new HyphenToEnDashRule());
    $engine->register(new MarkupRule());
    $engine->register(new GermanOnlyRule());
    return $engine;
}

require __DIR__ . '/corpus.php';
if ($filter === null || $filter === 'unit') {
    require __DIR__ . '/unit.php';
}
if ($filter === null || $filter === 'fuzz') {
    require __DIR__ . '/fuzz.php';
}

echo "RESULT: {$GLOBALS['passed']} passed, {$GLOBALS['failed']} failed\n";
exit($GLOBALS['failed'] > 0 ? 1 : 0);
