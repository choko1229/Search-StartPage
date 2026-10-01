<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

final class SyncDocument
{
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
            if($key!=='settings')foreach($collection as $id=>$item){
                if(!is_object($item) || !is_string($item->id??null) || $item->id!== (string)$id || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',(string)$id))throw new HttpException(422,'INVALID_INPUT');
                if(in_array($key,['favorites','providers-web','providers-ai'],true)){
                    $url=$item->url??null;
                    if(!is_string($url) || !filter_var(str_replace('{query}','test',$url),FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url,PHP_URL_SCHEME)??''),['http','https'],true))throw new HttpException(422,'INVALID_INPUT');
                }
            }
        }
        $walk($document,0);return $document;
    }
}
