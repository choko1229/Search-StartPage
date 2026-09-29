<?php
declare(strict_types=1);

namespace App\Helpers;

final class Translator
{
    private array $messages;
    public readonly string $locale;

    public function __construct(string $root, string $locale)
    {
        $this->locale = in_array($locale, ['ja', 'en'], true) ? $locale : 'en';
        $this->messages = require $root . '/lang/' . $this->locale . '.php';
    }

    public function get(string $key): string
    {
        return $this->messages[$key] ?? $key;
    }

    public function messages(): array
    {
        return $this->messages;
    }

    public static function preferred(string $header): string
    {
        $choices = [];
        foreach (explode(',', $header) as $item) {
            if (preg_match('/^\s*(ja|en)(?:-[a-zA-Z]+)?(?:;q=(0(?:\.\d+)?|1(?:\.0+)?))?\s*$/i', $item, $match)) {
                $choices[] = [strtolower($match[1]), isset($match[2]) ? (float) $match[2] : 1.0];
            }
        }
        usort($choices, static fn ($a, $b) => $b[1] <=> $a[1]);
        return ($choices[0][1] ?? 0) > 0 ? $choices[0][0] : 'en';
    }
}
