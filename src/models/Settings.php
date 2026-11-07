<?php

namespace iceboxind\sorts\models;

use Craft;
use craft\base\Model;
use iceboxind\sorts\engine\LocaleRegistry;

/**
 * Global plugin settings (PROJECT CONFIG — dev-owned, locked on prod when
 * `allowAdminChanges` is false; that's intentional, these are deploy-time
 * values). Editor-facing content belongs in a plugin-owned service/table
 * instead.
 *
 * Everything but `pluginName` and `ckeditorAsYouType` is a Pro setting: Lite
 * stores them (a downgrade keeps what Pro configured) and never reads them.
 *
 * Values can arrive from the control panel, project config or `config/sorts.php`
 * in odd shapes (an empty multi-select posts `''`, a number arrives as a
 * string), so {@see setAttributes()} coerces each one to its type and
 * {@see rules()} then validates what is left.
 */
class Settings extends Model
{
    /** A rule switched on, whatever the preset says (D864). */
    public const RULE_ON = 'on';

    /** A rule switched off, whatever the preset says: a site's veto of a preset rule (D864). */
    public const RULE_OFF = 'off';

    /** A rule left to the preset, and with no preset to its default (D864). The default state, stored as no entry at all. */
    public const RULE_FOLLOW = 'follow';

    /**
     * Every built-in rule with the state SPEC §3 gives it out of the box. This is what a rule does when it
     * follows (no preset, nothing switched); the three preset-only rules (`typed-dashes`, `dialogue-dash`,
     * `ellipsis-spacing`) are off until the preset or a site turns them on.
     */
    public const RULE_DEFAULTS = [
        'quotes' => true,
        'apostrophes' => true,
        'primes' => true,
        'dashes' => true,
        'typed-dashes' => false,
        'dialogue-dash' => false,
        'ellipsis' => true,
        'ellipsis-spacing' => false,
        'symbols' => true,
        'ranges' => false,
        'multiplication' => false,
        'fractions' => false,
        'ordinals' => false,
        'widows' => true,
        'nbsp' => true,
        'locale-spacing' => true,
        'hook-amp' => false,
        'hook-caps' => false,
        'hook-quotes' => false,
    ];

    /** Longest value any list item or pattern may have. */
    private const MAX_ITEM_LENGTH = 200;

    /** Most items a list setting may hold. */
    private const MAX_ITEMS = 200;

    /**
     * Overrides the plugin's name in the CP — the sidebar nav item, the
     * Plugins screen, and the settings breadcrumb. Blank = "Sorts".
     *
     * Set it in the site's `config/sorts.php` so it is version-controlled
     * and survives with `allowAdminChanges` off.
     */
    public string $pluginName = '';

    /** Lite. Load CKEditor’s TextTransformation so quotes convert as people type. */
    public bool $ckeditorAsYouType = true;

    /** Pro. The automatic whole-page pass. */
    public bool $pagePass = true;

    /**
     * @var array<string, string> Pro. Rule handle => {@see RULE_ON} or {@see RULE_OFF}. A rule with no entry follows the
     * preset ({@see RULE_FOLLOW}), so a site that has switched nothing gets exactly what the preset, or the rule's default, gives.
     */
    public array $rules = [];

    /** @var array<string, string> Pro. Locale id => the name of the variant to use for it. */
    public array $variants = [];

    /**
     * Pro. Set every quotation by how deeply it is nested (the locale’s primary pair outermost, the
     * secondary pair inside it), whichever mark was typed. Off: the outermost quotation gets the pair its
     * mark asks for (`"` primary, `'` secondary), as CKEditor does while typing, and one inside another
     * the other pair. Lite is always off.
     */
    public bool $normalizeQuotes = false;

    /**
     * Pro. Fine typography after Robert Bringhurst, *The Elements of Typographic Style*, 3rd ed., chapter 5
     * (the engine’s `bringhurst` preset, D817): a coherent set of choices that sits above the rule switches.
     * A switch moved off its default is the site’s own word and wins. Lite is always off.
     */
    public bool $fineTypography = false;

    /** Pro. The fewest words a heading needs before the widows rule joins its last two. */
    public int $widowsMinWords = 3;

    /** @var list<string> Pro. Extra tag names whose contents are left alone. */
    public array $skipTags = [];

