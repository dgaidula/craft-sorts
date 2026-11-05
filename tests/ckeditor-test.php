// ddev craft shell < tests/ckeditor-test.php
// CKEditor as-you-type quotes (Lite and Pro): which of CKEditor's own transformations Sorts enables for a
// language and its rules, what the control panel page really carries, and doing nothing without CKEditor.
// Checked at the HTTP level: the served entry edit page (the editor's script and config), not typed into a browser.
// (No opening PHP tag: the shell reads statements, not a file.)
require_once '/var/www/html/plugins/craft-sorts/tests/support.php';

$locales = new \iceboxind\sorts\engine\LocaleRegistry();
$rules = ['symbols' => true, 'ellipsis' => true, 'dashes' => true, 'fractions' => false, 'quotes' => true];
$t = static fn (string $id, array $r = [], ?string $variant = null) => \iceboxind\sorts\ckeditor\CkeditorSupport::transformations(
    $variant ? $locales->resolve($id)->withVariant($variant) : $locales->resolve($id),
    array_replace($rules, $r)
);

sortsTry('transformations by language', function () use ($t) {
    sortsEq('en-US: symbols, ellipsis, em dash, curly quotes; no arrows, fractions or `--` to en dash', ['copyright', 'registeredTrademark', 'trademark', 'horizontalEllipsis', 'emDash', 'quotesPrimary', 'quotesSecondary'], $t('en-US'));
    sortsEq('en-GB: the same, plus the spaced en dash that is its `--`', ['copyright', 'registeredTrademark', 'trademark', 'horizontalEllipsis', 'emDash', 'enDash', 'quotesPrimary', 'quotesSecondary'], $t('en-GB'));
    sortsEq('en-GB single-outer: one quote of each kind', ['copyright', 'registeredTrademark', 'trademark', 'horizontalEllipsis', 'emDash', 'enDash', 'quotesPrimary', 'quotesSecondary'], $t('en-GB', [], 'single-outer'));
    sortsEq('de: CKEditor has no „…“ transformation, so no quotes', ['copyright', 'registeredTrademark', 'trademark', 'horizontalEllipsis', 'emDash'], array_values(array_diff($t('de'), ['enDash'])));
    sortsCheck('fr: no quotes (« » is not one of CKEditor’s)', !array_intersect($t('fr'), ['quotesPrimary', 'quotesSecondary', 'quotesPrimaryPl', 'quotesSecondaryPl', 'quotesPrimaryEnGb', 'quotesSecondaryEnGb']));
    sortsCheck('fr: a spaced en dash', in_array('enDash', $t('fr'), true));
    sortsCheck('pl: „…” for the double key only (its nested « » is not CKEditor’s)', in_array('quotesPrimaryPl', $t('pl'), true) && !in_array('quotesSecondaryPl', $t('pl'), true) && !in_array('quotesSecondary', $t('pl'), true), json_encode($t('pl')));
});

sortsTry('transformations follow the rules', function () use ($t) {
    sortsCheck('fractions on: the five Unicode fractions CKEditor knows', array_intersect(['oneHalf', 'oneThird', 'twoThirds', 'oneForth', 'threeQuarters'], $t('en-US', ['fractions' => true])) === ['oneHalf', 'oneThird', 'twoThirds', 'oneForth', 'threeQuarters']);
    sortsCheck('fractions off: none', !array_intersect(['oneHalf', 'oneThird'], $t('en-US')));
    sortsCheck('quotes off: none', !array_intersect(['quotesPrimary', 'quotesSecondary'], $t('en-US', ['quotes' => false])));
    sortsCheck('dashes off: none', !array_intersect(['emDash', 'enDash'], $t('en-GB', ['dashes' => false])));
    sortsCheck('symbols off: none', !array_intersect(['copyright', 'trademark'], $t('en-US', ['symbols' => false])));
    sortsCheck('ellipsis off: none', !in_array('horizontalEllipsis', $t('en-US', ['ellipsis' => false]), true));
    sortsCheck('the arrow and comparison transformations are never enabled', !array_intersect(['arrowLeft', 'arrowRight', 'lessThanOrEqual', 'greaterThanOrEqual', 'notEqual'], $t('en-US', ['fractions' => true])));
});

