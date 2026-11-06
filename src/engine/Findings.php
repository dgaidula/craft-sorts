<?php

namespace iceboxind\sorts\engine;

/**
 * The checks behind {@see Engine::findings()}: read-only, each on one text run.
 *
 * A numerical plural takes no apostrophe (the 1930s, 2 × 4s: Bringhurst 5.4.3). The engine
 * cannot tell the plural from a possessive (1930's best film), so it never changes one; it
 * only says where one stands.
 *
 * @internal
 */
final class Findings
{
    /** A digit, an apostrophe in any form the engine reads (straight, curly, a word processor’s wrong-way mark, or an entity), and an s that ends the word. */
    private const NUMERICAL_PLURAL = '/(?<![\p{L}\p{N}])(\d+)(\'|’|‘|ʼ|&apos;|&#0*39;|&#[xX]0*27;)([sS])(?![\p{L}\p{N}])/u';

    /**
     * @param int $base where the run starts in the input, in bytes (0 for plain text)
     * @return list<Finding>
     */
    public static function scan(string $text, int $base, bool $html): array
    {
        if (strpbrk($text, "'’‘ʼ&") === false || !preg_match_all(self::NUMERICAL_PLURAL, $text, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return [];
        }
        $findings = [];
        foreach ($found as $m) {
            if (!$html && $m[2][0][0] === '&') {
                continue;   // in plain text an entity is just text
            }
            $number = $m[1][0];
            $findings[] = new Finding(
                Finding::NUMERICAL_PLURAL,
                $base + $m[0][1],
                $m[0][0],
                $number . $m[3][0],
                "A numerical plural takes no apostrophe: write {$number}{$m[3][0]}, not {$m[0][0]} (a possessive keeps it).",
            );
        }
        return $findings;
    }
}
