<?php

namespace iceboxind\sorts\engine;

/**
 * Something in the text the engine will not change but a writer may want to look at: a
 * note from {@see Engine::findings()}, never an edit.
 */
final class Finding
{
    /** The only code so far: a digit followed by an apostrophe and an s (1930's), see {@see Findings}. */
    public const NUMERICAL_PLURAL = 'numerical-plural';

    public function __construct(
        /** What kind of finding: one of the constants above. Stable, for a program to switch on. */
        public readonly string $code,
        /** Where it starts in the input, in bytes. */
        public readonly int $offset,
        /** The text as the input has it, entities and tags between its characters excluded (1930's, 1930&#39;s). */
        public readonly string $match,
        /** What the writer might put instead (1930s). */
        public readonly string $suggestion,
        /** One sentence for a person, in plain text. */
        public readonly string $message,
    ) {
    }
}
