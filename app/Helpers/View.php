<?php
declare(strict_types=1);

namespace App\Helpers;

final class View
{
    public function __construct(private readonly string $root, private readonly Translator $translator)
    {
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function render(string $name, array $data = []): string
    {
        if (!preg_match('/^[a-z-]+$/D', $name)) {
            throw new \InvalidArgumentException('Invalid view');
        }
        $t = $this->translator;
        $e = self::escape(...);
        ob_start();
        try {
            require $this->root . '/app/Views/' . $name . '.php';
            $content = ob_get_clean();
        } catch (\Throwable $error) {
            ob_end_clean();
            throw $error;
        }
        ob_start();
        require $this->root . '/app/Views/layout.php';
        return ob_get_clean();
    }
}
