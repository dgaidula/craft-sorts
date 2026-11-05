// ddev craft shell < tests/page-pass-test.php
// The automatic page pass over real HTTP requests to the testbed (from inside the container): Lite does
// nothing, Pro fixes pages, and it stays out of the control panel, action requests and non-HTML responses.
// (No opening PHP tag: the shell reads statements, not a file.)
require_once '/var/www/html/plugins/craft-sorts/tests/support.php';

$body = static fn (\Psr\Http\Message\ResponseInterface $r): string => (string) $r->getBody();
$has = static fn (string $haystack, string $needle): bool => str_contains($haystack, $needle);
$straight = '"Hello" -- she said';
$curly = '“Hello” — she said';

sortsTry('lite', function () use ($body, $has, $straight) {
    sortsConfigure('lite');
    $http = sortsHttp();
    $r = $http->get('/sorts-lab');
    sortsEq('lite: the page is served', 200, $r->getStatusCode());
    sortsCheck('lite: the page pass does nothing (straight quotes stay)', $has($body($r), $straight), substr($body($r), 0, 600));
});

sortsTry('pro, on', function () use ($body, $has, $curly) {
    sortsConfigure('pro');
    $http = sortsHttp();
    $r = $http->get('/sorts-lab');
    $html = $body($r);
    sortsEq('pro: the page is served', 200, $r->getStatusCode());
    sortsCheck('pro: body text is typeset', $has($html, $curly), $html);
    sortsCheck('pro: headings get widow control and curly quotes', $has($html, "<h1>Lab — the “heading” of the\u{00A0}page</h1>"), $html);
    sortsCheck('pro: primes', $has($html, '5′10″'), $html);
    sortsCheck('pro: symbols', $has($html, '©'), $html);
    sortsCheck('pro: lang attribute switches the locale', $has($html, "«\u{202F}Bonjour\u{202F}»"), $html);
    sortsCheck('pro: data-sorts="off" is left alone', $has($html, '<p id="off" data-sorts="off">"Skip" -- it\'s off...</p>'), $html);
    sortsCheck('pro: pre is left alone', $has($html, '<pre>"code" -- stays</pre>'), $html);
    sortsCheck('pro: script is left alone', $has($html, '<script>var s = "it\'s -- raw";</script>'), $html);
    sortsCheck('pro: style is left alone', $has($html, 'content: "don\'t touch"'), $html);
    sortsCheck('pro: the title is left alone by default', $has($html, '<title>"Lab" -- it\'s a test</title>'), $html);
    sortsCheck('pro: meta content is left alone by default', $has($html, 'content="She said &quot;hi&quot; -- it\'s fine..."') && $has($html, "content='The \"Lab\" -- tested'"), $html);
    sortsCheck('pro: the fixture rule is not scoped here', $has($html, 'Add 0.875 cup and 1.5 cups, “unscoped”.'), $html);
    sortsCheck('pro: data-sorts-rules scopes the fixture rule', $has($html, "Add ⅞ cup, 1½ cups and 250\u{00A0}ml; “scoped”."), $html);
});

sortsTry('pro, the head', function () use ($body, $has) {
    sortsConfigure('pro', ['processHead' => true]);
    $html = $body(sortsHttp()->get('/sorts-lab'));
    sortsCheck('head on: the title is typeset', $has($html, '<title>“Lab” — it’s a test</title>'), $html);
    sortsCheck('head on: a description is typeset and re-encoded safely', $has($html, 'content="She said “hi” — it’s fine…"'), $html);
    sortsCheck('head on: a single-quoted og:title is typeset', $has($html, 'content=\'The “Lab” — tested\''), $html);
    sortsCheck('head on: viewport is untouched', $has($html, '<meta name="viewport" content="width=device-width, initial-scale=1">'), $html);
    sortsCheck('head on: a style in the head is still untouched', $has($html, 'content: "don\'t touch"'), $html);
});

