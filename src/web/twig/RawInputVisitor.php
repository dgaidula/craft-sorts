<?php

namespace iceboxind\sorts\web\twig;

use Twig\Environment;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;
use Twig\TwigFilter;

/**
 * Lets `{{ html|raw|sorts }}` mean what it says.
 *
 * `|raw` returns the plain string it was given and only tells Twig’s compiler that the string
 * is safe, so at run time the `sorts` filter cannot tell it from any other string and would
 * escape it. This visitor sees the compiler’s side: when the filter’s input is directly a
 * `|raw` call it swaps in a declaration of the same filter that reads its input as HTML. It
 * touches nothing else, so a plain string, a variable or a literal is still escaped.
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
            ($input = $node->getNode('node')) instanceof FilterExpression &&
            $input->getAttribute('name') === 'raw'
        ) {
            $node->setAttribute('twig_callable', $this->trustedFilter);
        }
        return $node;
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
