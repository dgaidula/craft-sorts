<?php

namespace iceboxind\sorts\engine;

use iceboxind\sorts\engine\rules\ApostrophesRule;
use iceboxind\sorts\engine\rules\DashesRule;
use iceboxind\sorts\engine\rules\DialogueDashRule;
use iceboxind\sorts\engine\rules\EllipsisRule;
use iceboxind\sorts\engine\rules\EllipsisSpacingRule;
use iceboxind\sorts\engine\rules\FractionsRule;
use iceboxind\sorts\engine\rules\HookAmpRule;
use iceboxind\sorts\engine\rules\HookCapsRule;
use iceboxind\sorts\engine\rules\HookQuotesRule;
use iceboxind\sorts\engine\rules\LocaleSpacingRule;
use iceboxind\sorts\engine\rules\MultiplicationRule;
use iceboxind\sorts\engine\rules\NonBreakingSpacesRule;
use iceboxind\sorts\engine\rules\OrdinalsRule;
use iceboxind\sorts\engine\rules\PrimesRule;
use iceboxind\sorts\engine\rules\QuotesRule;
use iceboxind\sorts\engine\rules\RangesRule;
use iceboxind\sorts\engine\rules\SymbolsRule;
use iceboxind\sorts\engine\rules\TypedDashesRule;
use iceboxind\sorts\engine\rules\WidowsRule;
use InvalidArgumentException;

/**
 * The typography engine: HTML or plain text in, the same string out with only
 * its text runs changed. No Craft or Yii code lives here.
 *
 * The page is split into markup and text by a pattern, never parsed into a tree
 * and never re-serialized, so anything the engine does not change comes out
 * byte for byte. If nothing changes, the input string itself comes back.
 */
final class Engine
{
    /**
     * An attribute list and the end of its tag. A value may hold “>”; a quote that
     * never closes runs to the end, as it does in a browser.
     */
    private const ATTRIBUTES = '(?:[^>"\'=]++|=\s*+(?:"[^"]*+(?:"|\z)|\'[^\']*+(?:\'|\z))?|["\'])*+(?:>|\z)';

