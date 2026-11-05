<?php

namespace iceboxind\sorts\web\twig;

use Craft;
use iceboxind\sorts\engine\Options;
use iceboxind\sorts\Plugin;
use InvalidArgumentException;
use Throwable;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

/**
 * The `sorts` Twig filter.
 *
 * ```twig
 * {{ entry.body|sorts }}                          {# HTML in, HTML out #}
 * {{ entry.title|sorts }}                         {# a plain string is escaped first, as with any HTML output #}
 * {{ html|raw|sorts }}                            {# you vouched for it: read as HTML #}
 * {{ entry.title|sorts('text') }}                 {# plain text, for attributes and JSON; Twig escapes it as usual #}
 * {{ row.quantity|sorts(rules: ['my-rule']) }}    {# a registered rule, by name #}
 * {% apply sorts %}…{% endapply %}
 * ```
 *
 * Security: in HTML mode the filter never turns unescaped input into safe output. At run
 * time it trusts only what Twig already treats as HTML: a `Twig\Markup` value (a rich-text
 * field, the body of `{% apply %}`) and the output of `|raw` ({@see RawInputVisitor}). Any
 * other value, a plain string or variable or a literal in the template, is HTML-escaped first,
 * and the result is returned as `Markup` so it is not escaped twice. `{{ '<script>'|sorts }}`
 * prints `&lt;script&gt;`. Text mode returns a plain string, which Twig escapes when it is
 * printed. The filter is deliberately not declared `is_safe`, which would mark text mode’s
 * output safe at compile time; it returns `Markup` instead, which is safe at run time only.
 */
class SortsExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [new TwigFilter('sorts', [$this, 'sortsFilter'])];
    }

    public function getNodeVisitors(): array
    {
        return [new RawInputVisitor(new TwigFilter('sorts', [$this, 'sortsTrustedFilter']))];
    }

    /**
     * @param list<string>|null $rules handles of rules to run by name (a registered rule’s `<handle>`)
     * @throws InvalidArgumentException for a mode that is not `html` or `text`, and, in Pro, a rule that is not registered
     */
    public function sortsFilter(mixed $value, string $mode = Options::HTML, ?array $rules = null): mixed
    {
        return $this->run($value, $mode, $rules, $value instanceof Markup);
    }

    /**
     * The filter as {@see RawInputVisitor} calls it when its input is `|raw`: the template’s
     * author has said the value is HTML, so it is read as HTML.
     *
     * @param list<string>|null $rules
     */
    public function sortsTrustedFilter(mixed $value, string $mode = Options::HTML, ?array $rules = null): mixed
    {
        return $this->run($value, $mode, $rules, true);
    }

    /**
     * @param list<string>|null $rules
     */
    private function run(mixed $value, string $mode, ?array $rules, bool $trusted): mixed
    {
        if ($mode !== Options::HTML && $mode !== Options::TEXT) {
            throw new InvalidArgumentException("The sorts filter’s mode must be “html” or “text”, not “{$mode}”.");
        }
        if ($value === null || $value === '' || !(is_scalar($value) || $value instanceof \Stringable)) {
            return $value;
        }

        $string = (string) $value;
        if ($mode === Options::HTML && !$trusted) {
            $string = htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        $result = $string;
        try {
            $result = Plugin::getInstance()->engine->apply($string, $rules ?? [], null, $mode);
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (Throwable $e) {
            Craft::error('Sorts left text as it was: ' . get_class($e) . ': ' . $e->getMessage(), __METHOD__);
        }

        return $mode === Options::HTML ? new Markup($result, 'UTF-8') : $result;
    }
}
