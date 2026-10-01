<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;
final class SyncMerge
{
    private object $missing;
    private array $conflicts=[];
    public function __construct() {$this->missing=new \stdClass();}
    private function equal(mixed $a,mixed $b): bool
    {
        if($a===$this->missing || $b===$this->missing)return $a===$b;
        if(is_object($a) && is_object($b)) {
            if(count((array)$a)!==count((array)$b))return false;
            foreach($a as $key=>$value)if(!property_exists($b,(string)$key) || !$this->equal($value,$b->$key))return false;
            return true;
        }
        if(is_array($a) && is_array($b)) {
            if(count($a)!==count($b))return false;
            foreach($a as $key=>$value)if(!array_key_exists($key,$b) || !$this->equal($value,$b[$key]))return false;
            return true;
        }
        if((is_int($a) || is_float($a)) && (is_int($b) || is_float($b)))return $a==$b;
        return $a===$b;
    }
    private function choice(mixed $value): array {return ['present'=>$value!==$this->missing,'value'=>$value===$this->missing?null:$value];}
    private function read(mixed $value,string $key): mixed {return is_object($value) && $value!==$this->missing && property_exists($value,$key)?$value->$key:$this->missing;}
    private function node(mixed $previous,mixed $local,mixed $cloud,array $path,object $choices): mixed
    {
        if($this->equal($local,$cloud))return $local;
        if($this->equal($local,$previous))return $cloud;
        if($this->equal($cloud,$previous))return $local;
        if(is_object($local) && $local!==$this->missing && is_object($cloud) && $cloud!==$this->missing && (is_object($previous))) {
            $result=(object)[];$keys=array_unique([...array_keys((array)$previous),...array_keys((array)$local),...array_keys((array)$cloud)]);
            foreach($keys as $key) {
                $key=(string)$key;$value=$this->node($this->read($previous,$key),$this->read($local,$key),$this->read($cloud,$key),[...$path,$key],$choices);
                if($value!==$this->missing)$result->$key=$value;
            }
            return $result;
        }
        $id=json_encode($path,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $choice=$choices->$id??null;
        if($choice==='local')return $local;if($choice==='cloud')return $cloud;
        $this->conflicts[]=['id'=>$id,'path'=>$path,'previous'=>$this->choice($previous),'local'=>$this->choice($local),'cloud'=>$this->choice($cloud)];return $local;
    }
    public function merge(object $previous,object $local,object $cloud,object $choices): array
    {
        foreach($choices as $choice)if(!in_array($choice,['local','cloud'],true))throw new HttpException(422,'INVALID_INPUT');
        $this->conflicts=[];$result=$this->node($previous,$local,$cloud,[],$choices);
        return ['document'=>$result,'conflicts'=>$this->conflicts];
    }
}
