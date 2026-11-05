<?php

namespace iceboxind\sorts\gql\directives;

use craft\base\ElementInterface;
use craft\gql\base\Directive;
use craft\gql\GqlEntityRegistry;
use GraphQL\Error\UserError;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Type\Definition\Directive as GqlDirective;
use GraphQL\Type\Definition\FieldArgument;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Definition\Type;
use iceboxind\sorts\engine\Options;
use iceboxind\sorts\Plugin;

/**
 * The `@sorts` GraphQL directive (Pro): sets the typography of a text field as it is returned.
 *
 * ```graphql
 * { entries { title @sorts(mode: "text") body @sorts } }
 * ```
 *
 * `mode` is `html` (the default, for rich text) or `text`; `rules` runs rules by name. The
 * language is the one of the entry’s site. It applies to String fields (a title, a Plain Text field,
 * the `html` of a CKEditor field); any other field is returned as it is. On Lite the directive is known to the schema and
 * returns the value as it is, so a query written for Pro does not fail after a downgrade.
 */
class Sorts extends Directive
{
    public static function create(): GqlDirective
    {
        $typeName = static::name();

        return GqlEntityRegistry::getOrCreate($typeName, fn () => new self([
            'name' => $typeName,
            'locations' => [DirectiveLocation::FIELD],
            'args' => [
                new FieldArgument([
                    'name' => 'mode',
                    'type' => Type::string(),
                    'defaultValue' => Options::HTML,
                    'description' => 'How to read the value: `html` (the default) or `text`.',
                ]),
                new FieldArgument([
                    'name' => 'rules',
                    'type' => Type::listOf(Type::string()),
                    'defaultValue' => [],
                    'description' => 'Handles of registered rules to run by name, in addition to the rules that are on.',
                ]),
            ],
            'description' => 'Fixes the typography of the field value: smart quotes, apostrophes, dashes and more. Sorts Pro.',
        ]));
    }

    public static function name(): string
    {
        return 'sorts';
    }

    public static function apply(mixed $source, mixed $value, array $arguments, ResolveInfo $resolveInfo): mixed
    {
        $plugin = Plugin::getInstance();
        // Only a String field: an object field (CKEditor's full data) keeps its shape; ask for its `html` instead.
        if (
            !$plugin->isPro() ||
            $value === null ||
            !(is_scalar($value) || $value instanceof \Stringable) ||
            !Type::getNamedType($resolveInfo->returnType) instanceof StringType
        ) {
            return $value;
        }
        $mode = $arguments['mode'] ?? Options::HTML;
        if ($mode !== Options::HTML && $mode !== Options::TEXT) {
            throw new UserError('The mode argument of @sorts must be “html” or “text”.');
        }
        $rules = $arguments['rules'] ?? [];
        $language = $source instanceof ElementInterface ? $plugin->contentFixer->languageOf($source) : null;
        try {
            return $plugin->engine->apply((string) $value, is_array($rules) ? $rules : [], $language, $mode);
        } catch (\InvalidArgumentException $e) {
            throw new UserError($e->getMessage());
        }
    }
}
