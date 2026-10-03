<?php
declare(strict_types=1);
namespace App\Services;

use App\Http\HttpException;

final class ReleaseCatalog
{
    public static function repository(string $repository): string
    {
        if(!preg_match('~^[A-Za-z0-9][A-Za-z0-9-]{0,38}/[A-Za-z0-9][A-Za-z0-9_.-]{0,99}$~D',$repository))throw new HttpException(422,'INVALID_UPDATE_REPOSITORY');
        return $repository;
    }
    public static function channel(string $channel,string $tag=''): void
    {
        if(!in_array($channel,['stable','beta','nightly','custom'],true)||($channel==='custom'&&!preg_match('~^[A-Za-z0-9][A-Za-z0-9._/-]{0,127}$~D',$tag)))throw new HttpException(422,'INVALID_UPDATE_CHANNEL');
    }
    private static function version(string $tag): ?array
    {
        if(!preg_match('/^v?(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(?:-([0-9A-Za-z.-]+))?(?:\+([0-9A-Za-z.-]+))?$/D',$tag,$match))return null;
        $pre=isset($match[4])&&$match[4]!==''?explode('.',$match[4]):[];
        foreach(array_merge($pre,isset($match[5])?explode('.',$match[5]):[]) as $part)if($part==='')return null;
        foreach($pre as $part)if(ctype_digit($part)&&strlen($part)>1&&$part[0]==='0')return null;
        return [array_slice($match,1,3),$pre];
    }
    private static function number(string $a,string $b): int{return strlen($a)<=>strlen($b) ?: strcmp($a,$b);}
    public static function compare(string $a,string $b): ?int
    {
        $left=self::version($a);$right=self::version($b);if(!$left||!$right)return null;
        foreach($left[0] as $i=>$part)if($difference=self::number($part,$right[0][$i]))return $difference;
        if(!$left[1]||!$right[1])return (int)!$left[1]<=>(int)!$right[1];
        foreach($left[1] as $i=>$part){
            if(!isset($right[1][$i]))return 1;$other=$right[1][$i];
            if(ctype_digit($part)&&ctype_digit($other))$difference=self::number($part,$other);
            elseif(ctype_digit($part)!==ctype_digit($other))$difference=ctype_digit($part)?-1:1;
            else $difference=strcmp($part,$other);
            if($difference)return $difference;
        }
        return count($left[1])<=>count($right[1]);
    }
    public static function select(array $releases,string $channel='stable',string $customTag=''): ?array
    {
        self::channel($channel,$customTag);$candidates=[];
        foreach($releases as $release){
            if(!is_array($release)||!is_int($release['id']??null)||$release['id']<1||!is_string($release['tag_name']??null)||strlen($release['tag_name'])>128||!is_bool($release['draft']??null)||!is_bool($release['prerelease']??null))throw new HttpException(502,'INVALID_UPDATE_RELEASE');
            if($release['draft'])continue;
            $tag=$release['tag_name'];$version=self::version($tag);
            $nightly=preg_match('/(?:^|[.\/-])(nightly|dev)(?:$|[.\/-])/i',$tag)===1;
            if($channel==='custom'){if($tag!==$customTag)continue;}
            elseif($channel==='stable'){if(!$version||$release['prerelease']||$version[1])continue;}
            elseif($channel==='beta'){if(!$version||$nightly)continue;}
            else {if(!$nightly)continue;}
            $published=$release['published_at']??null;
            if(!is_string($published)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$published))throw new HttpException(502,'INVALID_UPDATE_RELEASE');
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z',$published,new \DateTimeZone('UTC'));
            if(!$date||$date->format('Y-m-d\TH:i:s\Z')!==$published)throw new HttpException(502,'INVALID_UPDATE_RELEASE');
            $candidates[]=['id'=>$release['id'],'tag'=>$tag,'prerelease'=>$release['prerelease'],'published_at'=>$published];
        }
        usort($candidates,static function(array $a,array $b)use($channel):int{
            if(in_array($channel,['stable','beta'],true)&&($order=self::compare($b['tag'],$a['tag'])))return $order;
            return strcmp($b['published_at'],$a['published_at']) ?: $b['id']<=>$a['id'];
        });
        return $candidates[0]??null;
    }
}
