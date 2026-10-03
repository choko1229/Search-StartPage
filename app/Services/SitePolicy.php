<?php
declare(strict_types=1);
namespace App\Services;
use App\Config;
use App\Http\HttpException;

final class SitePolicy
{
    public const FLAGS=['cloud_sync','background_uploads','weather','external_suggestions','favorite_metadata'];
    public const LIMITS=['background_max_bytes'=>[0,9007199254740990],'login_attempts'=>[1,1000],'login_window_seconds'=>[1,86400]];

    public static function validate(mixed $value): array
    {
        if (is_object($value)) $value=(array)$value;
        if (!is_array($value) || array_diff(array_keys($value),['flags','limits']) || !isset($value['flags'],$value['limits'])) throw new HttpException(422,'INVALID_INPUT');
        $flags=is_object($value['flags']) ? (array)$value['flags'] : $value['flags'];
        $limits=is_object($value['limits']) ? (array)$value['limits'] : $value['limits'];
        if (!is_array($flags) || !is_array($limits) || count($flags)!==count(self::FLAGS) || count($limits)!==count(self::LIMITS)) throw new HttpException(422,'INVALID_INPUT');
        foreach(self::FLAGS as $key) if(!array_key_exists($key,$flags)||!is_bool($flags[$key]))throw new HttpException(422,'INVALID_INPUT');
        foreach(self::LIMITS as $key=>[$min,$max]) if(!array_key_exists($key,$limits)||($limits[$key]!==null && (!is_int($limits[$key])||$limits[$key]<$min||$limits[$key]>$max)))throw new HttpException(422,'INVALID_INPUT');
        return ['flags'=>$flags,'limits'=>$limits];
    }
    public static function effective(array $value,Config $config): array
    {
        $value=self::validate($value);
        $defaults=['background_max_bytes'=>max(0,(int)$config->get('backgrounds.max_bytes',0)),'login_attempts'=>max(1,(int)$config->get('login_rate_limit.attempts',20)),
            'login_window_seconds'=>max(1,(int)$config->get('login_rate_limit.window_seconds',60))];
        foreach($defaults as $key=>$default)$value['limits'][$key] ??= $default;
        return $value;
    }
}
