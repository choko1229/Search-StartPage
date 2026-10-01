<?php
declare(strict_types=1);
namespace App\Repositories;
use PDO;

// The sync document is the canonical payload. Relational rows provide the
// specified entity tables and their owner/folder/tag constraints in the same commit.
final class SyncProjectionRepository
{
    private const TABLES=['favorite-folders'=>'favorite_folders','favorites'=>'favorites','history'=>'search_history','providers-web'=>'search_engines','providers-ai'=>'ai_providers'];
    public function __construct(private readonly PDO $pdo) {}
    private function id(int $user,string $type,string $client): string
    {
        $hash=substr(hash('sha256',$user.'|'.$type.'|'.$client),0,32);
        return substr($hash,0,8).'-'.substr($hash,8,4).'-'.substr($hash,12,4).'-'.substr($hash,16,4).'-'.substr($hash,20,12);
    }
    private function rows(string $table,int $user): array
    {
        $stmt=$this->pdo->prepare("SELECT * FROM $table WHERE user_id=?");$stmt->execute([$user]);return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    private static function milliseconds(?string $value): ?int {return $value===null?null:strtotime($value.' UTC')*1000;}
    private static function date(int|float|null $value): ?string {return $value===null?null:gmdate('Y-m-d H:i:s',(int)floor($value/1000));}
    public function read(int $user): object
    {
        $doc=(object)['settings'=>(object)[]];
        foreach($this->rows('user_settings',$user) as $row)$doc->settings->{$row['setting_key']}=json_decode($row['setting_value'],false,32,JSON_THROW_ON_ERROR);
        $folders=[];
        foreach($this->rows('favorite_folders',$user) as $row)$folders[$row['id']]=$row['client_id'];
        $tags=$this->pdo->prepare('SELECT ft.favorite_id,t.name FROM favorite_tags ft JOIN tags t ON t.id=ft.tag_id AND t.user_id=ft.user_id WHERE ft.user_id=? ORDER BY t.name');
        $tags->execute([$user]);$tagNames=[];
        foreach($tags->fetchAll(PDO::FETCH_ASSOC) as $tag)$tagNames[$tag['favorite_id']][]=$tag['name'];
        foreach(self::TABLES as $key=>$table) {
            $doc->$key=(object)[];
            foreach($this->rows($table,$user) as $row) {
                $client=$row['client_id'];
                if($row['payload']!==null) $item=json_decode($row['payload'],false,32,JSON_THROW_ON_ERROR);
                else $item=(object) match($key) {
                    'favorite-folders'=>['id'=>$client,'name'=>$row['name'],'sortOrder'=>(int)$row['sort_order']],
                    'favorites'=>['id'=>$client,'name'=>$row['name'],'url'=>$row['url'],'folderId'=>$folders[$row['folder_id']??'']??null,'tags'=>$tagNames[$row['id']]??[],
                        'icon'=>$row['icon']??'','color'=>$row['color']??'#304fc3','description'=>$row['description']??'','shortcut'=>$row['shortcut']??'',
                        'pinned'=>(bool)$row['pinned'],'visible'=>(bool)$row['visible'],'sortOrder'=>(int)$row['sort_order'],'usageCount'=>(int)$row['usage_count'],
                        'lastAccess'=>self::milliseconds($row['last_access_at']),'createdAt'=>self::milliseconds($row['created_at']),'updatedAt'=>self::milliseconds($row['updated_at'])],
                    'history'=>['id'=>$client,'query'=>$row['query'],'provider'=>$row['provider'],'mode'=>$row['mode'],'at'=>self::milliseconds($row['created_at'])],
                    default=>['id'=>$client,'name'=>$row['name'],'url'=>$row[$key==='providers-web'?'search_url':'query_url'],'icon'=>$row['icon'],'prefix'=>$row['prefix'],'enabled'=>(bool)$row['enabled'],'sortOrder'=>(int)$row['sort_order']],
                };
                $doc->$key->$client=$item;
            }
        }
        return $doc;
    }
    private function insert(string $table,array $values): void
    {
        $columns=implode(',',array_keys($values));$marks=implode(',',array_fill(0,count($values),'?'));
        $this->pdo->prepare("INSERT INTO $table ($columns) VALUES ($marks)")->execute(array_values($values));
    }
    public function replace(int $user,object $previous,object $doc,int $version): void
    {
        if(!$this->pdo->inTransaction())throw new \LogicException('Projection requires sync transaction');
        $oldFolders=[];
        foreach($this->rows('favorite_folders',$user) as $row)$oldFolders[$row['client_id']]=$row;
        foreach(['favorite_tags','favorites','tags','favorite_folders','search_history','search_engines','ai_providers','user_settings'] as $table)$this->pdo->prepare("DELETE FROM $table WHERE user_id=?")->execute([$user]);
        $now=gmdate('Y-m-d H:i:s');
        foreach($doc->settings??[] as $key=>$value)$this->insert('user_settings',['id'=>$this->id($user,'settings',$key),'user_id'=>$user,'setting_key'=>$key,'setting_value'=>json_encode($value,JSON_THROW_ON_ERROR),'updated_at'=>$now]);
        $tagIds=[];
        foreach(self::TABLES as $key=>$table)foreach($doc->$key??[] as $client=>$item) {
            $id=$this->id($user,$key,(string)$client);
            $fields=['id'=>$id,'user_id'=>$user,'client_id'=>$client,'payload'=>json_encode($item,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)];
            $fields+=match($key) {
                'favorite-folders'=>['name'=>$item->name,'sort_order'=>$item->sortOrder??0,'created_at'=>$oldFolders[$client]['created_at']??$now,'updated_at'=>$now],
                'favorites'=>['folder_id'=>empty($item->folderId)?null:$this->id($user,'favorite-folders',$item->folderId),'name'=>$item->name,'url'=>$item->url,
                    'icon'=>$item->icon??'','color'=>$item->color??null,'description'=>$item->description??'','shortcut'=>$item->shortcut??'',
                    'pinned'=>(int)($item->pinned??false),'visible'=>(int)($item->visible??true),'sort_order'=>$item->sortOrder??0,
                    'usage_count'=>$item->usageCount??0,'last_access_at'=>self::date($item->lastAccess??null),
                    'created_at'=>self::date($item->createdAt??null)??$now,'updated_at'=>self::date($item->updatedAt??null)??$now],
                'history'=>['query'=>$item->query,'provider'=>$item->provider,'mode'=>$item->mode,'created_at'=>self::date($item->at)],
                default=>['name'=>$item->name,($key==='providers-web'?'search_url':'query_url')=>$item->url,'icon'=>$item->icon??'','prefix'=>$item->prefix,
                    'enabled'=>(int)($item->enabled??true),'sort_order'=>$item->sortOrder??0],
            };
            $this->insert($table,$fields);
            if($key==='favorites')foreach($item->tags??[] as $name) {
                $folded=mb_strtolower($name);
                if(!isset($tagIds[$folded])) {
                    $tagIds[$folded]=$this->id($user,'tags',$folded);
                    $this->insert('tags',['id'=>$tagIds[$folded],'user_id'=>$user,'name'=>$name]);
                }
                $this->insert('favorite_tags',['favorite_id'=>$id,'tag_id'=>$tagIds[$folded],'user_id'=>$user]);
            }
        }
        foreach(array_unique([...array_keys((array)$previous),...array_keys((array)$doc)]) as $type) {
            $before=(array)($previous->$type??(object)[]);$after=(array)($doc->$type??(object)[]);
            foreach(array_unique([...array_keys($before),...array_keys($after)]) as $entity) {
                if(array_key_exists($entity,$before) && array_key_exists($entity,$after) && json_encode($before[$entity])===json_encode($after[$entity]))continue;
                $this->pdo->prepare('INSERT INTO sync_versions (id,user_id,entity_type,entity_id,version,updated_at) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE version=VALUES(version),updated_at=VALUES(updated_at)')
                    ->execute([$this->id($user,'version:'.$type,(string)$entity),$user,$type,$entity,$version,$now]);
            }
        }
    }
}
