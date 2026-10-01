<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;

final class SyncRepository
{
    public function __construct(private readonly PDO $pdo) {}
    public function read(int $userId): array
    {
        $statement=$this->pdo->prepare('SELECT version,document,updated_at FROM sync_states WHERE user_id=?');
        $statement->execute([$userId]);$row=$statement->fetch(PDO::FETCH_ASSOC);
        return $row ? ['version'=>(int)$row['version'],'document'=>json_decode($row['document'],false,32,JSON_THROW_ON_ERROR),'updated_at'=>$row['updated_at']]
            : ['version'=>0,'document'=>(object)[],'updated_at'=>null];
    }
    public function write(int $userId,int $expected,object $document): ?array
    {
        $this->pdo->beginTransaction();
        try {
            // Serialize the first write as well as later compare-and-swap updates.
            $this->pdo->prepare("INSERT IGNORE INTO sync_states (user_id,version,document,updated_at) VALUES (?,0,'{}',UTC_TIMESTAMP())")->execute([$userId]);
            $statement=$this->pdo->prepare('UPDATE sync_states SET version=version+1,document=?,updated_at=UTC_TIMESTAMP() WHERE user_id=? AND version=?');
            $statement->execute([json_encode($document,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$userId,$expected]);
            if ($statement->rowCount()!==1) {$this->pdo->rollBack();return null;}
            $result=$this->read($userId);$this->pdo->commit();return $result;
        } catch(\Throwable $error) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}
