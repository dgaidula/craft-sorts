<?php

/**
 * Runs the corpus. Every case checks the output and then runs the engine on its
 * own output, which must come back unchanged (idempotence).
 *
 * Format of corpus/*.txt (one case per `@` header):
 *   @ <name> [locale=fr] [mode=text] [only=h1,h2] [on=h] [off=h] [named=h] [config={json}]
 *   < input line (repeat for more lines; joined with \n)
 *   > expected line (repeat likewise; leave out when the input must come back unchanged)
 * `\u{A0}` style escapes work in input and expected. `#` starts a comment line.
 * config= is JSON: ruleOptions, skipTags, skipClasses, variants (locale id => variant name).
 * only= turns every rule off except those listed; on= and off= adjust the defaults.
 * idem=no skips the idempotence check, only for cases that prove settled characters are
 * protected: protection lasts for one call, so a second call may change what the first spared.
 */

use iceboxind\sorts\engine\Engine;
use iceboxind\sorts\engine\Options;

function unescapeCase(string $s): string
{
    return preg_replace_callback('/\\\\u\{([0-9A-Fa-f]+)\}/', static fn (array $m): string => mb_chr(hexdec($m[1])), $s);
}

/** @return list<array{name: string, file: string, line: int, attrs: array<string,string>, in: string, out: ?string}> */
function parseCorpus(string $file): array
{
    $cases = [];
    $cur = null;
    foreach (file($file, FILE_IGNORE_NEW_LINES) as $n => $line) {
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if ($line[0] === '@') {
            if ($cur) {
                $cases[] = $cur;
            }
            $parts = preg_split('/\s+/', trim(substr($line, 1)));
            $name = array_shift($parts);
            $attrs = [];
            foreach ($parts as $p) {
                [$k, $v] = explode('=', $p, 2) + [1 => ''];
                $attrs[$k] = $v;
            }
            $cur = ['name' => $name, 'file' => basename($file), 'line' => $n + 1, 'attrs' => $attrs, 'in' => [], 'out' => null];
        } elseif ($line[0] === '<') {
            $cur['in'][] = unescapeCase(substr($line, 2));
        } elseif ($line[0] === '>') {
            $cur['out'] ??= [];
            $cur['out'][] = unescapeCase(substr($line, 2));
        } else {
            fail(basename($file) . ':' . ($n + 1), '  unrecognised corpus line: ' . $line);
        }
    }
    if ($cur) {
        $cases[] = $cur;
    }
    foreach ($cases as &$c) {
        $c['in'] = implode("\n", $c['in']);
        $c['out'] = $c['out'] === null ? $c['in'] : implode("\n", $c['out']);
    }
    return $cases;
}

function caseOptions(Engine $engine, array $attrs): array
{
    $rules = [];
    if (isset($attrs['only'])) {
        foreach ($engine->rules() as $rule) {
            $rules[$rule->handle()] = false;
        }
        foreach (array_filter(explode(',', $attrs['only'])) as $h) {
            $rules[$h] = true;
        }
    }
    foreach (array_filter(explode(',', $attrs['on'] ?? '')) as $h) {
        $rules[$h] = true;
    }
    foreach (array_filter(explode(',', $attrs['off'] ?? '')) as $h) {
        $rules[$h] = false;
    }
    $config = isset($attrs['config']) ? json_decode($attrs['config'], true, 512, JSON_THROW_ON_ERROR) : [];
    $options = new Options(
        locale: $attrs['locale'] ?? 'en-US',
        mode: $attrs['mode'] ?? 'html',
        rules: $rules,
        ruleOptions: $config['ruleOptions'] ?? [],
        skipTags: $config['skipTags'] ?? [],
        skipClasses: $config['skipClasses'] ?? [],
        variants: $config['variants'] ?? [],
    );
    $named = array_values(array_filter(explode(',', $attrs['named'] ?? '')));
    return [$options, $named];
}

$engine = newEngine();
$corpusCount = 0;
$seen = [];
foreach (glob(__DIR__ . '/corpus/*.txt') as $file) {
    foreach (parseCorpus($file) as $case) {
        $label = $case['file'] . ':' . $case['line'] . ' ' . $case['name'];
        if (isset($seen[$case['name']])) {
            fail($label, '  duplicate case name (also ' . $seen[$case['name']] . ')');
            continue;
        }
        $seen[$case['name']] = $case['file'] . ':' . $case['line'];
        [$options, $named] = caseOptions($engine, $case['attrs']);
        $corpusCount++;
        $got = $engine->apply($case['in'], $named, $options);
        if ($got !== $case['out']) {
            fail($label, '  input:    ' . vis($case['in']) . "\n  expected: " . vis($case['out']) . "\n  actual:   " . vis($got));
            continue;
        }
        $again = $engine->apply($got, $named, $options);
        if ($again !== $got && ($case['attrs']['idem'] ?? '') !== 'no') {
            fail($label . ' (idempotence)', '  first:  ' . vis($got) . "\n  second: " . vis($again));
            continue;
        }
        pass();
    }
}
echo "corpus: $corpusCount cases\n";
$GLOBALS['corpusCount'] = $corpusCount;
