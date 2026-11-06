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
    /** Every built-in rule with the state SPEC §3 gives it out of the box. */
    public const RULE_DEFAULTS = [
        'quotes' => true,
        'apostrophes' => true,
        'primes' => true,
        'dashes' => true,
        'ellipsis' => true,
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

    /** @var array<string, bool> Pro. Rule handle => on or off. */
    public array $rules = self::RULE_DEFAULTS;

    /** @var array<string, string> Pro. Locale id => the name of the variant to use for it. */
    public array $variants = [];

    /**
     * Pro. Set every quotation by how deeply it is nested (the locale’s primary pair outermost, the
     * secondary pair inside it), whichever mark was typed. Off: the outermost quotation gets the pair its
     * mark asks for (`"` primary, `'` secondary), as CKEditor does while typing, and one inside another
     * the other pair. Lite is always off.
     */
    public bool $normalizeQuotes = false;

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
        foreach (['ckeditorAsYouType', 'pagePass', 'processHead', 'normalizeQuotes', 'fixOnSave', 'fixTitles', 'fixPlainText', 'fixCkeditor', 'fixOnSaveBulk'] as $name) {
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
     * The extra tag names with the built-in `title` raw-text element added unless
     * the head is to be fixed too.
     *
     * @return list<string>
     */
    public function pageSkipTags(): array
    {
        return array_values(array_unique(array_merge($this->skipTags, $this->processHead ? [] : ['title'])));
    }

    /** @return array<string, bool> */
    private static function coerceRules(mixed $value): array
    {
        $rules = self::RULE_DEFAULTS;
        if (!is_array($value)) {
            return $rules;
        }
        foreach ($rules as $handle => $default) {
            if (array_key_exists($handle, $value)) {
                $rules[$handle] = self::coerceBool($value[$handle], $default);
            }
        }
        return $rules;
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
