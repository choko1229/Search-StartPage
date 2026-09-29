<?php
declare(strict_types=1);

namespace App;

final class Config
{
    public function __construct(private readonly array $values)
    {
    }

    public static function load(string $root): self
    {
        $file = $root . '/config/config.php';
        return new self(require (is_file($file) ? $file : $root . '/config/config.example.php'));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }
}
