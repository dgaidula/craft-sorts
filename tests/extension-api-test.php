// ddev craft shell < tests/extension-api-test.php
// The extension API (Pro), SPEC §5.5 acceptance: the fixture rule from the sortstest module (recipe fractions),
// registered through Plugin::EVENT_REGISTER_RULES, passes all three scopes, and its output is byte for byte the same
// in the page pass and in the PHP call.
// (No opening PHP tag: the shell reads statements, not a file.)
require_once '/var/www/html/plugins/craft-sorts/tests/support.php';

$page = static function (): string {
    return (string) sortsHttp()->get('/sorts-lab')->getBody();
};
/** The inner HTML of the element with this id on the lab page. */
$inner = static function (string $html, string $id): ?string {
    return preg_match('~<(\w+)[^>]*\bid="' . preg_quote($id, '~') . '"[^>]*>(.*?)</\1>~s', $html, $m) ? $m[2] : null;
};

$text = 'Mix 0.875 cup, 1.5 cups, 0.5 oz, 0.75 ml and 250 ml.';
$expected = "Mix ⅞ cup, 1½ cups, 0.5\u{00A0}oz, 0.75\u{00A0}ml and 250\u{00A0}ml.";

sortsTry('scope (c): by name', function () use ($text, $expected) {
    sortsEverywhere(false);
    sortsConfigure('pro');
    sortsCheck('the module registered the rule', in_array('recipe-fractions', array_map(fn($r) => $r->handle(), sorts()->engine->engine()->rules()), true));
    sortsEq('php call by name', $expected, sorts()->engine->apply($text, ['recipe-fractions']));
    sortsEq('php call without the name: untouched', "Mix 0.875 cup, 1.5 cups, 0.5\u{00A0}oz, 0.75\u{00A0}ml and 250\u{00A0}ml.", sorts()->engine->apply($text));
    $twig = Craft::$app->getView()->renderString('{{ s|sorts(rules: [\'recipe-fractions\']) }}', ['s' => $text], \craft\web\View::TEMPLATE_MODE_SITE, true);
    sortsEq('twig filter by name equals the php call', $expected, $twig);
    sortsEq('csv-style text mode by name equals the php call', $expected, sorts()->engine->apply($text, ['recipe-fractions'], null, 'text'));
});

sortsTry('scope (b): data-sorts-rules, and page equals php call', function () use ($text, $expected, $page, $inner) {
    sortsEverywhere(false);
    sortsConfigure('pro');
    $html = $page();
    sortsEq('page: inside data-sorts-rules', $expected, $inner($html, 'recipe'));
    sortsEq('page: outside it, untouched', 'Add 0.875 cup and 1.5 cups, “unscoped”.', $inner($html, 'unscoped'));
    sortsEq('page output is byte-identical to the php call', sorts()->engine->apply($text, ['recipe-fractions']), $inner($html, 'recipe'));
});

sortsTry('scope (a): everywhere, and page equals php call', function () use ($page, $inner) {
    sortsEverywhere(true);
    sortsConfigure('pro');
    $html = $page();
    $unscopedText = 'Add 0.875 cup and 1.5 cups, "unscoped".';
    sortsEq('page: everywhere', 'Add ⅞ cup and 1½ cups, “unscoped”.', $inner($html, 'unscoped'));
    sortsEq('page output is byte-identical to the php call', sorts()->engine->apply($unscopedText), $inner($html, 'unscoped'));
    sortsCheck('the head and attributes were not touched by it', str_contains($html, '<meta name="viewport" content="width=device-width, initial-scale=1">'));
    sortsEverywhere(false);
});

sortsTry('lite: the extension API is Pro', function () use ($text) {
    sortsEverywhere(false);
    sortsConfigure('lite');
    sortsCheck('lite: the module’s rule is not registered', !in_array('recipe-fractions', array_map(fn($r) => $r->handle(), sorts()->engine->engine()->rules()), true));
    sortsEq('lite: naming it is skipped, not an error', 'Mix 0.875 cup', sorts()->engine->apply('Mix 0.875 cup', ['recipe-fractions']));
});

sortsTry('bad registrations are logged, not fatal', function () {
    sortsConfigure('pro');
    $handler = function ($event) {
        $event->rules[] = new \modules\sortstest\rules\RecipeFractionsRule();  // the handle is taken
        $event->rules[] = new \stdClass();                                     // not a rule
        $event->rules[] = new class extends \iceboxind\sorts\engine\rules\AbstractRule {
            public function handle(): string
            {
                return 'Bad Handle';
            }
            public function priority(): int
            {
                return 1;
            }
            public function defaultEnabled(): bool
            {
                return true;
            }
            public function apply(\iceboxind\sorts\engine\Run $run, \iceboxind\sorts\engine\Context $context): void
            {
            }
        };
    };
    \yii\base\Event::on(\iceboxind\sorts\Plugin::class, \iceboxind\sorts\Plugin::EVENT_REGISTER_RULES, $handler);
    sorts()->engine->reset();
    sortsEq('the good rule still works', 'Mix ⅞ cup', sorts()->engine->apply('Mix 0.875 cup', ['recipe-fractions']));
    sortsEq('and ordinary typography still runs', '“x”', sorts()->engine->apply('"x"'));
    \yii\base\Event::off(\iceboxind\sorts\Plugin::class, \iceboxind\sorts\Plugin::EVENT_REGISTER_RULES, $handler);
    sorts()->engine->reset();
});

sortsEverywhere(false);
sortsDefaults();
sortsDone();
