// ddev craft shell < tests/graphql-test.php
// The @sorts GraphQL directive (Pro): real queries through Craft's GraphQL service and over HTTP.
// (No opening PHP tag: the shell reads statements, not a file.)
require_once '/var/www/html/plugins/craft-sorts/tests/support.php';

$gql = Craft::$app->getGql();
$schema = null;
$token = null;

sortsTry('setup', function () use ($gql, &$schema, &$token) {
    sortsClean();
    sortsConfigure('pro');
    // Leftovers from an earlier run that stopped part way.
    foreach ($gql->getTokens() as $old) {
        if ($old->name === 'Sorts test token') {
            $gql->deleteTokenById($old->id);
        }
    }
    foreach ($gql->getSchemas() as $old) {
        if ($old->name === 'Sorts test schema') {
            $gql->deleteSchema($old);
        }
    }
    $section = Craft::$app->getEntries()->getSectionByHandle('sortsTest');
    $type = Craft::$app->getEntries()->getEntryTypeByHandle('sortsTest');
    $schema = new \craft\models\GqlSchema(['name' => 'Sorts test schema', 'scope' => array_merge(
        ["sections.{$section->uid}:read", "entrytypes.{$type->uid}:read"],
        array_map(fn($site) => "sites.{$site->uid}:read", Craft::$app->getSites()->getAllSites()),
    )]);
    if (!$gql->saveSchema($schema)) {
        throw new \RuntimeException('Schema did not save: ' . json_encode($schema->getErrors()));
    }
    $token = new \craft\models\GqlToken(['name' => 'Sorts test token', 'accessToken' => Craft::$app->getSecurity()->generateRandomString(32), 'enabled' => true, 'schemaId' => $schema->id]);
    if (!$gql->saveToken($token)) {
        throw new \RuntimeException('Token did not save: ' . json_encode($token->getErrors()));
    }
    Craft::$app->getProjectConfig()->saveModifiedConfigData();
    sortsEntry(['title' => 'GraphQL "title" -- here', 'slug' => 'gql-one', 'sortsPlain' => 'Plain it\'s...', 'sortsBody' => '<p>"Body" -- <b>bold</b></p>']);
});

$run = static fn (string $query) => Craft::$app->getGql()->executeQuery(Craft::$app->getGql()->getSchemaById($GLOBALS['gqlSchemaId']), $query);
$GLOBALS['gqlSchemaId'] = $schema?->id;

sortsTry('queries', function () use ($run, $schema) {
    $GLOBALS['gqlSchemaId'] = $schema->id;
    $q = '{ entries(section: "sortsTest", slug: "gql-one") { title plain: sortsPlain body: sortsBody } }';
    $r = $run($q);
    sortsEq('no directive: values as stored', 'GraphQL "title" -- here', $r['data']['entries'][0]['title'] ?? json_encode($r));

    $q = '{ entries(section: "sortsTest", slug: "gql-one") { title @sorts(mode: "text") plain: sortsPlain @sorts(mode: "text") body: sortsBody { html @sorts } } }';
    $r = $run($q);
    $row = $r['data']['entries'][0] ?? [];
    sortsEq('@sorts(mode: "text") on the title', 'GraphQL “title” — here', $row['title'] ?? json_encode($r));
    sortsEq('@sorts(mode: "text") on a plain-text field', 'Plain it’s…', $row['plain'] ?? null);
    sortsEq('@sorts on the html of a CKEditor field (HTML mode is the default)', '<p>“Body” — <b>bold</b></p>', $row['body']['html'] ?? null);

    $r = $run('{ entries(section: "sortsTest", slug: "gql-one") { title @sorts } }');
    sortsEq('html mode on a plain string: markup is read as markup', 'GraphQL “title” — here', $r['data']['entries'][0]['title'] ?? json_encode($r));

    $r = $run('{ entries(section: "sortsTest", slug: "gql-one") { sortsBody { rawHtml @sorts(rules: ["recipe-fractions"]) } } }');
    sortsEq('rules by name, on rawHtml', '<p>“Body” — <b>bold</b></p>', $r['data']['entries'][0]['sortsBody']['rawHtml'] ?? json_encode($r));

    $r = $run('{ entries(section: "sortsTest", slug: "gql-one") { sortsBody @sorts { html } } }');
    sortsEq('an object field keeps its shape', '<p>"Body" -- <b>bold</b></p>', $r['data']['entries'][0]['sortsBody']['html'] ?? json_encode($r));

    $r = $run('{ entries(section: "sortsTest", slug: "gql-one") { title @sorts(mode: "xml") } }');
    sortsCheck('a bad mode is a GraphQL error', isset($r['errors'][0]['message']) && str_contains($r['errors'][0]['message'], 'must be “html” or “text”'), json_encode($r));

    $r = $run('{ entries(section: "sortsTest", slug: "gql-one") { title @sorts(rules: ["nope"]) } }');
    sortsCheck('an unknown rule is a GraphQL error', isset($r['errors'][0]['message']) && str_contains($r['errors'][0]['message'], 'No rule is registered'), json_encode($r));

    $r = $run('{ entries(section: "sortsTest", slug: "gql-one") { id @sorts } }');
    sortsCheck('a value that is not text passes through', isset($r['data']['entries'][0]['id']), json_encode($r));

    $r = $run('{ entries(section: "sortsTest", slug: "gql-one") { sortsShort @sorts } }');
    sortsCheck('a null value stays null', array_key_exists('sortsShort', $r['data']['entries'][0] ?? []) && $r['data']['entries'][0]['sortsShort'] === null, json_encode($r));
});