    /** @var list<string> Pro. Extra class names whose elements are left alone. */
    public array $skipClasses = [];

    /** @var list<string> Pro. Request paths the page pass skips; `*` matches any run of characters. */
    public array $skipUris = [];

    /** Pro. Also fix the text of `<title>` and the prose `<meta>` descriptions. */
    public bool $processHead = false;

    /** Pro. The largest page, in bytes, the page pass sets; a larger one is sent as rendered, with a warning in the log. */
    public int $maxPageBytes = 1_000_000;

    /** Pro. Fix titles and field values as elements are saved. */
    public bool $fixOnSave = false;

    /** Pro. Fix-on-save and the audit cover element titles. */
    public bool $fixTitles = true;

    /** Pro. Fix-on-save and the audit cover Plain Text fields. */
    public bool $fixPlainText = true;

    /** Pro. Fix-on-save and the audit cover CKEditor fields. */
    public bool $fixCkeditor = true;

    /** Pro. Fix-on-save also runs for resaves and while migrations run. */
    public bool $fixOnSaveBulk = false;

    /**
     * Coerce each value to its type before it reaches a typed property.
     *
     * @inheritdoc
     */
    public function setAttributes($values, $safeOnly = true): void
    {
        foreach (['rules', 'variants'] as $name) {
            if (array_key_exists($name, $values)) {
                $values[$name] = $name === 'rules' ? self::coerceRules($values[$name]) : self::coerceMap($values[$name]);
            }
        }
        foreach (['skipTags', 'skipClasses', 'skipUris'] as $name) {
            if (array_key_exists($name, $values)) {
                $values[$name] = self::coerceList($values[$name]);
            }
        }
        foreach (['widowsMinWords', 'maxPageBytes'] as $name) {
            if (array_key_exists($name, $values)) {
                $values[$name] = is_numeric($values[$name]) ? (int) $values[$name] : 0;
            }
        }
        foreach (['ckeditorAsYouType', 'pagePass', 'processHead', 'normalizeQuotes', 'fineTypography', 'fixOnSave', 'fixTitles', 'fixPlainText', 'fixCkeditor', 'fixOnSaveBulk'] as $name) {
            if (array_key_exists($name, $values)) {
                $values[$name] = self::coerceBool($values[$name], $this->$name);
            }
        }
        if (array_key_exists('pluginName', $values)) {
            $values['pluginName'] = is_string($values['pluginName']) ? trim($values['pluginName']) : '';
        }
        parent::setAttributes($values, $safeOnly);
    }

    public function rules(): array
    {
        return [
            [['pluginName'], 'string', 'max' => 100],
            [['widowsMinWords'], 'integer', 'min' => 2, 'max' => 20],
            [['maxPageBytes'], 'integer', 'min' => 1],
            [['skipTags'], 'validateList', 'params' => ['pattern' => '/^[a-z][a-z0-9-]*$/', 'hint' => 'lower-case letters, digits and hyphens, starting with a letter']],
            [['skipClasses'], 'validateList', 'params' => ['pattern' => '/^[^\s"\'<>=`]+$/', 'hint' => 'a class name with no spaces, quotes, angle brackets or equals signs']],
            [['skipUris'], 'validateList', 'params' => ['pattern' => '/^[^\s\x00-\x1f]+$/', 'hint' => 'a path with no spaces; use * to match any run of characters']],
            [['variants'], 'validateVariants'],
        ];
    }

    /**
     * Put each numeric setting in $values that does not validate back to its default, with
     * one warning naming the value: settings loaded from project config or `config/sorts.php`
     * are never validated, and a non-number reads as 0 ({@see setAttributes()}).
     *
     * @param array<string, mixed> $values the settings as they arrived
     */
    public function defaultInvalidNumbers(array $values): void
    {
        $defaults = new self();
        foreach (['widowsMinWords', 'maxPageBytes'] as $name) {
            if (array_key_exists($name, $values) && !$this->validate([$name])) {
                Craft::warning(sprintf('Sorts ignored the %s setting %s (%s) and uses the default, %s.', $name, json_encode($values[$name]), implode(' ', $this->getErrors($name)), $defaults->$name), __METHOD__);
                $this->$name = $defaults->$name;
                $this->clearErrors($name);
            }
        }
    }

