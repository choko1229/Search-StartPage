<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

final class BackgroundInput
{
    public static function validate(object $input,bool $uploaded=false): array
    {
        $id = $input->id ?? bin2hex(random_bytes(16));
        $name = $input->name ?? null; $type = $input->type ?? 'image';
        if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $id) || !is_string($name)
            || trim($name) === '' || mb_strlen($name) > 100 || !in_array($type, ['solid','gradient','image','video'], true)) self::invalid();
        $settings = [];
        foreach (['color'=>'#101723','colorEnd'=>'#304fc3','overlayColor'=>'#000000'] as $key=>$default) {
            $value = $input->$key ?? $default;
            if (!is_string($value) || !preg_match('/^#[a-fA-F0-9]{6}$/D', $value)) self::invalid();
            $settings[$key] = strtolower($value);
        }
        foreach (['angle'=>[135,0,360],'blur'=>[0,0,40],'brightness'=>[1,0,2],'overlay'=>[0,0,1],
            'scale'=>[1,1,2],'speed'=>[1,.25,4]] as $key=>[$default,$min,$max]) {
            $settings[$key] = self::number($input->$key ?? $default,$min,$max);
        }
        foreach (['position'=>['center','top','bottom','left','right'],'fit'=>['cover','contain','fill']] as $key=>$values) {
            $value = $input->$key ?? $values[0]; if (!in_array($value,$values,true)) self::invalid(); $settings[$key]=$value;
        }
        foreach (['fixed'=>true,'autoplay'=>true,'loop'=>true,'mute'=>true,'paused'=>false] as $key=>$default) {
            $value=property_exists($input,$key)?$input->$key:$default; if(!is_bool($value))self::invalid(); $settings[$key]=$value;
        }
        $settings['fallback'] = self::url($input->fallback ?? '', true);
        if (isset($input->rule)) { $nodes=0; self::rule($input->rule,0,$nodes); $settings['rule']=$input->rule; }
        $url = $uploaded ? '' : self::url($input->url ?? '', !in_array($type,['image','video'],true));
        $cloud=property_exists($input,'cloudSync')?$input->cloudSync:true; $deleted=property_exists($input,'deleted')?$input->deleted:false;
        if(!is_bool($cloud)||!is_bool($deleted))self::invalid();
        return ['id'=>$id,'name'=>trim($name),'type'=>$type,'url'=>$url,'cloudSync'=>$cloud,'deleted'=>$deleted,'settings'=>$settings];
    }
    public static function url(mixed $url,bool $optional=false): string
    {
        if ($optional && $url==='') return '';
        if (!is_string($url) || strlen($url)>2048 || preg_match('/[\x00-\x20\\\\]/',$url)) self::invalid();
        if(str_starts_with($url,'/')&&!str_starts_with($url,'//'))return $url;
        $parts=parse_url($url);
        if (!$parts || ($parts['scheme']??'')!=='https' || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || !filter_var($url,FILTER_VALIDATE_URL)) self::invalid();
        return $url;
    }
    private static function number(mixed $value,float $min,float $max): int|float
    {
        if ((!is_int($value)&&!is_float($value)) || !is_finite((float)$value) || $value<$min || $value>$max) self::invalid();
        return $value;
    }
    private static function date(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D',$value,$match)
            || !checkdate((int)$match[2],(int)$match[3],(int)$match[1])) self::invalid();
        return $value;
    }
    private static function rule(mixed $rule,int $depth,int &$nodes): void
    {
        if(!is_object($rule)||$depth>12||++$nodes>100)self::invalid();
        if(strlen(json_encode($rule,JSON_THROW_ON_ERROR))>32768)self::invalid();
        if(isset($rule->operator)) {
            if(isset($rule->type)||count(array_diff(array_keys((array)$rule),['operator','conditions'])))self::invalid();
            if(!in_array($rule->operator,['and','or'],true)||!is_array($rule->conditions??null)||!count($rule->conditions))self::invalid();
            foreach($rule->conditions as $child)self::rule($child,$depth+1,$nodes); return;
        }
        $allowed=match($rule->type??null) {
            'time','period'=>['type','start','end'], 'day'=>['type','days'], 'date'=>['type','date'],
            'weather'=>['type','values'], 'temperature'=>['type','min','max'], 'season','login','device'=>['type','value'],
            'random'=>['type','chance'], 'screen'=>['type','minWidth','maxWidth','minHeight','maxHeight'], default=>[],
        };
        if(count(array_diff(array_keys((array)$rule),$allowed)))self::invalid();
        switch($rule->type??null) {
        case 'time': foreach(['start','end'] as $key)if(!is_string($rule->$key??null)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$rule->$key))self::invalid(); break;
        case 'day': if(!is_array($rule->days??null)||!count($rule->days))self::invalid(); foreach($rule->days as $day)if(!is_int($day)||$day<0||$day>6)self::invalid(); break;
        case 'date': self::date($rule->date??null); break;
        case 'period': if(self::date($rule->start??null)>self::date($rule->end??null))self::invalid(); break;
        case 'weather': if(!is_array($rule->values??null)||!count($rule->values))self::invalid(); foreach($rule->values as $value)if(!in_array($value,['clear','cloudy','fog','rain','snow','storm'],true))self::invalid(); break;
        case 'temperature': if(self::number($rule->min??null,-100,100)>self::number($rule->max??null,-100,100))self::invalid(); break;
        case 'season': if(!in_array($rule->value??null,['spring','summer','autumn','winter'],true))self::invalid(); break;
        case 'random': self::number($rule->chance??null,0,1); break;
        case 'login': if(!is_bool($rule->value??null))self::invalid(); break;
        case 'device': if(!in_array($rule->value??null,['mobile','tablet','desktop'],true))self::invalid(); break;
        case 'screen': foreach(['Width','Height'] as $axis)if(self::number($rule->{'min'.$axis}??0,0,100000)>self::number($rule->{'max'.$axis}??100000,0,100000))self::invalid(); break;
        default: self::invalid();
        }
        if(strlen(json_encode($rule,JSON_THROW_ON_ERROR))>32768)self::invalid();
    }
    private static function invalid(): never { throw new HttpException(422,'INVALID_BACKGROUND'); }
}
