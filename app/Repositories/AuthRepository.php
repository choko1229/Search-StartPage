<?php
declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class AuthRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function upsertIdentity(array $identity, string $locale): int
    {
        $this->pdo->beginTransaction();
        try {
            $statement = $this->pdo->prepare('INSERT INTO users
                (discord_id,discord_username,discord_display_name,discord_avatar,locale,created_at,updated_at,last_login_at)
                VALUES (?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP(),UTC_TIMESTAMP())
                ON DUPLICATE KEY UPDATE discord_username=VALUES(discord_username),discord_display_name=VALUES(discord_display_name),
                discord_avatar=VALUES(discord_avatar),updated_at=UTC_TIMESTAMP(),last_login_at=UTC_TIMESTAMP()');
            $statement->execute([$identity['id'],$identity['username'],$identity['display_name'],$identity['avatar'],$locale]);
            $statement = $this->pdo->prepare('SELECT id FROM users WHERE discord_id=?');
            $statement->execute([$identity['id']]);
            $id = (int) $statement->fetchColumn();
            $claim = $this->pdo->prepare('SELECT claimed_at FROM installation_claims WHERE discord_id=? FOR UPDATE');
            $claim->execute([$identity['id']]);
            $row = $claim->fetch(PDO::FETCH_ASSOC);
            if ($row && $row['claimed_at'] === null) {
                $this->pdo->prepare('INSERT INTO administrators (user_id,created_at,updated_at) VALUES (?,UTC_TIMESTAMP(),UTC_TIMESTAMP())')->execute([$id]);
                $this->pdo->prepare('UPDATE installation_claims SET claimed_at=UTC_TIMESTAMP() WHERE discord_id=?')->execute([$identity['id']]);
            }
            $this->pdo->commit();
            return $id;
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function createDevice(int $userId, string $deviceId, string $tokenHash, array $agent, int $now): void
    {
        $this->pdo->beginTransaction();
        try {
            $date = gmdate('Y-m-d H:i:s', $now);
            $this->pdo->prepare('INSERT INTO devices (id,user_id,name,browser,os,created_at,last_active_at) VALUES (?,?,?,?,?,?,?)')
                ->execute([$deviceId,$userId,$agent['browser'].' / '.$agent['os'],$agent['browser'],$agent['os'],$date,$date]);
            $this->pdo->prepare('INSERT INTO login_tokens (device_id,token_hash,expires_at,created_at) VALUES (?,?,?,?)')
                ->execute([$deviceId,$tokenHash,gmdate('Y-m-d H:i:s',$now+90*86400),$date]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    public function authenticate(string $deviceId, string $hash, int $now): ?array
    {
        $this->pdo->beginTransaction();
        try {
        $statement = $this->pdo->prepare('SELECT d.user_id,t.token_hash,t.expires_at FROM devices d JOIN login_tokens t ON t.device_id=d.id WHERE d.id=? FOR UPDATE');
        $statement->execute([$deviceId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$row || $row['expires_at'] <= gmdate('Y-m-d H:i:s',$now) || !hash_equals($row['token_hash'],$hash)) { $this->pdo->commit(); return null; }
        // Conditional update prevents a concurrent revoke from resurrecting authentication.
        $statement = $this->pdo->prepare('UPDATE login_tokens SET expires_at=? WHERE device_id=? AND token_hash=? AND expires_at>?');
        $statement->execute([gmdate('Y-m-d H:i:s',$now+90*86400),$deviceId,$hash,gmdate('Y-m-d H:i:s',$now)]);
        $this->pdo->prepare('UPDATE devices SET last_active_at=? WHERE id=?')->execute([gmdate('Y-m-d H:i:s',$now),$deviceId]);
        $this->pdo->commit();
        return $row;
        } catch (\Throwable $error) { $this->pdo->rollBack(); throw $error; }
    }

    public function user(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT id,discord_id,discord_username,discord_display_name,discord_avatar FROM users WHERE id=?');
        $statement->execute([$id]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function devices(int $userId): array
    {
        $statement = $this->pdo->prepare('SELECT d.*,t.expires_at FROM devices d JOIN login_tokens t ON t.device_id=d.id WHERE d.user_id=? ORDER BY d.last_active_at DESC');
        $statement->execute([$userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rename(int $userId, string $id, string $name): void
    {
        $this->pdo->prepare('UPDATE devices SET name=? WHERE user_id=? AND id=?')->execute([$name,$userId,$id]);
    }

    public function revoke(int $userId, string $id): void
    {
        $this->pdo->prepare('DELETE FROM devices WHERE user_id=? AND id=?')->execute([$userId,$id]);
    }
}
