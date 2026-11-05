<?php

namespace iceboxind\sorts\web\twig;

use Twig\Environment;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TwigFilter;

/**
 * Lets `{{ html|raw|sorts }}` mean what it says, and reads the output of any filter Twig
 * already treats as safe HTML (`nl2br`, `e`/`escape`, `md`/`markdown`) as HTML.
 *
 * Such a filter returns a plain string and only tells Twig’s compiler that the string is safe
 * HTML, so at run time the `sorts` filter cannot tell it from any other string and would
 * escape it a second time. This visitor sees the compiler’s side: when the filter’s input is
 * directly a call to a filter that declares itself safe for HTML (`is_safe` or
 * `is_safe_callback` giving `html` or `all`, as Twig’s escaper reads it), it swaps in a
 * declaration of the same filter that reads its input as HTML. `|e('js')` is safe for
 * JavaScript, not HTML, and stays untrusted, as do `|upper` and every other filter that is
 * not declared safe. It touches nothing else, so a plain string, a variable or a literal is
 * still escaped.
 *
 * Twig compiles templates once and reuses them; it notices a change to an extension’s own
 * class file but not to this one, so a site that updates Sorts should clear its compiled
 * templates (README, Known limits).
 */
final class RawInputVisitor implements NodeVisitorInterface
{
    public function __construct(private readonly TwigFilter $trustedFilter)
    {
    }

    public function enterNode(Node $node, Environment $env): Node
    {
        if (
            $node instanceof FilterExpression &&
            $node->getAttribute('name') === 'sorts' &&
            self::isSafeHtml($node->getNode('node'))
        ) {
            $node->setAttribute('twig_callable', $this->trustedFilter);
        }
        return $node;
    }

    /** Whether a node is a filter call whose output Twig treats as safe HTML. */
    private static function isSafeHtml(Node $input): bool
    {
        if (!$input instanceof FilterExpression) {
            return false;
        }
        if ($input->getAttribute('name') === 'raw') {
            return true;
        }
        $filter = $input->hasAttribute('twig_callable') ? $input->getAttribute('twig_callable') : null;
        if (!$filter instanceof TwigFilter) {
            return false;
        }
        $safe = $filter->getSafe($input->getNode('arguments')) ?? [];
        return in_array('html', $safe, true) || in_array('all', $safe, true);
    }

    public function leaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function getPriority(): int
    {
        return 0;
    }
}
