<?php

namespace iceboxind\sorts\engine;

use InvalidArgumentException;

/** A locale read from a data array, as the files in `locales/` return it. */
final class DataLocale implements LocaleInterface
{
    private function __construct(private readonly array $data)
    {
    }

    public static function fromArray(array $data): self
    {
        foreach (['id', 'quotes', 'nested', 'apostrophe', 'dash'] as $key) {
            if (!isset($data[$key])) {
                throw new InvalidArgumentException("Locale data is missing “{$key}”.");
            }
        }
        foreach (['quotes', 'nested'] as $key) {
            if (!is_array($data[$key]) || count($data[$key]) !== 2) {
                throw new InvalidArgumentException("Locale “{$data['id']}” needs two marks in “{$key}”.");
            }
        }
        if (isset($data['third']) && (!is_array($data['third']) || count($data['third']) !== 2)) {
            throw new InvalidArgumentException("Locale “{$data['id']}” needs two marks in “third”.");
        }
        foreach ($data['variants'] ?? [] as $name => $changes) {
            foreach (['quotes', 'nested', 'third'] as $key) {
                if (isset($changes[$key]) && (!is_array($changes[$key]) || count($changes[$key]) !== 2)) {
                    throw new InvalidArgumentException("Variant “{$name}” of “{$data['id']}” needs two marks in “{$key}”.");
                }
            }
        }
        return new self($data);
    }

    public static function fromFile(string $path): self
    {
        $data = require $path;
        if (!is_array($data)) {
            throw new InvalidArgumentException("Locale file {$path} does not return an array.");
        }
        return self::fromArray($data);
    }

    public function id(): string
    {
        return $this->data['id'];
    }

    public function aliases(): array
    {
        return $this->data['aliases'] ?? [];
    }

    public function variants(): array
    {
        return array_map('strval', array_keys($this->data['variants'] ?? []));
    }

    public function withVariant(string $name): LocaleInterface
    {
        $changes = $this->data['variants'][$name] ?? null;
        if (!is_array($changes)) {
            return $this;
        }
        return new self(array_replace($this->data, array_intersect_key($changes, array_flip(['quotes', 'nested', 'third', 'apostrophe', 'dash', 'spacing']))));
    }

    /** This locale with a space inside its guillemets (a preset’s choice), keeping every other key. */
    public function withGuillemetSpace(string $space): self
    {
        return new self(array_replace($this->data, ['spacing' => array_replace($this->data['spacing'] ?? [], ['guillemets' => $space])]));
    }

    public function quotes(): array
    {
        return $this->data['quotes'];
    }

    public function nestedQuotes(): array
    {
        return $this->data['nested'];
    }

    /**
     * Pair for a third level, [opening, closing], or null when the locale has none. With one,
     * a quotation three deep takes it, and deeper ones alternate the nested pair and this one.
     */
    public function thirdQuotes(): ?array
    {
        return $this->data['third'] ?? null;
    }

    public function apostrophe(): string
    {
        return $this->data['apostrophe'];
    }

    public function dash(): array
    {
        return $this->data['dash'];
    }

    public function spacing(): array
    {
        return $this->data['spacing'] ?? [];
    }
}
