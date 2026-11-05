// ddev craft shell < tests/smoke-test.php
// (No opening PHP tag: the shell reads statements, not a file.)
$pass = 0;
$fail = 0;
$check = function (string $label, bool $ok) use (&$pass, &$fail): void {
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'ok   ' : 'FAIL ') . $label . "\n";
};
$plugin = \Craft::$app->getPlugins()->getPlugin('sorts');
$check('plugin installed and enabled', $plugin !== null);
$check('edition is a declared one', $plugin !== null && in_array($plugin->edition, $plugin::editions(), true));
echo "RESULT: {$pass} passed, {$fail} failed\n";
