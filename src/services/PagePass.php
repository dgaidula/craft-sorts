<?php

namespace iceboxind\sorts\services;

use Craft;
use craft\base\Component;
use craft\events\TemplateEvent;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\web\View;
use iceboxind\sorts\engine\Options;
use iceboxind\sorts\Plugin;
use Throwable;

/**
 * The automatic whole-page pass (Pro): fixes the typography of the rendered page
 * before it is sent, with no template edits.
 *
 * It runs for site page requests that answer with HTML, previews and token
 * requests included. It never runs for the control panel, the console, action
 * requests, or a response that is not HTML. Whatever goes wrong, the page is sent
 * as the template rendered it.
 */
class PagePass extends Component
{
    /** `<meta>` names and properties whose content is prose, so worth fixing when the head is. */
    public const PROSE_META = [
        'description', 'og:title', 'og:description', 'og:site_name', 'og:image:alt',
        'twitter:title', 'twitter:description', 'twitter:image:alt',
    ];

    /** @var list<string> the content types the pass treats as HTML */
    private const HTML_TYPES = ['text/html', 'application/xhtml+xml'];

    public function handle(TemplateEvent $event): void
    {
        try {
            if (!$this->shouldRun($event)) {
                return;
            }
            $event->output = $this->fix($event->output);
        } catch (Throwable $e) {
            // Never break a page: log, and let the original output through.
            Craft::error('Sorts left the page as rendered: ' . get_class($e) . ': ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Fix the typography of a rendered page. Throws what the engine or a rule throws.
     */
    public function fix(string $html): string
    {
        $plugin = Plugin::getInstance();
        $settings = $plugin->getSettings();
        $service = $plugin->engine;
        $options = $service->options()->with(skipTags: $settings->pageSkipTags());
        $html = $service->applyWith($html, $options);
        if ($settings->processHead) {
            $html = $this->fixMeta($html, $options->with(mode: Options::TEXT));
        }
        return $html;
    }

    /**
     * Whether this render is one the pass should touch: Pro, switched on, a site
     * page request answering with HTML, and a path that is not on the skip list.
     */
    public function shouldRun(TemplateEvent $event): bool
    {
        $plugin = Plugin::getInstance();
        if (!$plugin->isPro() || !$plugin->getSettings()->pagePass) {
            return false;
        }
        $request = Craft::$app->getRequest();
        // A token request (a share link, a preview) is never an action request, whatever route the token holds.
        if (
            $request->getIsConsoleRequest() ||
            $request->getIsCpRequest() ||
            $request->getIsActionRequest() ||
            $event->templateMode !== View::TEMPLATE_MODE_SITE
        ) {
            return false;
        }
        if (!$this->isHtmlResponse($event)) {
            return false;
        }
        return !self::matchesAny($request->getFullPath(), $plugin->getSettings()->skipUris);
    }

    /**
     * Whether a request path (no leading slash) matches one of the patterns.
     * `*` matches any run of characters, slashes included; the rest is literal.
     *
     * @param list<string> $patterns
     */
    public static function matchesAny(string $path, array $patterns): bool
    {
        $path = trim($path, '/');
        foreach ($patterns as $pattern) {
            // Runs of asterisks mean one; it keeps a pattern like a*a*a*a*b from backtracking for long.
            $pattern = preg_replace('/\*+/', '*', trim($pattern, '/')) ?? '';
            $regex = '~^' . str_replace('\*', '.*', preg_quote($pattern, '~')) . '$~su';
            if (preg_match($regex, $path) === 1) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the response will be HTML: its Content-Type if the template set one
     * (a `{% header %}` tag), else the type Craft will give the template’s file extension.
     */
    private function isHtmlResponse(TemplateEvent $event): bool
    {
        $type = Craft::$app->getResponse()->getHeaders()->get('content-type');
        if (!is_string($type) || $type === '') {
            $file = Craft::$app->getView()->resolveTemplate($event->template, $event->templateMode);
            $file = $file === false ? '' : StringHelper::removeRight(strtolower($file), '.twig');
            $type = FileHelper::getMimeTypeByExtension($file) ?? 'text/html';
        }
        return in_array(strtolower(trim(explode(';', $type)[0])), self::HTML_TYPES, true);
    }

    /**
     * Fix the `content` of the prose `<meta>` tags in `<head>`. The engine leaves attributes
     * alone by design, so this decodes the value, runs the engine over it as plain text, and
     * encodes it again; a value the engine does not change is left byte for byte.
     */
    private function fixMeta(string $html, Options $textOptions): string
    {
        if (!preg_match('~<head\b[^>]*>~i', $html, $open, PREG_OFFSET_CAPTURE)) {
            return $html;
        }
        $start = $open[0][1] + strlen($open[0][0]);
        $end = stripos($html, '</head', $start);
        if ($end === false) {
            return $html;
        }
        $head = substr($html, $start, $end - $start);

        // Text inside scripts, styles, templates and comments is not markup.
        preg_match_all('~<!--.*?(?:-->|\z)|<(script|style|noscript|template)\b.*?(?:</\1\s*>|\z)~is', $head, $blocks, PREG_OFFSET_CAPTURE);
        $inRaw = static function (int $offset) use ($blocks): bool {
            foreach ($blocks[0] as [$text, $at]) {
                if ($offset >= $at && $offset < $at + strlen($text)) {
                    return true;
                }
            }
            return false;
        };

        $out = '';
        $last = 0;
        preg_match_all('~<meta\b(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>~i', $head, $metas, PREG_OFFSET_CAPTURE);
        foreach ($metas[0] as [$tag, $at]) {
            if ($inRaw($at)) {
                continue;
            }
            $fixed = $this->fixMetaTag($tag, $textOptions);
            if ($fixed !== $tag) {
                $out .= substr($head, $last, $at - $last) . $fixed;
                $last = $at + strlen($tag);
            }
        }
        if ($last === 0) {
            return $html;
        }
        return substr($html, 0, $start) . $out . substr($head, $last) . substr($html, $end);
    }

    private function fixMetaTag(string $tag, Options $textOptions): string
    {
        if (!preg_match('~(?<![\w-])(?:name|property)\s*=\s*(?:"([^"]*)"|\'([^\']*)\')~i', $tag, $key)) {
            return $tag;
        }
        $name = strtolower(trim($key[2] ?? $key[1]));
        if (!in_array($name, self::PROSE_META, true)) {
            return $tag;
        }
        if (!preg_match('~(?<![\w-])content(\s*=\s*)(?:"([^"]*)"|\'([^\']*)\')~i', $tag, $content, PREG_OFFSET_CAPTURE)) {
            return $tag;
        }
        $quoted = $content[2][1] >= 0 ? $content[2] : $content[3];
        $raw = $quoted[0];
        $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $fixed = Plugin::getInstance()->engine->applyWith($decoded, $textOptions);
        if ($fixed === $decoded) {
            return $tag;
        }
        $encoded = htmlspecialchars($fixed, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
        return substr($tag, 0, $quoted[1]) . $encoded . substr($tag, $quoted[1] + strlen($raw));
    }
}
