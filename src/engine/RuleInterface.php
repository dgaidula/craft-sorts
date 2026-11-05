<?php

namespace iceboxind\sorts\engine;

/**
 * A typography rule. The built-in rules and a site’s own rules implement this
 * one interface, so a site’s rule plugs in exactly as a built-in does.
 *
 * A rule is called for a {@see Run}: one stretch of text with the same locale
 * and the same set of active rules. It edits the run through the run’s methods
 * and reads its surroundings from the {@see Context}. Characters a rule writes
 * are marked settled by default, which keeps every later rule from touching them.
 *
 * Scope: a rule whose `defaultEnabled()` is true (and not switched off in the
 * options) runs everywhere. Any rule also runs inside an element marked
 * `data-sorts-rules="<handle>"`, and when the call names it:
 * `$engine->apply($text, ['<handle>'])`.
 */
interface RuleInterface
{
    /** Unique, lower-case, ASCII: used in options, `data-sorts-rules` and calls. */
    public function handle(): string;

    /** Rules run in ascending priority; equal priorities keep registration order. */
    public function priority(): int;

    public function defaultEnabled(): bool;

    /** Whether the rule applies in this locale (locale awareness). */
    public function supportsLocale(LocaleInterface $locale): bool;

    /** True if the rule can write markup. The engine never runs such a rule in text mode. */
    public function emitsMarkup(): bool;

    /**
     * Characters at least one of which a run must contain for {@see apply()} to
     * be worth calling, or null to be called for every run. Only a speed-up: the
     * rule still checks for itself. Multi-byte characters match on their bytes,
     * so the filter may let extra runs through but never drops a matching one.
     */
    public function triggers(): ?string;

    public function apply(Run $run, Context $context): void;
}
