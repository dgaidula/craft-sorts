<?php

namespace iceboxind\sorts\engine;

use iceboxind\sorts\engine\rules\ApostrophesRule;
use iceboxind\sorts\engine\rules\DashesRule;
use iceboxind\sorts\engine\rules\EllipsisRule;
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
     * Markup and text, in one pass. Groups: 1 text, 2 comment, 3 CDATA, 4 bogus
     * comment or doctype, 5–8 a raw-text element (open tag, name, body, close
     * tag), 9–10 an ordinary tag (the slash of a closing tag, the name).
     * Attribute values may hold “>”; a quote that never closes runs to the end,
     * as it does in a browser.
     */
    private const TOKENS = '~
        ([^<]++|<(?![a-zA-Z/!?]))                                 # 1 text
      | (<!--(?:>|->|.*?(?:-->|\z)))                              # 2 comment
      | (<!\[CDATA\[.*?(?:\]\]>|\z))                              # 3 CDATA
      | (<[!?][^>]*+(?:>|\z)|</(?![a-zA-Z])[^>]*+(?:>|\z))        # 4 doctype, bogus comment
      | (<(script|style|xmp|iframe|noembed|noframes|textarea|title)(?=[\s/>])
            (?:[^>"\'=]++|=\s*+(?:"[^"]*+(?:"|\z)|\'[^\']*+(?:\'|\z))?|["\'])*+(?:>|\z))   # 5, 6
        (.*?)                                                     # 7
        (</\6(?=[\s/>])(?:[^>"\'=]++|=\s*+(?:"[^"]*+(?:"|\z)|\'[^\']*+(?:\'|\z))?|["\'])*+(?:>|\z)|\z)  # 8
      | <(/)?([a-zA-Z][^\s/>]*)
            (?:[^>"\'=]++|=\s*+(?:"[^"]*+(?:"|\z)|\'[^\']*+(?:\'|\z))?|["\'])*+(?:>|\z)   # 9, 10
    ~xsiD';

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

    public function __construct(private Options $options = new Options(), ?LocaleRegistry $locales = null)
    {
        $this->locales = $locales ?? new LocaleRegistry();
        foreach ([
            new SymbolsRule(), new EllipsisRule(), new DashesRule(), new FractionsRule(), new RangesRule(),
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
        // A string that is not UTF-8 is not ours to fix: return it as it came.
        if ($input === '' || preg_match('//u', $input) !== 1) {
            return $input;
        }
        $pass = new Pass($options, array_fill_keys($rules, true), $this->locales->resolveOrFallback($options->locale));
        $pass->locale = $this->withVariant($pass, $pass->locale);
        return $options->mode === Options::TEXT ? $this->applyText($input, $pass) : $this->applyHtml($input, $pass);
    }

    private function applyText(string $input, Pass $pass): string
    {
        $root = new Frame('', '', null, $pass->locale, false, '', null);
        $parts = preg_split('/(\R[ \t]*\R\s*)/u', $input, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return $input;
        }
        foreach ($parts as $i => $text) {
            if ($i % 2 === 0 && $text !== '') {
                $flow = new Flow(null);
                $flow->segments[] = [$i, $text, true, $root, '', ''];
                $this->processFlow($flow, $parts, $pass);
            }
        }
        return $pass->changed ? implode('', $parts) : $input;
    }

    private function applyHtml(string $input, Pass $pass): string
    {
        $count = preg_match_all(self::TOKENS, $input, $m, PREG_PATTERN_ORDER);
        // Every byte must belong to a token, or the pass would not be lossless.
        if (!$count || strlen(implode('', $m[0])) !== strlen($input)) {
            return $input;
        }
        $parts = $m[0];
        $options = $pass->options;
        $skipTags = array_fill_keys(array_map('strtolower', $options->skipTags), true);
        $skipClasses = $options->skipClasses;
        $top = new Frame('', '', null, $pass->locale, false, '', null);
        $flow = null;

        for ($i = 0; $i < $count; $i++) {
            $text = $m[1][$i];
            if ($text !== '') {
                if ($flow === null) {
                    if (ctype_space($text)) {
                        continue;
                    }
                    $flow = new Flow($top->block);
                }
                $flow->segments[] = [$i, $text, !$top->skip, $top, '', ''];
                continue;
            }

            $name = $m[10][$i];
            if ($name !== '') {
                $name = strtolower($name);
                if ($m[9][$i] === '/') {
                    if (!isset(Html::INLINE[$name])) {
                        $flow = $this->flush($flow, $parts, $pass);
                    }
                    for ($f = $top; $f->parent !== null; $f = $f->parent) {
                        if ($f->name === $name) {
                            $top = $f->parent;
                            break;
                        }
                    }
                    continue;
                }
                if (isset(Html::VOID[$name])) {
                    if ($name === 'br' || $name === 'hr') {
                        $flow = $this->flush($flow, $parts, $pass);
                    } elseif (isset(Html::OBJECTS[$name])) {
                        $flow ??= new Flow($top->block);
                        $flow->segments[] = [$i, Html::OBJECT_CHAR, false, $top, '', ''];
                    }
                    continue;
                }
                while (isset(Html::IMPLIED_END[$top->name][$name])) {
                    $top = $top->parent;
                }
                $inline = isset(Html::INLINE[$name]);
                if (!$inline) {
                    $flow = $this->flush($flow, $parts, $pass);
                } elseif (isset(Html::OBJECTS[$name])) {
                    $flow ??= new Flow($top->block);
                    $flow->segments[] = [$i, Html::OBJECT_CHAR, false, $top, '', ''];
                }

                $raw = $m[0][$i];
                $locale = $top->locale;
                $scope = $top->scope;
                $skip = $top->skip || isset(Html::SKIP[$name]) || isset($skipTags[$name]);
                if (!$skip) {
                    $attributes = $this->attributes($raw, strlen($name) + 1, $skipClasses);
                    if ($attributes !== null) {
                        $lang = $attributes['data-sorts-lang'] ?? $attributes['lang'] ?? '';
                        if (trim($lang) !== '') {
                            $locale = $this->withVariant($pass, $this->locales->resolveOrFallback(trim($lang), $options->locale));
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
                $top = new Frame($name, $raw, $top, $locale, $skip, $scope, $inline ? $top->block : $name);
                if ($top->depth > self::MAX_DEPTH) {
                    return $input;
                }
                continue;
            }

            if ($m[5][$i] !== '') {
                // A raw-text element is one token. Only <title> has text worth editing.
                $name = strtolower($m[6][$i]);
                if ($name === 'title' && !$top->skip && !isset($skipTags['title']) && $m[7][$i] !== '') {
                    $flow = $this->flush($flow, $parts, $pass);
                    $flow = new Flow('title');
                    $flow->segments[] = [$i, $m[7][$i], true, $top, $m[5][$i], $m[8][$i]];
                    $flow = $this->flush($flow, $parts, $pass);
                }
            }
            // Comments, CDATA, doctypes and the rest are markup: nothing to do.
        }
        $flow = $this->flush($flow, $parts, $pass);
        return $pass->changed ? implode('', $parts) : $input;
    }

    /** Process the flow, if there is one. Returns null: the caller’s next flow starts empty. */
    /** The locale with the variant the options ask for, if it has one by that name. */
    private function withVariant(Pass $pass, LocaleInterface $locale): LocaleInterface
    {
        $name = $pass->options->variants[$locale->id()] ?? null;
        if ($name === null || !in_array($name, $locale->variants(), true)) {
            return $locale;
        }
        return $pass->variantLocales[$locale->id() . '|' . $name] ??= $locale->withVariant($name);
    }

    private function flush(?Flow $flow, array &$parts, Pass $pass): null
    {
        if ($flow !== null) {
            $this->processFlow($flow, $parts, $pass);
        }
        return null;
    }

    /**
     * The attributes of an opening tag that the engine reads, lower-cased name
     * to raw value. Null when the tag has none of them (the common case).
     *
     * @param list<string> $skipClasses
     * @return array<string, string>|null
     */
    private function attributes(string $raw, int $offset, array $skipClasses): ?array
    {
        if (strpos($raw, '=') === false) {
            return null;
        }
        if (stripos($raw, 'lang') === false && stripos($raw, 'data-sorts') === false
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

    private function processFlow(Flow $flow, array &$parts, Pass $pass): void
    {
        foreach ($flow->segments as $i => [$token, $text, $editable, $frame, $prefix, $suffix]) {
            $result = $text;
            if ($editable && $text !== '') {
                $set = $this->ruleSet($pass, $frame);
                if ($set['rules'] && ($set['union'] === null || strpbrk($text, $set['union']) !== false)) {
                    $run = new Run($text);
                    $context = null;
                    $current = $text;
                    foreach ($set['rules'] as [$rule, $triggers]) {
                        if ($triggers !== null && strpbrk($current, $triggers) === false) {
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
                $parts[$token] = $prefix . $result . $suffix;
                $pass->changed = true;
            }
        }
    }

    /**
     * The rules that run for a locale and a rule scope, in order, with the
     * characters that call for any of them. Built once per pair per call.
     *
     * @return array{rules: list<array{RuleInterface, ?string}>, union: ?string, active: array<string, true>}
     */
    private function ruleSet(Pass $pass, Frame $frame): array
    {
        if ($frame->ruleSet !== null) {
            return $frame->ruleSet;
        }
        $key = $frame->locale->id() . '|' . $frame->scope;
        if (isset($pass->ruleSets[$key])) {
            return $frame->ruleSet = $pass->ruleSets[$key];
        }
        $scoped = $frame->scope === '' ? [] : array_fill_keys(explode(' ', $frame->scope), true);
        $rules = [];
        $active = [];
        $union = '';
        foreach ($this->rules() as $rule) {
            $handle = $rule->handle();
            $on = ($pass->options->rules[$handle] ?? $rule->defaultEnabled()) || isset($pass->named[$handle]) || isset($scoped[$handle]);
            if (!$on || !$rule->supportsLocale($frame->locale)
                || ($pass->options->mode === Options::TEXT && $rule->emitsMarkup())) {
                continue;
            }
            $triggers = $rule->triggers();
            if ($triggers === '') {
                $triggers = null;
            }
            $rules[] = [$rule, $triggers];
            $active[$handle] = true;
            if ($union !== null) {
                $union = $triggers === null ? null : $union . $triggers;
            }
        }
        if ($union !== null && $union !== '') {
            $union = count_chars($union, 3);
        }
        return $frame->ruleSet = $pass->ruleSets[$key] = ['rules' => $rules, 'union' => $union === '' ? null : $union, 'active' => $active];
    }
}