sortsTry('pro, the head: edge cases', function () use ($body, $has) {
    $edge = static fn () => $body(sortsHttp()->get('/sorts-head-lab'));
    sortsConfigure('pro');
    $html = $edge();
    sortsCheck('head off: nothing in the head changes', $has($html, '<title>Head "lab" -- title</title>') && $has($html, "content='She said \"reversed\" -- order'"), substr($html, 0, 900));
    sortsConfigure('pro', ['processHead' => true]);
    $html = $edge();
    sortsCheck('head on: title', $has($html, '<title>Head “lab” — title</title>'), $html);
    sortsCheck('head on: content before name, single quotes', $has($html, "<meta content='She said “reversed” — order' name=\"description\">"), $html);
    sortsCheck('head on: upper-case tag and attribute names, entities decoded and encoded again', $has($html, '<META NAME="Description" CONTENT="Upper &amp; “case”">'), $html);
    sortsCheck('head on: an ampersand and angle bracket stay encoded', $has($html, 'content="Tom &amp; “Jerry” &lt;3"'), $html);
    sortsCheck('head on: og:url is not prose', $has($html, 'content="https://example.com/a--b...c"'), $html);
    sortsCheck('head on: robots is not prose', $has($html, 'content="index, follow -- not prose"'), $html);
    sortsCheck('head on: an unquoted value is left alone', $has($html, '<meta name="description" content=unquoted>'), $html);
    sortsCheck('head on: an empty value is left alone', $has($html, '<meta name="twitter:description" content="">'), $html);
    sortsCheck('head on: a comment is left alone', $has($html, '<!-- <meta name="description" content=\'"in a comment" -- x\'> -->'), $html);
    sortsCheck('head on: noscript is left alone', $has($html, '<noscript><meta name="twitter:title" content=\'"in noscript" -- x\'></noscript>'), $html);
    sortsCheck('head on: a script’s text is left alone', $has($html, '"<meta name=\\"description\\" content=\\"it\'s -- json\\">"'), $html);
    sortsCheck('head on: a template element is left alone', $has($html, '<template><meta name="description" content=\'"in a template" -- x\'></template>'), $html);
    sortsCheck('head on: a meta outside the head is left alone', $has($html, '<meta name="description" content=\'"in the body" -- x\'>'), $html);
    sortsCheck('head on: the body is still typeset', $has($html, '<p>“Body” text — here</p>'), $html);
});

sortsTry('pro, off', function () use ($body, $has, $straight) {
    sortsConfigure('pro', ['pagePass' => false]);
    $html = $body(sortsHttp()->get('/sorts-lab'));
    sortsCheck('page pass switched off: nothing changes', $has($html, $straight), $html);
    sortsConfigure('lite');
});

sortsTry('pro, excluded by URI', function () use ($body, $has, $straight, $curly) {
    sortsConfigure('pro', ['skipUris' => ['/sorts-api/*', '/exact-only']]);
    $http = sortsHttp();
    sortsCheck('uri skip: a matching path is left alone', $has($body($http->get('/sorts-api/lab')), $straight));
    sortsCheck('uri skip: another path is still typeset', $has($body($http->get('/sorts-lab')), $curly));
    sortsConfigure('pro', ['skipUris' => ['sorts-lab']]);
    sortsCheck('uri skip: a pattern without a leading slash matches too', $has($body(sortsHttp()->get('/sorts-lab')), $straight));
    sortsCheck('uri skip: ...and not the longer path', $has($body(sortsHttp()->get('/sorts-api/lab')), $curly));
});

sortsTry('pro, not a page request', function () use ($body, $has, $straight, $curly) {
    sortsConfigure('pro');
    $http = sortsHttp();

    $r = $http->get('/sorts-json');
    sortsCheck('json: content type is json', str_starts_with($r->getHeaderLine('Content-Type'), 'application/json'), $r->getHeaderLine('Content-Type'));
    sortsEq('json: the response is untouched', '{"text": "He said \"hi\" -- it\'s fine..."}', trim($body($r)));

    $r = $http->get('/sorts-feed.json');
    sortsCheck('json by file extension: content type is json', str_starts_with($r->getHeaderLine('Content-Type'), 'application/json'), $r->getHeaderLine('Content-Type'));
    sortsEq('json by file extension: the response is untouched', '{"text": "He said \"hi\" -- it\'s fine..."}', trim($body($r)));

    $r = $http->get('/actions/sortstest/page/render');
    sortsEq('action: the action is served', 200, $r->getStatusCode());
    sortsCheck('action: the page pass does not run', $has($body($r), $straight), substr($body($r), 0, 400));

    $cp = sortsCpClient();
    $r = $cp->get('/admin/settings/plugins/sorts');
    $html = $body($r);
    sortsEq('cp: the settings page is served', 200, $r->getStatusCode());
    sortsCheck('cp: the page pass does not run (the data-sorts="off" help text keeps straight quotes)', $has($html, 'data-sorts=&quot;off&quot;') && !$has($html, 'data-sorts=“off”'), substr($html, 0, 300));
    $r = $cp->get('/admin/dashboard');
    sortsEq('cp: the dashboard is served', 200, $r->getStatusCode());
});

