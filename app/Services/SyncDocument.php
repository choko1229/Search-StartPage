<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

final class SyncDocument
{
    private static function invalid(): never {throw new HttpException(422,'INVALID_INPUT');}
    private static function text(object $item,string $key,int $max,bool $required=false): void
    {
        if(!property_exists($item,$key)) {if($required)self::invalid();return;}
        if(!is_string($item->$key) || mb_strlen($item->$key)>$max || ($required && trim($item->$key)===''))self::invalid();
    }
    private static function number(object $item,string $key,int $max=9007199254740991,bool $required=false,bool $nullable=false): void
    {
        if(!property_exists($item,$key)) {if($required)self::invalid();return;}
        if($nullable && $item->$key===null)return;
        if(!is_int($item->$key) || $item->$key<0 || $item->$key>$max)self::invalid();
    }
    private static function item(string $type,object $item): void
    {
        $fields=match($type) {
            'favorites'=>['id','name','url','folderId','tags','icon','color','description','shortcut','pinned','visible','sortOrder','usageCount','lastAccess','createdAt','updatedAt'],
            'favorite-folders'=>['id','name','sortOrder'],
            'history'=>['id','query','provider','mode','at'],
            default=>['id','name','url','prefix','icon','enabled','sortOrder','copy'],
        };
        foreach($item as $field=>$value)if(!in_array($field,$fields,true))self::invalid();
        if($type==='history') {
            self::text($item,'query',12000,true);self::text($item,'provider',100,true);
            if(!in_array($item->mode??null,['web','ai'],true))self::invalid();
            self::number($item,'at',253402300799000,true);return;
        }
        self::text($item,'name',100,true);self::number($item,'sortOrder');
        if($type==='favorite-folders')return;
        self::text($item,'url',2048,true);self::text($item,'icon',$type==='favorites'?2048:255);
        $url=str_replace('{query}','test',$item->url);
        $parts=parse_url($url);
        if(!filter_var($url,FILTER_VALIDATE_URL) || !in_array(strtolower($parts['scheme']??''),['http','https'],true) || isset($parts['user']) || isset($parts['pass']))self::invalid();
        foreach(['enabled','copy','pinned','visible'] as $key)if(property_exists($item,$key) && !is_bool($item->$key))self::invalid();
        if($type!=='favorites') {
            self::text($item,'prefix',32,true);
            if(!preg_match('/^[a-zA-Z0-9_-]{1,32}$/D',$item->prefix))self::invalid();return;
        }
        self::text($item,'description',2000);self::text($item,'shortcut',80);
        if(isset($item->color) && (!is_string($item->color) || !preg_match('/^#[a-fA-F0-9]{6}$/D',$item->color)))self::invalid();
        if(isset($item->folderId) && (!is_string($item->folderId) || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$item->folderId)))self::invalid();
        foreach(['createdAt','updatedAt','lastAccess'] as $key)self::number($item,$key,253402300799000,false,$key==='lastAccess');
        self::number($item,'usageCount');
        if(property_exists($item,'tags')) {
            if(!is_array($item->tags) || count($item->tags)>20)self::invalid();
            $seen=[];
            foreach($item->tags as $tag) {
                if(!is_string($tag) || trim($tag)==='' || mb_strlen($tag)>40 || isset($seen[mb_strtolower($tag)]))self::invalid();
                $seen[mb_strtolower($tag)]=true;
            }
        }
        if(!empty($item->shortcut)) {
            if(!preg_match('/^(?:(?:Alt|Control|Shift)\+){1,3}[a-z0-9]$/iD',$item->shortcut))self::invalid();
            $parts=explode('+',strtolower($item->shortcut));$key=array_pop($parts);
            if(count($parts)!==count(array_unique($parts)) || ($key==='k' && $parts===['control']))self::invalid();
        }
    }
    public static function validate(mixed $document): object
    {
        if(!is_object($document) || strlen(json_encode($document,JSON_THROW_ON_ERROR))>524288)throw new HttpException(422,'INVALID_INPUT');
        $allowed=['settings','favorites','favorite-folders','history','providers-web','providers-ai'];
        $nodes=0;
        $walk=static function(mixed $value,int $depth) use (&$walk,&$nodes): void {
            if(++$nodes>20000 || $depth>16)throw new HttpException(422,'INVALID_INPUT');
            if(is_string($value) && mb_strlen($value)>12000)throw new HttpException(422,'INVALID_INPUT');
            if(is_object($value) || is_array($value))foreach($value as $key=>$child){
                if(in_array((string)$key,['__proto__','constructor','prototype'],true))throw new HttpException(422,'INVALID_INPUT');
                $walk($child,$depth+1);
            }
        };
        foreach($document as $key=>$collection){
            if(!in_array($key,$allowed,true) || !is_object($collection))throw new HttpException(422,'INVALID_INPUT');
            if($key==='settings')foreach($collection as $setting=>$value) {
                if(!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$setting) || in_array($setting,['syncEnabled','syncHistory','clearSyncedOnLogout'],true))self::invalid();
                if(in_array($setting,['historyLimit','historyDays'],true) && (!is_int($value) || $value<1 || $value>($setting==='historyLimit'?10000:3650)))self::invalid();
                if($setting==='syncRules') {
                    if(!is_object($value))self::invalid();
                    foreach($value as $path=>$choice) {
                        if(!in_array($choice,['local','cloud'],true))self::invalid();
                        try {$segments=json_decode($path,true,20,JSON_THROW_ON_ERROR);}catch(\JsonException){self::invalid();}
                        if(!is_array($segments) || !array_is_list($segments) || count($segments)<1 || count($segments)>16)self::invalid();
                        foreach($segments as $segment)if(!is_string($segment) || strlen($segment)>1000 || in_array($segment,['__proto__','prototype','constructor'],true))self::invalid();
                    }
                }
            }
            $unique=[];
            if($key!=='settings')foreach($collection as $id=>$item){
                if(!is_object($item) || !is_string($item->id??null) || $item->id!== (string)$id || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',(string)$id))throw new HttpException(422,'INVALID_INPUT');
                self::item($key,$item);
                $uniqueKey=match($key) {'favorite-folders'=>mb_strtolower(trim($item->name)),'providers-web','providers-ai'=>strtolower($item->prefix),default=>null};
                if($uniqueKey!==null) {if(isset($unique[$uniqueKey]))self::invalid();$unique[$uniqueKey]=true;}
                if($key==='favorites' && !empty($item->folderId) && !isset($document->{'favorite-folders'}->{$item->folderId}))self::invalid();
                if($key==='favorites' && !empty($item->shortcut)) {
                    $parts=explode('+',strtolower($item->shortcut));$last=array_pop($parts);sort($parts);$shortcut=implode('+',$parts).'+'.$last;
                    if(isset($unique[$shortcut]))self::invalid();$unique[$shortcut]=true;
                }
            }
        }
        $walk($document,0);return $document;
    }
}