    /**
     * @param array{pattern: string, hint: string} $params
     */
    public function validateList(string $attribute, ?array $params = null): void
    {
        $items = $this->$attribute;
        if (count($items) > self::MAX_ITEMS) {
            $this->addError($attribute, 'Use no more than ' . self::MAX_ITEMS . ' entries.');
            return;
        }
        foreach ($items as $item) {
            if (strlen($item) > self::MAX_ITEM_LENGTH || !preg_match($params['pattern'] ?? '/./', $item)) {
                $this->addError($attribute, '“' . $item . '” is not valid: ' . ($params['hint'] ?? 'check the value') . '.');
            }
        }
    }

    public function validateVariants(string $attribute): void
    {
        $locales = new LocaleRegistry();
        foreach ($this->variants as $id => $name) {
            $locale = $locales->resolve((string) $id);
            if ($locale === null || !in_array($name, $locale->variants(), true)) {
                $this->addError($attribute, '“' . $name . '” is not a variant of “' . $id . '”.');
            }
        }
    }

    /**
     * One rule's state: {@see RULE_ON}, {@see RULE_OFF} or {@see RULE_FOLLOW}.
     */
    public function ruleState(string $handle): string
    {
        return $this->rules[$handle] ?? self::RULE_FOLLOW;
    }

    /**
     * The rules the site has switched on or off, as the engine's `Options::$rules` takes them. A rule that follows is
     * left out, which is what lets the preset (and then the rule's own default) answer for it.
     *
     * @return array<string, bool>
     */
    public function ruleSwitches(): array
    {
        return array_map(static fn (string $state): bool => $state === self::RULE_ON, $this->rules);
    }

    /**
     * The extra tag names with the built-in `title` raw-text element added unless
     * the head is to be fixed too.
     *
     * @return list<string>
     */
    public function pageSkipTags(): array
    {
        return array_values(array_unique(array_merge($this->skipTags, $this->processHead ? [] : ['title'])));
    }

    /**
     * Read the rule map from the control panel, project config or `config/sorts.php`, into the entries that are
     * on or off. A rule not named, named with `follow`, or named with something unreadable follows the preset.
     *
     * @return array<string, string>
     */
    private static function coerceRules(mixed $value): array
    {
        $rules = [];
        if (!is_array($value)) {
            return $rules;
        }
        foreach (self::RULE_DEFAULTS as $handle => $default) {
            if (array_key_exists($handle, $value)) {
                $state = self::coerceRuleState($value[$handle], $default);
                if ($state !== self::RULE_FOLLOW) {
                    $rules[$handle] = $state;
                }
            }
        }
        return $rules;
    }

    /**
     * The words `on`, `off` and `follow` are the three states. Anything else is a plain on or off as the
     * settings stored them before there were three (a boolean, `1`, `''`, `true`), when a switch at its rule's
     * default was no more than the default and only one moved off it was the site's own word: so it reads as the
     * site's word on or off when it differs from the default, and as `follow` when it does not. A site that
     * stored its switches then behaves exactly as it did.
     */
    private static function coerceRuleState(mixed $value, bool $default): string
    {
        if (is_string($value)) {
            $word = strtolower(trim($value));
            if (in_array($word, [self::RULE_ON, self::RULE_OFF, self::RULE_FOLLOW], true)) {
                return $word;
            }
        }
        $on = self::coerceBool($value, $default);
        return $on === $default ? self::RULE_FOLLOW : ($on ? self::RULE_ON : self::RULE_OFF);
    }

    /** @return array<string, string> */
    private static function coerceMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $map = [];
        foreach ($value as $key => $item) {
            if (is_string($item) && trim($item) !== '' && is_string($key)) {
                $map[trim($key)] = trim($item);
            }
        }
        return $map;
    }

    /** @return list<string> */
    private static function coerceList(mixed $value): array
    {
        if (is_string($value)) {
            $value = preg_split('/[\r\n,]+/', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }
        $list = [];
        foreach ($value as $item) {
            if (is_string($item) || is_int($item)) {
                $item = trim((string) $item);
                if ($item !== '') {
                    $list[$item] = $item;
                }
            }
        }
        return array_values($list);
    }

    private static function coerceBool(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_string($value)) {
            return match (strtolower(trim((string) $value))) {
                '1', 'true', 'on', 'yes' => true,
                '0', 'false', 'off', 'no', '' => false,
                default => $default,
            };
        }
        return $default;
    }
}
