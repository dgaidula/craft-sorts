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
        foreach ($data['variants'] ?? [] as $name => $changes) {
            foreach (['quotes', 'nested'] as $key) {
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
        return new self(array_replace($this->data, array_intersect_key($changes, array_flip(['quotes', 'nested', 'apostrophe', 'dash', 'spacing']))));
    }

    public function quotes(): array
    {
        return $this->data['quotes'];
    }

    public function nestedQuotes(): array
    {
        return $this->data['nested'];
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
