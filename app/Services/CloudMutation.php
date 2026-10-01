<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;
final class CloudMutation
{
    private static function uuid(): string
    {
        $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);$hex=bin2hex($bytes);
        return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20);
    }
    public static function apply(object $document,string $collection,string $action,?string $id,?object $input): object
    {
        $doc=json_decode(json_encode($document,JSON_THROW_ON_ERROR),false,32,JSON_THROW_ON_ERROR);
        if($collection==='settings') {
            if($action!=='update' || $input===null)throw new HttpException(422,'INVALID_INPUT');
            $doc->settings=$input;return SyncDocument::validate($doc);
        }
        if(!in_array($collection,['favorites','favorite-folders','history','providers-web','providers-ai'],true))throw new \LogicException('Unknown cloud collection');
        $doc->$collection??=(object)[];$items=$doc->$collection;
        if($action==='create') {
            if($input===null)throw new HttpException(422,'INVALID_INPUT');
            $id=$input->id??self::uuid();
        }
        if(!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$id) || in_array($id,['__proto__','constructor','prototype'],true))throw new HttpException(422,'INVALID_INPUT');
        if($action==='create' && isset($items->$id))throw new HttpException(409,'ENTITY_EXISTS');
        if($action!=='create' && !isset($items->$id))throw new HttpException(404,'NOT_FOUND');
        $now=(int)floor(microtime(true)*1000);
        if($action==='delete') {
            if($collection==='favorite-folders')foreach($doc->favorites??[] as $favorite)if(($favorite->folderId??null)===$id) {$favorite->folderId=null;$favorite->updatedAt=$now;}
            unset($items->$id);
        } elseif($action==='open') {
            if($collection!=='favorites')throw new \LogicException('Unsupported open action');
            if(($doc->settings->favoriteStats??true)!==false) {
                $items->$id->usageCount=($items->$id->usageCount??0)+1;$items->$id->lastAccess=$now;$items->$id->updatedAt=$now;
            }
        } else {
            if(!in_array($action,['create','update'],true) || $input===null)throw new HttpException(422,'INVALID_INPUT');
            if(property_exists($input,'id') && $input->id!==$id)throw new HttpException(422,'INVALID_INPUT');
            $item=(object)array_replace((array)($items->$id??(object)[]),(array)$input,['id'=>$id]);
            if($action==='create') {
                $defaults=match($collection) {
                    'favorites'=>['folderId'=>null,'tags'=>[],'icon'=>'','color'=>'#304fc3','description'=>'','shortcut'=>'','pinned'=>false,'visible'=>true,'sortOrder'=>$now,'usageCount'=>0,'lastAccess'=>null,'createdAt'=>$now],
                    'favorite-folders'=>['sortOrder'=>count((array)$items)],
                    'history'=>['at'=>$now],
                    default=>['icon'=>'','enabled'=>true,'sortOrder'=>count((array)$items)],
                };
                $item=(object)((array)$item+$defaults);
            }
            if($collection==='favorites')$item->updatedAt=$now;
            $items->$id=$item;
        }
        return SyncDocument::validate($doc);
    }
}
