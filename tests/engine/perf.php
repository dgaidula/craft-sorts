<?php

/**
 * Time the engine on a generated, realistic ~100 KB page with the default rules.
 * `composer perf-engine`, or `php tests/engine/perf.php [runs]`. Prints the PHP
 * version, the page size and the median, minimum and 95th percentile times.
 * Run it with opcache.enable_cli=1 and the JIT off to see what a web request sees.
 */

use iceboxind\sorts\engine\Engine;

require __DIR__ . '/bootstrap.php';

/**
 * A news-site article page: navigation, a long article, a sidebar, a footer.
 * `$plain` plain sentences are mixed in for each one that needs typesetting: 0 is
 * the dense stress page (every sentence has quotes, dashes, units or the like);
 * 6 is a typical article, where most sentences need nothing.
 */
function realisticPage(int $plain = 6): string
{
    mt_srand(100);
    $sentences = [
        'The council voted on Tuesday to approve the new library, which will open in the spring.',
        '"We\'ve waited a long time for this," said Mayor Dana Ortiz, "and it\'s worth every penny."',
        'Construction began in the \'90s, then stalled -- twice -- before funding arrived in 2019.',
        'The building is 5\'10" shorter than planned, but the reading room seats 120 people.',
        'Dr. Smith, who led the survey, said that 10 kg of books arrive each week...',
        'Residents can read more at <a href="/library/plans" title="Library plans">the plans page</a>, or ask at the desk.',
        'She added: "It\'s a place for everyone," and the crowd, <em>all</em> of whom had come early, applauded.',
        'Tom &amp; Jerry&#039;s Diner donated &quot;the first coffee&quot; to the opening day crowd.',
        'Hours are 9-5 on weekdays; the children\'s room stays open until 7 p.m. for homework help.',
        'Copies of the budget (c) 2024 are available, and the full report runs to 1/2 an inch of paper.',
    ];
    for ($i = 0; $i < $plain; $i++) {
        $sentences = array_merge($sentences, [
            'Officials said the project had been planned for most of the decade and was on schedule.',
            'The new building replaces a smaller branch on the east side of the river.',
            'Parking is available behind the town hall, and buses stop outside every fifteen minutes.',
            'Volunteers will help with moving the collection over the coming weeks.',
        ]);
    }
    $html = "<!DOCTYPE html>\n<html lang=\"en\"><head><meta charset=\"utf-8\"><title>Library approved - The Daily Example</title>\n"
        . "<meta name=\"description\" content=\"The council's decision, in brief\"><link rel=\"stylesheet\" href=\"/css/site.css\">\n"
        . "<script>window.dataLayer = window.dataLayer || []; function gtag(){dataLayer.push(arguments);} gtag('js', new Date());</script></head>\n<body>\n"
        . '<header class="site-header"><nav class="nav" aria-label="Main"><ul>';
    foreach (['News', 'Sport', 'Culture', 'Opinion', 'Food', 'Travel', 'Contact'] as $item) {
        $html .= "<li class=\"nav__item\"><a class=\"nav__link\" href=\"/" . strtolower($item) . "\" data-track=\"nav-$item\">$item</a></li>";
    }
    $html .= "</ul></nav></header>\n<main><article class=\"story\" data-id=\"4711\"><h1 class=\"story__title\">Council approves the new library after years of delay</h1>\n"
        . "<p class=\"story__dek\">The vote was 7-2, and the building opens in the spring.</p>\n";
    while (strlen($html) < 70000) {
        if (mt_rand(0, 9) === 0) {
            $html .= '<h2 class="story__sub">' . ['What happens next', 'Who pays for it', 'The view from the street'][mt_rand(0, 2)] . "</h2>\n";
        }
        $html .= '<p class="story__p">';
        for ($i = mt_rand(2, 5); $i > 0; $i--) {
            $html .= $sentences[mt_rand(0, count($sentences) - 1)] . ' ';
        }
        $html .= "</p>\n";
        if (mt_rand(0, 12) === 0) {
            $html .= '<blockquote class="pull"><p>"We\'re not done yet," she said.</p></blockquote>' . "\n";
        }
        if (mt_rand(0, 15) === 0) {
            $html .= '<ul class="facts"><li>Seats: 120</li><li>Floors: 2</li><li>Cost: $4.2 million</li></ul>' . "\n";
        }
        if (mt_rand(0, 20) === 0) {
            $html .= '<figure><img src="/img/library.jpg" alt="The new library, seen from the street" width="800" height="450"><figcaption>The library\'s east face.</figcaption></figure>' . "\n";
        }
    }
    $html .= "</article>\n<aside class=\"sidebar\"><h2>Most read</h2><ol>";
    for ($i = 1; $i <= 40; $i++) {
        $html .= "<li class=\"trend\"><a href=\"/story/$i\" data-position=\"$i\">Story headline number $i about something happening nearby</a></li>";
    }
    $html .= "</ol></aside></main>\n<footer class=\"site-footer\"><p>Copyright 2024 The Daily Example. All rights reserved.</p>";
    while (strlen($html) < 100000) {
        $html .= '<a class="footer__link" href="/about/' . mt_rand(1000, 9999) . '" rel="nofollow" data-x="1">Footer link ' . mt_rand(1, 99) . '</a> ';
    }
    return $html . "</footer></body></html>\n";
}

$runs = (int) ($argv[1] ?? 300);
$engine = new Engine();
printf("PHP %s, opcache.enable_cli=%s, jit=%s\n", PHP_VERSION, ini_get('opcache.enable_cli') ?: '0', ini_get('opcache.jit') ?: 'off');
foreach (['typical article' => 6, 'dense stress page' => 0] as $label => $plain) {
    $page = realisticPage($plain);
    $out = $engine->apply($page);
    for ($i = 0; $i < 30; $i++) {
        $engine->apply($page);
    }
    $times = [];
    for ($i = 0; $i < $runs; $i++) {
        $t = hrtime(true);
        $engine->apply($page);
        $times[] = (hrtime(true) - $t) / 1e6;
    }
    sort($times);
    printf(
        "%-18s %d bytes in, %d out, runs %d: median %.2f ms, min %.2f ms, p95 %.2f ms\n",
        $label,
        strlen($page),
        strlen($out),
        $runs,
        $times[intdiv(count($times), 2)],
        $times[0],
        $times[(int) floor(count($times) * 0.95)]
    );
}