sortsTry('the control panel page', function () {
    sortsClean();
    sortsConfigure('pro');
    $entry = sortsEntry(['title' => 'Editor page']);
    $cp = sortsCpClient();
    $page = static fn (string $query = '') => (string) $cp->get('/admin/entries/sortsTest/' . $GLOBALS['sortsEntryId'] . $query)->getBody();
    $GLOBALS['sortsEntryId'] = $entry->id;
    $html = $page();
    sortsCheck('page: TextTransformation is imported', (bool) preg_match('/import \{[^}]*\bTextTransformation\b[^}]*\} from "ckeditor5";/', $html));
    sortsCheck('page: ...and is in the field’s plugin list', (bool) preg_match('/plugins: \[[^\]]*\bTextTransformation\b/', $html));
    sortsCheck('page: its transformations are narrowed (en-US)', str_contains($html, '"typing":{"transformations":{"include":["copyright","registeredTrademark","trademark","horizontalEllipsis","emDash","quotesPrimary","quotesSecondary"]}}'));
    $fr = $page('?site=testbedFr');
    sortsCheck('page, French site: the editor follows the site’s language (no quotes, a spaced en dash)', str_contains($fr, '"include":["copyright","registeredTrademark","trademark","horizontalEllipsis","emDash","enDash"]'), (string) (preg_match('/"typing":\{[^}]*\}\}/', $fr, $m) ? $m[0] : 'no typing config'));

    sortsConfigure('lite');
    $lite = $page();
    sortsCheck('lite page: TextTransformation is there too', str_contains($lite, 'TextTransformation'));
    sortsCheck('lite page: with the Lite rules (no symbols)', str_contains($lite, '"include":["horizontalEllipsis","emDash","quotesPrimary","quotesSecondary"]'), (string) (preg_match('/"typing":\{[^}]*\}\}/', $lite, $m) ? $m[0] : 'no typing config'));

    sortsConfigure('pro', ['ckeditorAsYouType' => false]);
    $off = $page();
    sortsCheck('switched off: TextTransformation is not loaded', !str_contains($off, 'TextTransformation') && !str_contains($off, '"typing"'));
    sortsClean();
});

sortsTry('what CKEditor itself ships', function () {
    $js = (string) file_get_contents('/var/www/html/vendor/craftcms/ckeditor/src/web/assets/ckeditor/dist/lib/ckeditor5.js');
    sortsCheck('ckeditor5.js 5.9.0 exports TextTransformation', str_contains($js, 'DT as TextTransformation'));
    sortsCheck('its default transformation groups are symbols, mathematical, typography and quotes', str_contains($js, 'ET=[`symbols`,`mathematical`,`typography`,`quotes`]'));
    foreach (['quotesPrimary:{from:jT(`"`),to:[null,`“`,null,`”`]}', 'quotesSecondary:{from:jT(`\'`),to:[null,`‘`,null,`’`]}', 'quotesPrimaryPl:{from:jT(`"`),to:[null,`„`,null,`”`]}', 'enDash:{from:/(^| )(--)( )$/,to:[null,`–`,null]}'] as $needle) {
        sortsCheck('CKEditor defines ' . substr($needle, 0, strpos($needle, ':')), str_contains($js, $needle));
    }
    sortsCheck('the core package list does not include it by default (Sorts adds it)', !in_array('TextTransformation', (new \ReflectionClass(\craft\ckeditor\helpers\CkeditorConfig::class))->getStaticPropertyValue('pluginsByPackage')['ckeditor5'] ?? [], true));
});

sortsTry('without CKEditor, nothing happens', function () {
    $file = '/var/www/html/plugins/craft-sorts/src/ckeditor/CkeditorSupport.php';
    $out = [];
    // A bare PHP with only Sorts' own file loaded: craft\ckeditor\* does not exist, as on a site without CKEditor.
    $code = 'require ' . var_export('/var/www/html/plugins/craft-sorts/src/engine/LocaleInterface.php', true) . ';'
        . 'require ' . var_export($file, true) . ';'
        . 'iceboxind\sorts\ckeditor\CkeditorSupport::register(); echo "no-op ok";';
    exec('php -r ' . escapeshellarg($code) . ' 2>&1', $out, $code2);
    sortsEq('register() returns quietly when the class is missing', 'no-op ok', implode("\n", $out));
});

sortsDefaults();
sortsDone();
