<?php

declare(strict_types=1);

namespace MinifyX;

final class Config
{
    /** @var array<string, mixed> */
    private array $data;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        $this->data = $data;
        $this->syncExtensions();
    }

    /**
     * @param array<string, mixed> $defaults
     * @param array<string, mixed> $overrides
     */
    public static function fromDefaults(array $defaults, array $overrides = []): self
    {
        return new self(array_merge($defaults, $overrides));
    }

    /**
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * @param mixed $value
     */
    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
        if (in_array($key, ['minifyJs', 'minifyCss', 'mangleJs'], true)) {
            $this->syncExtensions();
        }
    }

    /**
     * @param array<string, mixed> $values
     */
    public function merge(array $values): void
    {
        $this->data = array_merge($this->data, $values);
        $this->syncExtensions();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    public function syncExtensions(): void
    {
        $this->data['jsExt'] = !empty($this->data['minifyJs']) || !empty($this->data['mangleJs']) ? '.min.js' : '.js';
        $this->data['cssExt'] = !empty($this->data['minifyCss']) ? '.min.css' : '.css';
    }
}