sortsTry('over HTTP', function () use ($token) {
    $res = sortsHttp()->post('/actions/graphql/api', [
        'headers' => ['Authorization' => 'Bearer ' . $token->accessToken, 'Content-Type' => 'application/json'],
        'body' => json_encode(['query' => '{ entries(section: "sortsTest", slug: "gql-one") { title @sorts(mode: "text") } }']),
    ]);
    $data = json_decode((string) $res->getBody(), true);
    sortsEq('http: status', 200, $res->getStatusCode());
    sortsEq('http: the directive runs in a real request', 'GraphQL “title” — here', $data['data']['entries'][0]['title'] ?? json_encode($data));
});

sortsTry('the French site uses its own language', function () use ($run, $schema) {
    $GLOBALS['gqlSchemaId'] = $schema->id;
    $r = $run('{ entries(section: "sortsTest", slug: "gql-one", site: "testbedFr") { title @sorts(mode: "text") } }');
    sortsEq('fr: the entry’s site language sets the quotes', "GraphQL «\u{202F}title\u{202F}» – here", $r['data']['entries'][0]['title'] ?? json_encode($r));
});

// F5: the html of a CKEditor field (its source is the field data, not an element) takes the
// language of the site the field data belongs to, as the entry's title does, whichever endpoint asked.
sortsTry('no element source: the field data’s own site', function () use ($token) {
    $post = static fn (string $path, string $query) => json_decode((string) sortsHttp()->post($path, [
        'headers' => ['Authorization' => 'Bearer ' . $token->accessToken, 'Content-Type' => 'application/json'],
        'body' => json_encode(['query' => $query]),
    ])->getBody(), true);
    $query = '{ entries(section: "sortsTest", slug: "gql-one", site: "testbedFr") { title @sorts(mode: "text") ... on sortsTest_Entry { sortsBody { html @sorts } } } }';
    $fr = $post('/fr/actions/graphql/api', $query);
    sortsEq('request to the French site: the html of a CKEditor field is French', "<p>«\u{202F}Body\u{202F}» – <b>bold</b></p>", $fr['data']['entries'][0]['sortsBody']['html'] ?? json_encode($fr));
    sortsEq('request to the French site: the title (an element source) is French', "GraphQL «\u{202F}title\u{202F}» – here", $fr['data']['entries'][0]['title'] ?? json_encode($fr));
    $en = $post('/actions/graphql/api', $query);
    sortsEq('request to the English site: the html of a French entry stays French, like its title', "<p>«\u{202F}Body\u{202F}» – <b>bold</b></p>", $en['data']['entries'][0]['sortsBody']['html'] ?? json_encode($en));
    $enOwn = $post('/actions/graphql/api', '{ entries(section: "sortsTest", slug: "gql-one") { ... on sortsTest_Entry { sortsBody { html @sorts } } } }');
    sortsEq('request to the English site: an English entry’s html is English', '<p>“Body” — <b>bold</b></p>', $enOwn['data']['entries'][0]['sortsBody']['html'] ?? json_encode($enOwn));
    sortsEq('request to the English site: the title still follows its entry’s site', "GraphQL «\u{202F}title\u{202F}» – here", $en['data']['entries'][0]['title'] ?? json_encode($en));
});

sortsTry('lite', function () use ($run, $schema) {
    $GLOBALS['gqlSchemaId'] = $schema->id;
    sortsConfigure('lite');
    $r = $run('{ entries(section: "sortsTest", slug: "gql-one") { title @sorts(mode: "text") } }');
    sortsEq('lite: the directive is known and returns the value as stored', 'GraphQL "title" -- here', $r['data']['entries'][0]['title'] ?? json_encode($r));
    sortsConfigure('pro');
});

sortsTry('teardown', function () use ($gql, $schema, $token) {
    $gql->deleteTokenById($token->id);
    $gql->deleteSchema($schema);
    Craft::$app->getProjectConfig()->saveModifiedConfigData();
    sortsClean();
});

sortsDefaults();
sortsDone();
