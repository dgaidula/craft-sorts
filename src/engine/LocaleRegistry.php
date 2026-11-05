<?php

namespace iceboxind\sorts\engine;

/**
 * The locales an engine knows. Built-in locales are the files in `locales/`;
 * a file is read only when its locale is first asked for.
 */
final class LocaleRegistry
{
    public const FALLBACK = 'en-US';

    /** @var array<string, string> lower-case id => file path, not yet loaded */
    private array $files = [];

    /** @var array<string, LocaleInterface> lower-case id => locale */
    private array $loaded = [];

    /** @var array<string, string> lower-case alias => lower-case id */
    private array $aliases = [];

    private bool $allLoaded = false;

    /** @var array<string, LocaleInterface|null> memo of resolve() */
    private array $memo = [];

    public function __construct(?string $directory = null)
    {
        $directory ??= __DIR__ . '/locales';
        foreach (glob($directory . '/*.php') ?: [] as $file) {
            $this->files[strtolower(basename($file, '.php'))] = $file;
        }
    }

    public function register(LocaleInterface $locale): void
    {
        $id = strtolower($locale->id());
        $this->loaded[$id] = $locale;
        unset($this->files[$id]);
        foreach ($locale->aliases() as $alias) {
            $this->aliases[strtolower($alias)] = $id;
        }
        $this->memo = [];
    }

    /** @return list<string> */
    public function ids(): array
    {
        $this->loadAll();
        return array_map(static fn (LocaleInterface $l): string => $l->id(), array_values($this->loaded));
    }

    /**
     * The locale for a language tag: an exact match, then an alias, then the
     * tag with its last subtag removed until something matches (de-AT → de).
     * Null when nothing matches.
     */
    public function resolve(string $tag): ?LocaleInterface
    {
        if (array_key_exists($tag, $this->memo)) {
            return $this->memo[$tag];
        }
        $key = strtolower(str_replace('_', '-', trim($tag)));
        $found = null;
        while ($key !== '' && $found === null) {
            $found = $this->lookup($key);
            $cut = strrpos($key, '-');
            $key = $cut === false ? '' : substr($key, 0, $cut);
        }
        if (count($this->memo) >= 256) {
            $this->memo = []; // tags come from page content: keep the memo bounded
        }
        return $this->memo[$tag] = $found;
    }

    /** The locale for a tag, the fallback id if the tag is unknown, then en-US. */
    public function resolveOrFallback(string $tag, string $fallback = self::FALLBACK): LocaleInterface
    {
        return $this->resolve($tag) ?? $this->resolve($fallback) ?? $this->resolve(self::FALLBACK)
            ?? throw new \RuntimeException('The engine has no en-US locale.');
    }

    private function lookup(string $key): ?LocaleInterface
    {
        if (isset($this->loaded[$key])) {
            return $this->loaded[$key];
        }
        if (isset($this->files[$key])) {
            $this->register(DataLocale::fromFile($this->files[$key]));
            return $this->loaded[$key];
        }
        if (!$this->allLoaded) {
            $this->loadAll();
        }
        if (isset($this->aliases[$key])) {
            return $this->loaded[$this->aliases[$key]] ?? null;
        }
        return null;
    }

    private function loadAll(): void
    {
        if ($this->allLoaded) {
            return;
        }
        $this->allLoaded = true;
        foreach ($this->files as $file) {
            $this->register(DataLocale::fromFile($file));
        }
    }
}
