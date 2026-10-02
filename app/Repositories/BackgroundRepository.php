<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;
use App\Http\HttpException;

final class BackgroundRepository
{
    public function __construct(private readonly PDO $pdo,private readonly int $limitBytes=0) {}
    public function list(int $user): array
    {
        $statement=$this->pdo->prepare('SELECT * FROM backgrounds WHERE user_id=? ORDER BY created_at,id');
        $statement->execute([$user]);return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
    public function find(int $user,string $id): ?array
    {
        $statement=$this->pdo->prepare('SELECT * FROM backgrounds WHERE user_id=? AND id=?');$statement->execute([$user,$id]);
        return $statement->fetch(PDO::FETCH_ASSOC)?:null;
    }
    public function usage(int $user): array
    {
        $statement=$this->pdo->prepare('SELECT COALESCE(SUM(file_size),0) FROM backgrounds WHERE user_id=?');$statement->execute([$user]);
        return ['used_bytes'=>(int)$statement->fetchColumn(),'limit_bytes'=>$this->limitBytes>0?$this->limitBytes:null];
    }
    public function uploadReceipt(int $user,string $requestId,string $fingerprint): ?array
    {
        $statement=$this->pdo->prepare('SELECT fingerprint,response_json FROM background_upload_receipts WHERE user_id=? AND request_id=?');
        $statement->execute([$user,$requestId]);$row=$statement->fetch(PDO::FETCH_ASSOC);
        if(!$row)return null;
        if(!hash_equals($row['fingerprint'],$fingerprint))throw new HttpException(409,'BACKGROUND_CONFLICT');
        return json_decode($row['response_json'],true,32,JSON_THROW_ON_ERROR)+['_upload_replayed'=>true];
    }
    public function save(int $user,array $item,?int $expected=null,?array $file=null,bool $clearFile=false,?array $receipt=null): array
    {
        $this->pdo->beginTransaction();
        try {
            // The owner row serializes file quota and metadata writes, including the first upload.
            $lock=$this->pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');$lock->execute([$user]);
            if(!$lock->fetchColumn())throw new HttpException(401,'AUTH_REQUIRED');
            if($receipt!==null) {
                $replayed=$this->uploadReceipt($user,$receipt['id'],$receipt['fingerprint']);
                if($replayed!==null){$this->pdo->commit();return $replayed;}
            }
            $before=$this->find($user,$item['id']);
            if($expected===null && $before)throw new HttpException(409,'BACKGROUND_CONFLICT');
            if($expected!==null && !$before)throw new HttpException(404,'NOT_FOUND');
            if($before && (int)$before['version']!==$expected)throw new HttpException(409,'BACKGROUND_CONFLICT');
            $size=$clearFile?0:($file['bytes']??($before['file_size']??0));
            if(!is_int($size))$size=(int)$size;
            if($size<0||$size>524288000)throw new HttpException(422,'INVALID_BACKGROUND');
            $usage=$this->usage($user)['used_bytes']-(int)($before['file_size']??0)+$size;
            if($this->limitBytes>0 && $usage>$this->limitBytes)throw new HttpException(413,'BACKGROUND_QUOTA_EXCEEDED');
            $settings=json_encode($item['settings'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
            $filename=$clearFile?null:($file['filename']??($before['file_path']??null));
            if($filename!==null && !preg_match('/^[a-f0-9]{48}\.(jpg|png|gif|webp|avif|mp4|webm)$/D',$filename))throw new HttpException(422,'INVALID_BACKGROUND');
            $type=$file['type']??$item['type']; $source=$filename!==null?'upload':'url';
            if($filename!==null && $file===null && $type!==$before['type'])throw new HttpException(422,'INVALID_BACKGROUND');
            $values=[$item['name'],$type,$source,$filename,$source==='url'?$item['url']:null,$size,
                $clearFile?null:($file['mime']??($before['mime']??null)),(int)$item['cloudSync'],$settings,(int)$item['deleted']];
            if($before) {
                $statement=$this->pdo->prepare('UPDATE backgrounds SET name=?,type=?,source_type=?,file_path=?,external_url=?,file_size=?,mime=?,cloud_sync=?,settings_json=?,deleted=?,version=version+1,updated_at=UTC_TIMESTAMP() WHERE user_id=? AND id=?');
                $statement->execute([...$values,$user,$item['id']]);
            } else {
                $statement=$this->pdo->prepare('INSERT INTO backgrounds (name,type,source_type,file_path,external_url,file_size,mime,cloud_sync,settings_json,deleted,user_id,id,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP())');
                $statement->execute([...$values,$user,$item['id']]);
            }
            $this->pdo->prepare('DELETE FROM background_rules WHERE user_id=? AND background_id=?')->execute([$user,$item['id']]);
            if(isset($item['settings']['rule']))$this->pdo->prepare('INSERT INTO background_rules (id,user_id,background_id,conditions_json,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP())')
                ->execute([$item['id'],$user,$item['id'],json_encode($item['settings']['rule'],JSON_THROW_ON_ERROR)]);
            $saved=$this->find($user,$item['id']);
            if($receipt!==null)$this->pdo->prepare('INSERT INTO background_upload_receipts (user_id,request_id,fingerprint,response_json,created_at) VALUES (?,?,?,?,UTC_TIMESTAMP())')
                ->execute([$user,$receipt['id'],$receipt['fingerprint'],json_encode($saved+['_upload_warning'=>$file['warning']??null],JSON_THROW_ON_ERROR)]);
            $this->pdo->commit();return $saved;
        } catch(\Throwable $error) {if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
}
