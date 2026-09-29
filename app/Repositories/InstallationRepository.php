<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class InstallationRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function reserveAdministrator(string $discordId): void
    {
        $existing = $this->pdo->query('SELECT discord_id FROM installation_claims')->fetchColumn();
        if ($existing !== false && $existing !== $discordId) {
            throw new \RuntimeException('Installation already belongs to another administrator');
        }
        $statement = $this->pdo->prepare('INSERT INTO installation_claims (discord_id, created_at) VALUES (?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE discord_id = VALUES(discord_id)');
        $statement->execute([$discordId]);
    }
}