sortsTry('pro, preview and token requests', function () use ($body, $has) {
    sortsConfigure('pro');
    sortsClean();
    $entry = sortsEntry(['title' => 'Preview "me" -- please', 'sortsBody' => '<p>"Draft" body -- here</p>']);
    $siteId = Craft::$app->getSites()->getPrimarySite()->id;

    $http = sortsHttp();
    $r = $http->get('/sorts-test/' . $entry->slug);
    sortsCheck('entry page: typeset', $has($body($r), "<h1>Preview “me” —\u{00A0}please</h1>") && $has($body($r), '<p>“Draft” body — here</p>'), $body($r));

    $token = Craft::$app->getTokens()->createPreviewToken(['preview/preview', [
        'elementType' => \craft\elements\Entry::class,
        'canonicalId' => (int) $entry->id,
        'siteId' => (int) $siteId,
        'draftId' => null,
        'revisionId' => null,
        'userId' => null,
    ]]);
    $r = $http->get('/sorts-test/' . $entry->slug, ['query' => ['token' => $token]]);
    sortsEq('token request: served', 200, $r->getStatusCode());
    sortsCheck('token request: the page pass runs', $has($body($r), "<h1>Preview “me” —\u{00A0}please</h1>"), substr($body($r), 0, 500));

    // A real draft, previewed by token.
    $draft = Craft::$app->getDrafts()->createDraft($entry, 1, 'Test draft');
    $draft->title = 'Draft "title" -- new';
    $draft->setFieldValue('sortsBody', '<p>"Draft" text -- new</p>');
    Craft::$app->getElements()->saveElement($draft);
    $token = Craft::$app->getTokens()->createPreviewToken(['preview/preview', [
        'elementType' => \craft\elements\Entry::class,
        'canonicalId' => (int) $entry->id,
        'siteId' => (int) $siteId,
        'draftId' => (int) $draft->draftId,
        'revisionId' => null,
        'userId' => 1,
    ]]);
    $r = $http->get('/sorts-test/' . $entry->slug, ['query' => ['token' => $token]]);
    sortsCheck('draft preview by token: shows the draft, typeset', $has($body($r), "<h1>Draft “title” —\u{00A0}new</h1>"), substr($body($r), 0, 500));
    sortsClean();
});

sortsTry('pro, the French site', function () use ($body, $has) {
    sortsConfigure('pro');
    $html = $body(sortsHttp()->get('/fr/sorts-lab'));
    sortsCheck('fr site: the page is French (lang attribute)', $has($html, '<html lang="fr">'), substr($html, 0, 200));
    sortsCheck('fr site: guillemets and narrow no-break spaces from the site’s language', $has($html, "«\u{202F}Hello\u{202F}»") || $has($html, "«\u{202F}Hello"), $html);
});

sortsTry('fail safe', function () use ($body, $has, $straight) {
    sortsConfigure('pro');
    sortsThrowing(true);
    $r = sortsHttp()->get('/sorts-lab');
    sortsEq('a rule that throws: the page is still served', 200, $r->getStatusCode());
    sortsCheck('a rule that throws: ...exactly as rendered', $has($body($r), $straight) && $has($body($r), '</html>'), substr($body($r), 0, 300));
    sortsThrowing(false);
    $log = '';
    foreach (glob(Craft::getAlias('@storage') . '/logs/web-*.log') ?: [] as $file) {
        $log .= substr((string) file_get_contents($file), -60000);
    }
    sortsCheck('the failure was logged', str_contains($log, 'Sorts left the page as rendered: RuntimeException: The fixture rule always fails.'));
    sortsCheck('and the next page is typeset again', $has($body(sortsHttp()->get('/sorts-lab')), '“Hello”'));
});

sortsEverywhere(false);
sortsThrowing(false);
sortsDefaults();
sortsDone();