    /**
     * Text, ordinary tags, doctypes and bogus comments, each starting where the last
     * one ended (`\G` anchors every alternative, so PCRE treats the pattern as
     * anchored). A scan stops at the first comment, CDATA section or raw-text element
     * (`script style xmp iframe noembed noframes textarea title`): the walk finds where
     * those end itself, with plain searches rather than a lazy quantifier, so a long
     * body cannot run into PCRE’s backtracking limit, and a script follows the HTML5
     * escape states ({@see scriptEnd()}).
     */
    private const TOKENS = '~\G(?:
        [^<]++|<(?![a-zA-Z/!?])                                              # text
      | <(?!!--|!\[CDATA\[)[!?][^>]*+(?:>|\z)|</(?![a-zA-Z])[^>]*+(?:>|\z)   # doctype, bogus comment
      | <(?!(?:script|style|xmp|iframe|noembed|noframes|textarea|title)(?=[\s/>]))/?[a-zA-Z][^\s/>]*+
        ' . self::ATTRIBUTES . '                                             # a tag
    )~xsiD';

    /** A raw-text element’s opening or closing tag, anchored where it starts. */
    private const RAW_TAG = '~\G</?[a-zA-Z]++' . self::ATTRIBUTES . '~siD';

    /** The characters that end a tag name. */
    private const NAME_END = " \t\n\x0B\f\r/>";

    /**
     * How deep elements may nest before the engine gives the page back unchanged.
     * Browsers stop building a tree at 512; a page far beyond that is not one to edit,
     * and a chain of tens of thousands of frames would overflow PHP’s stack on cleanup.
     */
    private const MAX_DEPTH = 1024;

    private LocaleRegistry $locales;

    /** @var list<RuleInterface> in registration order */
    private array $registered = [];

    /** @var list<RuleInterface>|null by priority, then registration order */
    private ?array $sorted = null;

    private ?string $lastError = null;

    public function __construct(private Options $options = new Options(), ?LocaleRegistry $locales = null)
    {
        $this->locales = $locales ?? new LocaleRegistry();
        foreach ([
            new SymbolsRule(), new EllipsisRule(), new EllipsisSpacingRule(), new DialogueDashRule(), new DashesRule(), new TypedDashesRule(), new FractionsRule(), new RangesRule(),
            new MultiplicationRule(), new PrimesRule(), new ApostrophesRule(), new QuotesRule(),
            new NonBreakingSpacesRule(), new LocaleSpacingRule(), new WidowsRule(), new OrdinalsRule(),
            new HookAmpRule(), new HookCapsRule(), new HookQuotesRule(),
        ] as $rule) {
            $this->register($rule);
        }
    }

    public function locales(): LocaleRegistry
    {
        return $this->locales;
    }

    /**
     * Add a rule. Its handle must be new: lower-case ASCII letters, digits and
     * hyphens, starting with a letter.
     */
    public function register(RuleInterface $rule): void
    {
        $handle = $rule->handle();
        if (!preg_match('/^[a-z][a-z0-9-]*$/', $handle)) {
            throw new InvalidArgumentException("Rule handle “{$handle}” must be lower-case letters, digits and hyphens.");
        }
        foreach ($this->registered as $other) {
            if ($other->handle() === $handle) {
                throw new InvalidArgumentException("A rule with the handle “{$handle}” is already registered.");
            }
        }
        $this->registered[] = $rule;
        $this->sorted = null;
    }

    /** @return list<RuleInterface> every registered rule, in the order they run */
    public function rules(): array
    {
        if ($this->sorted === null) {
            $indexed = [];
            foreach ($this->registered as $i => $rule) {
                $indexed[] = [$rule->priority(), $i, $rule];
            }
            usort($indexed, static fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $this->sorted = array_column($indexed, 2);
        }
        return $this->sorted;
    }

    /**
     * Fix the typography of $input. Runs every rule that is on by default
     * (everywhere), the rules a `data-sorts-rules` attribute names (inside that
     * element), and the rules named in $rules (everywhere).
     *
     * @param list<string> $rules handles of rules to run in addition, by name
     * @throws InvalidArgumentException if a handle in $rules is not registered
     */
    public function apply(string $input, array $rules = [], ?Options $options = null): string
    {
        $options ??= $this->options;
        $known = [];
        foreach ($this->registered as $rule) {
            $known[$rule->handle()] = true;
        }
        foreach ($rules as $handle) {
            if (!isset($known[$handle])) {
                throw new InvalidArgumentException("No rule is registered with the handle “{$handle}”.");
            }
        }
        $this->lastError = null;
        // A string that is not UTF-8 is not ours to fix: return it as it came.
        if ($input === '' || preg_match('//u', $input) !== 1) {
            if ($input !== '' && preg_last_error() !== PREG_BAD_UTF8_ERROR) {
                $this->lastError = 'checking the text is UTF-8 failed: ' . preg_last_error_msg();
            }
            return $input;
        }
        $pass = new Pass($options, array_fill_keys($rules, true), $this->locales->resolveOrFallback($options->locale));
        $pass->locale = $this->withVariant($pass, $pass->locale);
        $output = $options->mode === Options::TEXT ? $this->applyText($input, $pass) : $this->applyHtml($input, $pass);
        $this->lastError = $pass->error;
        return $output;
    }

    /**
     * What a writer may want to look at in $input, which the engine will not change: a numerical
     * plural with an apostrophe (1930's). Read-only, and over the same text apply() would edit:
     * no code, attribute, script, excluded element or comment, and none inside a `data-sorts="off"`
     * element. No rule runs and nothing is written; no rule switch, locale or variant matters.
     * An input that is not UTF-8 has none.
     *
     * @return list<Finding> in input order, with each offset in bytes into $input
     */
    public function findings(string $input, ?Options $options = null): array
    {
        $options ??= $this->options;
        if ($input === '' || preg_match('//u', $input) !== 1) {
            return [];
        }
        $pass = new Pass($options, [], $this->locales->resolveOrFallback($options->locale));
        $pass->findings = [];
        $options->mode === Options::TEXT ? $this->applyText($input, $pass) : $this->applyHtml($input, $pass);
        return $pass->findings;
    }

    /**
     * Why the last call to {@see apply()} gave its input back unchanged although it
     * may hold text to fix: a pattern failed (PCRE’s backtracking or JIT stack limit,
     * for example). Null when the last call ran normally.
     */
    public function lastError(): ?string
    {
        return $this->lastError;
    }

    private function applyText(string $input, Pass $pass): string
    {
        $root = new Frame('', '', null, $pass->locale, false, '', null);
        $parts = preg_split('/(\R[ \t]*\R\s*)/u', $input, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE);
        if ($parts === false) {
            $pass->error = 'splitting the text into paragraphs failed: ' . preg_last_error_msg();
            return $input;
        }
        foreach ($parts as $i => [$text, $offset]) {
            if ($i % 2 === 0 && $text !== '') {
                $flow = new Flow(null);
                $flow->add($offset, $text, true, $root);
                $this->processFlow($flow, $pass);
            }
        }
        return $pass->changed ? self::splice($input, $pass->edits) : $input;
    }

    private function applyHtml(string $input, Pass $pass): string
    {
        $options = $pass->options;
        $skipTags = array_fill_keys(array_map('strtolower', $options->skipTags), true);
        $skipClasses = $options->skipClasses;
        $top = new Frame('', '', null, $pass->locale, false, '', null);
        $flow = null;
        $length = strlen($input);
        $at = 0;
        // One string per tag name, shared by every frame of that name.
        $names = [];
        // How many elements of each name are open, so an end tag that closes nothing
        // costs no walk of the stack.
        $open = [];

        while ($at < $length) {
            if (preg_match_all(self::TOKENS, $input, $m, 0, $at) === false) {
                $pass->error = 'tokenizing the page failed: ' . preg_last_error_msg();
                return $input;
            }
            foreach ($m[0] as $token) {
                $offset = $at;
                $at += strlen($token);
                if ($token[0] !== '<' || !isset($token[1])) {
                    if ($flow === null) {
                        if (ctype_space($token)) {
                            continue;
                        }
                        $flow = new Flow($top->block);
                    }
                    $flow->add($offset, $token, !$top->skip, $top);
                    continue;
                }
                $closing = $token[1] === '/';
                if ($token[1] === '!' || $token[1] === '?' || ($closing && !ctype_alpha($token[2] ?? ''))) {
                    continue;   // a doctype or a bogus comment: markup, nothing to do
                }

                $start = $closing ? 2 : 1;
                $name = strtolower(substr($token, $start, strcspn($token, self::NAME_END, $start)));
                $name = $names[$name] ??= $name;
                if ($closing) {
                    if (!isset(Html::INLINE[$name])) {
                        $flow = $this->flush($flow, $pass);
                    }
                    if (($open[$name] ?? 0) > 0) {
                        for ($f = $top; $f->parent !== null; $f = $f->parent) {
                            $open[$f->name]--;
                            if ($f->name === $name) {
                                $top = $f->parent;
                                break;
                            }
                        }
                    }
                    continue;
                }
                if (isset(Html::VOID[$name])) {
                    if ($name === 'br' || $name === 'hr') {
                        $flow = $this->flush($flow, $pass);
                    } elseif (isset(Html::OBJECTS[$name])) {
                        $flow ??= new Flow($top->block);
                        $flow->add(-1, Html::OBJECT_CHAR, false, $top);
                    }
                    continue;
                }
                while (isset(Html::IMPLIED_END[$top->name][$name])) {
                    $open[$top->name]--;
                    $top = $top->parent;
                }
                $inline = isset(Html::INLINE[$name]);
                if (!$inline) {
                    $flow = $this->flush($flow, $pass);
                } elseif (isset(Html::OBJECTS[$name])) {
                    $flow ??= new Flow($top->block);
                    $flow->add(-1, Html::OBJECT_CHAR, false, $top);
                }

                $locale = $top->locale;
                $scope = $top->scope;
                $skip = $top->skip || isset(Html::SKIP[$name]) || isset($skipTags[$name]);
                if (!$skip) {
                    $option = $name === 'option';
                    $attributes = $this->attributes($token, strlen($name) + 1, $skipClasses, $option);
                    // An option with no value submits its text: changing the text would change what the form sends.
                    if ($option && !isset($attributes['value'])) {
                        $skip = true;
                    }
                    if ($attributes !== null) {
                        if (isset($attributes['data-sorts-lang'])) {
                            if (trim($attributes['data-sorts-lang']) !== '') {
                                $locale = $this->withVariant($pass, $this->locales->resolveOrFallback(trim($attributes['data-sorts-lang']), $options->locale));
                            }
                        } elseif (isset($attributes['lang'])) {
                            // lang="" means the language is unknown: the caller’s default, not the parent’s.
                            $locale = trim($attributes['lang']) === ''
                                ? $pass->locale
                                : $this->withVariant($pass, $this->locales->resolveOrFallback(trim($attributes['lang']), $options->locale));
                        }
                        if (isset($attributes['data-sorts']) && strtolower(trim($attributes['data-sorts'])) === 'off') {
                            $skip = true;
                        }
                        if ($skipClasses && isset($attributes['class'])
                            && array_intersect($skipClasses, preg_split('/\s+/', trim($attributes['class'])) ?: [])) {
                            $skip = true;
                        }
                        if (isset($attributes['data-sorts-rules'])) {
                            $handles = array_merge(
                                $scope === '' ? [] : explode(' ', $scope),
                                preg_split('/\s+/', strtolower(trim($attributes['data-sorts-rules']))) ?: []
                            );
                            $handles = array_values(array_unique(array_filter($handles, static fn (string $h): bool => $h !== '')));
                            sort($handles);
                            $scope = implode(' ', $handles);
                        }
                    }
                }
                // Only a class lookup reads a frame’s tag, so a tag without one is not kept.
                $raw = stripos($token, 'class') === false ? '' : $token;
                $top = new Frame($name, $raw, $top, $locale, $skip, $scope, $inline ? $top->block : $name);
                $open[$name] = ($open[$name] ?? 0) + 1;
                if ($top->depth > self::MAX_DEPTH) {
                    return $input;
                }
            }
            unset($m);
            if ($at < $length) {
                // The scan stopped at a comment, a CDATA section or a raw-text element.
                $at = $this->special($input, $at, $top, $flow, $skipTags, $pass);
                if ($at === null) {
                    return $input;
                }
            }
        }
        $flow = $this->flush($flow, $pass);
        return $pass->changed ? self::splice($input, $pass->edits) : $input;
    }

    /**
     * Step over the comment, CDATA section or raw-text element at $at, and fix the
     * text of a `<title>` (as text only: no rule that writes markup runs on it).
     * Returns where the next token starts, or null when a pattern failed.
     *
     * @param array<string, true> $skipTags
     */
    private function special(string $input, int $at, Frame $top, ?Flow &$flow, array $skipTags, Pass $pass): ?int
    {
        $length = strlen($input);
        if (substr_compare($input, '<!--', $at, 4) === 0) {
            if (($input[$at + 4] ?? '') === '>') {
                return $at + 5;
            }
            if (substr($input, $at + 4, 2) === '->') {
                return $at + 6;
            }
            $end = strpos($input, '-->', $at + 4);
            return $end === false ? $length : $end + 3;
        }
        if (substr_compare($input, '<![CDATA[', $at, 9, true) === 0) {
            $end = strpos($input, ']]>', $at + 9);
            return $end === false ? $length : $end + 3;
        }

        // A raw-text element: its body is not markup, and only <title> has text worth editing.
        if (!preg_match(self::RAW_TAG, $input, $open, 0, $at)) {
            $pass->error = 'reading a raw-text element failed: ' . (preg_last_error() ? preg_last_error_msg() : 'no tag at ' . $at);
            return null;
        }
        $name = strtolower(substr($open[0], 1, strcspn($open[0], self::NAME_END, 1)));
        $body = $at + strlen($open[0]);
        $close = $name === 'script' ? self::scriptEnd($input, $body) : self::rawEnd($input, $body, $name);
        if ($close === null) {
            $pass->error = "finding the end of a <{$name}> failed: " . preg_last_error_msg();
            return null;
        }
        $end = $close;
        if ($close < $length) {
            if (!preg_match(self::RAW_TAG, $input, $closer, 0, $close)) {
                $pass->error = "reading the end tag of a <{$name}> failed: " . preg_last_error_msg();
                return null;
            }
            $end += strlen($closer[0]);
        }
        if ($name === 'title' && !$top->skip && !isset($skipTags['title']) && $close > $body) {
            $flow = $this->flush($flow, $pass);
            $title = new Flow('title');
            $title->add($body, substr($input, $body, $close - $body), true, new Frame('title', '', $top, $top->locale, false, $top->scope, 'title', true));
            $this->flush($title, $pass);
        }
        return $end;
    }

    /** Where the end tag of a raw-text element other than script starts, or the end of the input. Null if the search failed. */
    private static function rawEnd(string $input, int $from, string $name): ?int
    {
        $found = preg_match('~</' . $name . '(?=[\s/>])~i', $input, $m, PREG_OFFSET_CAPTURE, $from);
        if ($found === false) {
            return null;
        }
        return $found ? $m[0][1] : strlen($input);
    }

    /**
     * Where the `</script>` that ends a script starts, or the end of the input, by the
     * HTML5 script-data states. After `<!--` the script is “escaped”; a `<script` there
     * makes it “double escaped”, where `</script>` only steps back to escaped; `-->`
     * returns to plain script data from either. So
     * `<script><!-- document.write("<script></script>"); --></script>` is one script.
     * Null if the search failed.
     */
    private static function scriptEnd(string $input, int $from): ?int
    {
        $patterns = [
            '~</script[\s/>]|<!--~i',                   // script data
            '~</script[\s/>]|<script[\s/>]|-->~i',      // escaped
            '~</script[\s/>]|-->~i',                    // double escaped
        ];
        $state = 0;
        while (true) {
            $found = preg_match($patterns[$state], $input, $m, PREG_OFFSET_CAPTURE, $from);
            if ($found === false) {
                return null;
            }
            if (!$found) {
                return strlen($input);
            }
            [$match, $at] = $m[0];
            if ($match === '-->') {
                $state = 0;
                $from = $at + 3;
            } elseif ($match === '<!--') {
                // The two dashes may also begin the -->: <!--> is opened and closed at once.
                $state = 1;
                $from = $at + 2;
            } elseif ($match[1] !== '/') {
                $state = 2;
                $from = $at + strlen($match);
            } elseif ($state === 2) {
                $state = 1;
                $from = $at + strlen($match);
            } else {
                return $at;
            }
        }
    }

    /**
     * The locale as this pass wants it: with the variant the options ask for (or, failing that, the
     * preset’s), if it has one by that name, and with the preset’s spacing inside guillemets.
     */
    private function withVariant(Pass $pass, LocaleInterface $locale): LocaleInterface
    {
        $name = $pass->options->variants[$locale->id()] ?? $pass->presetVariants[$locale->id()] ?? null;
        if ($name !== null && in_array($name, $locale->variants(), true)) {
            $locale = $pass->variantLocales[$locale->id() . '|' . $name] ??= $locale->withVariant($name);
        }
        if ($pass->options->preset !== '' && $locale instanceof DataLocale && Presets::spacesGuillemets($pass->options->preset, $locale)) {
            return $pass->presetLocales[spl_object_id($locale)] ??= $locale->withGuillemetSpace(Presets::GUILLEMET_SPACE);
        }
        return $locale;
    }

    /** Process the flow, if there is one. Returns null: the caller’s next flow starts empty. */
    private function flush(?Flow $flow, Pass $pass): null
    {
        if ($flow !== null) {
            $this->processFlow($flow, $pass);
        }
        return null;
    }

    /** The input with the pass’s edits put in. */
    private static function splice(string $input, array $edits): string
    {
        $out = '';
        $pos = 0;
        foreach ($edits as [$at, $length, $text]) {
            $out .= substr($input, $pos, $at - $pos) . $text;
            $pos = $at + $length;
        }
        return $out . substr($input, $pos);
    }

    /**
     * The attributes of an opening tag that the engine reads, lower-cased name
     * to raw value. Null when the tag has none of them (the common case). With
     * $all, every attribute the tag has.
     *
     * @param list<string> $skipClasses
     * @return array<string, string>|null
     */
    private function attributes(string $raw, int $offset, array $skipClasses, bool $all = false): ?array
    {
        if (!$all && strpos($raw, '=') === false) {
            return null;
        }
        if (!$all && stripos($raw, 'lang') === false && stripos($raw, 'data-sorts') === false
            && (!$skipClasses || stripos($raw, 'class') === false)) {
            return null;
        }
        $found = [];
        $pattern = '/\G[\s\/]*+([^\s\/>=][^\s\/>=]*+)(?:\s*+=\s*+(?:"([^"]*+)"|\'([^\']*+)\'|([^\s>]*+)))?/';
        while (preg_match($pattern, $raw, $a, PREG_UNMATCHED_AS_NULL, $offset)) {
            $offset += strlen($a[0]);
            $key = strtolower($a[1]);
            if (!isset($found[$key])) {
                $found[$key] = $a[2] ?? $a[3] ?? $a[4] ?? '';
            }
        }
        return $found ?: null;
    }

    private function processFlow(Flow $flow, Pass $pass): void
    {
        if ($pass->findings !== null) {
            foreach ($flow->texts as $i => $text) {
                if ($flow->editable[$i] && $text !== '') {
                    array_push($pass->findings, ...Findings::scan($text, $flow->offsets[$i], $pass->options->mode === Options::HTML));
                }
            }
            return;
        }
        foreach ($flow->texts as $i => $text) {
            $result = $text;
            $frame = $flow->frames[$i];
            if ($flow->editable[$i] && $text !== '') {
                $set = $this->ruleSet($pass, $frame);
                if ($set['rules'] && ($set['union'] === null || preg_match($set['union'], $text) !== 0)) {
                    $run = new Run($text);
                    $context = null;
                    $current = $text;
                    foreach ($set['rules'] as [$rule, $triggers]) {
                        if ($triggers !== null && preg_match($triggers, $current) === 0) {
                            continue;
                        }
                        $context ??= new Context($frame->locale, $pass->options, $flow, $i, $frame, $set['active']);
                        $rule->apply($run, $context);
                        $current = $run->text();
                    }
                    $result = $current;
                }
            }
            $flow->done[$i] = $result;
            if ($result !== $text) {
                $pass->edits[] = [$flow->offsets[$i], strlen($text), $result];
                $pass->changed = true;
            }
        }
    }

    /**
     * The rules that run for a locale and a rule scope, in order, each with a pattern
     * for the characters that call for it, and one for any of them. Built once per
     * pair per call.
     *
     * @return array{rules: list<array{RuleInterface, ?string}>, union: ?string, active: array<string, true>}
     */
    private function ruleSet(Pass $pass, Frame $frame): array
    {
        if ($frame->ruleSet !== null) {
            return $frame->ruleSet;
        }
        $key = $frame->locale->id() . '|' . $frame->scope . ($frame->text ? '|text' : '');
        if (isset($pass->ruleSets[$key])) {
            return $frame->ruleSet = $pass->ruleSets[$key];
        }
        $scoped = $frame->scope === '' ? [] : array_fill_keys(explode(' ', $frame->scope), true);
        $allowed = $pass->options->allowed === null ? null : array_fill_keys($pass->options->allowed, true);
        $textOnly = $pass->options->mode === Options::TEXT || $frame->text;
        $rules = [];
        $active = [];
        $union = '';
        foreach ($this->rules() as $rule) {
            $handle = $rule->handle();
            $on = ($pass->options->rules[$handle] ?? $pass->presetRules[$handle] ?? $rule->defaultEnabled()) || isset($pass->named[$handle]) || isset($scoped[$handle]);
            // Nothing turns on a rule the options do not allow: not the settings, a scope or the call.
            if (!$on || ($allowed !== null && !isset($allowed[$handle])) || !$rule->supportsLocale($frame->locale)
                || ($textOnly && $rule->emitsMarkup())) {
                continue;
            }
            $triggers = $rule->triggers();
            if ($triggers === '') {
                $triggers = null;
            }
            $rules[] = [$rule, $triggers === null ? null : self::anyByte($triggers)];
            $active[$handle] = true;
            if ($union !== null) {
                $union = $triggers === null ? null : $union . $triggers;
            }
        }
        if ($union !== null && $union !== '') {
            $union = self::anyByte($union);
        }
        return $frame->ruleSet = $pass->ruleSets[$key] = ['rules' => $rules, 'union' => $union === '' ? null : $union, 'active' => $active];
    }

    /**
     * A pattern that matches any one of the bytes in $chars: the test strpbrk() makes,
     * which on PHP 8.2 walks the whole list for every byte of the text and copies the
     * rest of the text when it finds one. A byte class is several times faster there.
     */
    private static function anyByte(string $chars): string
    {
        return '/[' . implode('', array_map(static fn (int $byte): string => sprintf('\\x%02X', $byte), array_keys(count_chars($chars, 1)))) . ']/';
    }
}
