<?php

namespace iceboxind\sorts\engine;

/** Element tables the tokenizer walk reads. Names are lower case. */
final class Html
{
    /** Inline elements the engine looks through when it decides a quote’s direction. */
    public const TRANSPARENT = [
        'a' => 1, 'i' => 1, 'em' => 1, 'b' => 1, 'strong' => 1, 'span' => 1, 'sup' => 1, 'sub' => 1,
        'small' => 1, 'mark' => 1, 'abbr' => 1, 'cite' => 1, 'q' => 1, 'u' => 1, 's' => 1,
        'del' => 1, 'ins' => 1, 'time' => 1, 'dfn' => 1, 'data' => 1, 'bdi' => 1, 'bdo' => 1,
        'font' => 1, 'label' => 1, 'big' => 1, 'nobr' => 1, 'wbr' => 1,
    ];

    /** Every element the walk treats as inline: transparent ones, code and objects. */
    public const INLINE = self::TRANSPARENT + self::CODE + self::OBJECTS;

    /** Inline elements whose text is code: read as context, never edited. */
    public const CODE = ['code' => 1, 'kbd' => 1, 'samp' => 1, 'var' => 1];

    /** Elements whose whole subtree is left alone. */
    public const SKIP = [
        'pre' => 1, 'code' => 1, 'kbd' => 1, 'samp' => 1, 'var' => 1, 'textarea' => 1, 'math' => 1,
        'svg' => 1, 'template' => 1, 'noscript' => 1,
    ];

    /** Inline elements that stand in the text as one unseen object. */
    public const OBJECTS = [
        'svg' => 1, 'math' => 1, 'video' => 1, 'audio' => 1, 'canvas' => 1, 'object' => 1, 'select' => 1,
        'img' => 1, 'input' => 1, 'embed' => 1,
    ];

    public const VOID = [
        'area' => 1, 'base' => 1, 'br' => 1, 'col' => 1, 'embed' => 1, 'hr' => 1, 'img' => 1, 'input' => 1,
        'link' => 1, 'meta' => 1, 'param' => 1, 'source' => 1, 'track' => 1, 'wbr' => 1, 'keygen' => 1,
    ];

    /** Open elements that a new element implicitly closes: [open element => [new elements]]. */
    public const IMPLIED_END = [
        'p' => [
            'address' => 1, 'article' => 1, 'aside' => 1, 'blockquote' => 1, 'details' => 1, 'div' => 1,
            'dl' => 1, 'fieldset' => 1, 'figcaption' => 1, 'figure' => 1, 'footer' => 1, 'form' => 1,
            'h1' => 1, 'h2' => 1, 'h3' => 1, 'h4' => 1, 'h5' => 1, 'h6' => 1, 'header' => 1, 'hgroup' => 1,
            'hr' => 1, 'main' => 1, 'menu' => 1, 'nav' => 1, 'ol' => 1, 'p' => 1, 'pre' => 1,
            'section' => 1, 'table' => 1, 'ul' => 1,
        ],
        'li' => ['li' => 1],
        'dt' => ['dt' => 1, 'dd' => 1],
        'dd' => ['dt' => 1, 'dd' => 1],
        'option' => ['option' => 1, 'optgroup' => 1],
        'tr' => ['tr' => 1, 'tbody' => 1, 'thead' => 1, 'tfoot' => 1],
        'td' => ['td' => 1, 'th' => 1, 'tr' => 1, 'tbody' => 1, 'thead' => 1, 'tfoot' => 1],
        'th' => ['td' => 1, 'th' => 1, 'tr' => 1, 'tbody' => 1, 'thead' => 1, 'tfoot' => 1],
        'thead' => ['tbody' => 1, 'tfoot' => 1],
        'tbody' => ['tbody' => 1, 'tfoot' => 1],
    ];

    /** The stand-in character a flow holds for an object (U+FFFC). */
    public const OBJECT_CHAR = "\u{FFFC}";
}
