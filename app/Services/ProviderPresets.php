<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

final class ProviderPresets
{
    public static function defaults(): array {return self::validate(require dirname(__DIR__,2).'/config/providers.php');}
    public static function client(array $presets):array
    {
        foreach($presets as &$rows)foreach($rows as &$row){$row['sortOrder']=$row['sort_order'];unset($row['sort_order']);}unset($rows,$row);
        return $presets;
    }
    public static function validate(mixed $value): array
    {
        if(is_object($value))$value=(array)$value;
        if(!is_array($value)||array_diff(array_keys($value),['web','ai'])||!isset($value['web'],$value['ai']))throw new HttpException(422,'INVALID_INPUT');
        $result=[];$ids=[];$prefixes=[];
        foreach(['web','ai'] as $mode){
            if(!is_array($value[$mode])||!array_is_list($value[$mode])||count($value[$mode])<1||count($value[$mode])>50)throw new HttpException(422,'INVALID_INPUT');
            $rows=[];
            foreach($value[$mode] as $index=>$row){
                if(is_object($row))$row=(array)$row;
                if(!is_array($row)||array_diff(array_keys($row),['id','name','url','prefix','icon','enabled','copy','sort_order']))throw new HttpException(422,'INVALID_INPUT');
                foreach(['id','name','url','prefix','icon'] as $field)if(!isset($row[$field])||!is_string($row[$field]))throw new HttpException(422,'INVALID_INPUT');
                if(!preg_match('/^[a-z0-9][a-z0-9_-]{0,35}$/D',$row['id'])||in_array($row['id'],['constructor','prototype'],true)||isset($ids[$row['id']])||trim($row['name'])===''||mb_strlen($row['name'])>100||mb_strlen($row['icon'])>32||!preg_match('/^[A-Za-z0-9_-]{1,32}$/D',$row['prefix'])||isset($prefixes[strtolower($row['prefix'])]))throw new HttpException(422,'INVALID_INPUT');
                $enabled=$row['enabled']??true;$copy=$row['copy']??false;$sort=$row['sort_order']??$index;
                if(!is_bool($enabled)||!is_bool($copy)||!is_int($sort)||$sort<0||$sort>9999)throw new HttpException(422,'INVALID_INPUT');
                $url=$row['url'];$parsed=parse_url(str_replace('{query}','test',$url));
                if(strlen($url)>2048||preg_match('/[\x00-\x20\x7f]/',$url)||!filter_var(str_replace('{query}','test',$url),FILTER_VALIDATE_URL)||!$parsed||!in_array(strtolower($parsed['scheme']??''),['https','http'],true)||empty($parsed['host'])||isset($parsed['user'])||isset($parsed['pass'])||(!str_contains($url,'{query}')&&!$copy)||($mode==='web'&&!str_contains($url,'{query}')))throw new HttpException(422,'INVALID_INPUT');
                $ids[$row['id']]=true;$prefixes[strtolower($row['prefix'])]=true;
                $rows[]=['id'=>$row['id'],'name'=>trim($row['name']),'url'=>$url,'prefix'=>$row['prefix'],'icon'=>$row['icon'],'enabled'=>$enabled,'copy'=>$copy,'sort_order'=>$sort];
            }
            if(!array_filter($rows,static fn($row)=>$row['enabled']))throw new HttpException(422,'INVALID_INPUT');
            usort($rows,static fn($a,$b)=>[$a['sort_order'],$a['id']]<=>[$b['sort_order'],$b['id']]);$result[$mode]=$rows;
        }
        return $result;
    }
}
