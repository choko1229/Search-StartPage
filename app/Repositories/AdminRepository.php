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

    public function users(string $search, int $page, int $pageSize, bool $storage = false): array
    {
        // LOCATE treats %, _ and SQL fragments as literal search text.
        $where = "WHERE (?='' OR LOCATE(?,u.discord_id)>0 OR LOCATE(?,u.discord_username)>0 OR LOCATE(?,COALESCE(u.discord_display_name,''))>0)";
        $parameters = [$search,$search,$search,$search];
        $count = $this->pdo->prepare('SELECT COUNT(*) FROM users u '.$where);
        $count->execute($parameters);
        $total = (int)$count->fetchColumn();
        $order = $storage ? 'stored_background_bytes DESC,u.id DESC' : 'u.id DESC';
        $query = $this->pdo->prepare("SELECT u.id,u.discord_id,u.discord_username,u.discord_display_name,u.locale,u.created_at,u.last_login_at,
            COALESCE(a.admin_flag,0) AS admin_flag,COALESCE(b.background_count,0) AS background_count,COALESCE(b.background_bytes,0) AS background_bytes,
            COALESCE(b.stored_background_bytes,0) AS stored_background_bytes,COALESCE(b.archived_background_bytes,0) AS archived_background_bytes,COALESCE(b.stored_background_count,0) AS stored_background_count
            FROM users u LEFT JOIN administrators a ON a.user_id=u.id
            LEFT JOIN (SELECT user_id,SUM(deleted=0) AS background_count,SUM(CASE WHEN deleted=0 THEN file_size ELSE 0 END) AS background_bytes,
                SUM(file_size) AS stored_background_bytes,SUM(CASE WHEN deleted=1 THEN file_size ELSE 0 END) AS archived_background_bytes,COUNT(*) AS stored_background_count
                FROM backgrounds GROUP BY user_id) b ON b.user_id=u.id
            $where ORDER BY $order LIMIT ? OFFSET ?");
        foreach ($parameters as $index=>$value) $query->bindValue($index+1,$value,\PDO::PARAM_STR);
        $query->bindValue(5,$pageSize,\PDO::PARAM_INT);
        $query->bindValue(6,($page-1)*$pageSize,\PDO::PARAM_INT);
        $query->execute();
        $items = $query->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($items as &$item) {
            foreach (['id','admin_flag','background_count','background_bytes','stored_background_bytes','archived_background_bytes','stored_background_count'] as $key) $item[$key]=(int)$item[$key];
        }
        return ['items'=>$items,'total'=>$total,'page'=>$page,'page_size'=>$pageSize,'search'=>$search];
    }

    public function storageTotals():array
    {
        $query=$this->pdo->prepare('SELECT COUNT(*) AS stored_background_count,COALESCE(SUM(file_size),0) AS stored_background_bytes,
            COALESCE(SUM(CASE WHEN deleted=0 THEN file_size ELSE 0 END),0) AS active_background_bytes,
            COALESCE(SUM(CASE WHEN deleted=1 THEN file_size ELSE 0 END),0) AS archived_background_bytes FROM backgrounds');
        $query->execute();return array_map(static fn($value):int=>(int)$value,$query->fetch(\PDO::FETCH_ASSOC));
    }
}
