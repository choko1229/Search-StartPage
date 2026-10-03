<?php
declare(strict_types=1);
namespace App\Repositories;

final class AdminRepository
{
    public function __construct(private readonly \PDO $pdo) {}

    public function isAdministrator(int $userId): bool
    {
        $query = $this->pdo->prepare('SELECT user_id FROM administrators WHERE user_id=? AND admin_flag=1');
        $query->execute([$userId]);
        return $query->fetchColumn() !== false;
    }

    public function dashboard(): array
    {
        $query = $this->pdo->prepare('SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM administrators WHERE admin_flag=1) AS administrators, (SELECT COUNT(*) FROM backgrounds WHERE deleted=0) AS backgrounds, (SELECT COALESCE(SUM(file_size),0) FROM backgrounds WHERE deleted=0) AS background_bytes');
        $query->execute();
        return array_map(static fn($value): int => (int)$value, $query->fetch(\PDO::FETCH_ASSOC));
    }
}
