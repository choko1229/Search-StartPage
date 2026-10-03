<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;

final class LogFilters
{
    public const TYPES=['php_error','api_error','oauth_error','sync_error','update_error','admin_audit','security'];

    public static function parse(array $query,bool $auditOnly=false): array
    {
        $filters=[];
        foreach (['type'=>20,'from'=>10,'to'=>10,'user_id'=>19,'error_code'=>80,'q'=>100] as $key=>$length) {
            $value=$query[$key] ?? '';
            if (!is_string($value) || !mb_check_encoding($value,'UTF-8') || mb_strlen($value)>$length || preg_match('/[\x00-\x1f\x7f]/u',$value)) throw new HttpException(422,'INVALID_INPUT');
            $filters[$key]=trim($value);
        }
        if ($filters['type']!=='' && !in_array($filters['type'],self::TYPES,true)) throw new HttpException(422,'INVALID_INPUT');
        if ($auditOnly) $filters['type']='admin_audit';
        foreach (['from','to'] as $key) {
            if ($filters[$key]==='') continue;
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$filters[$key],new \DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d')!==$filters[$key] || $filters[$key]<'1000-01-01') throw new HttpException(422,'INVALID_INPUT');
        }
        if ($filters['from']!=='' && $filters['to']!=='' && $filters['from']>$filters['to']) throw new HttpException(422,'INVALID_INPUT');
        if ($filters['user_id']!=='' && (!preg_match('/^[1-9][0-9]{0,17}$/D',$filters['user_id']))) throw new HttpException(422,'INVALID_INPUT');
        foreach (['page'=>1000000,'page_size'=>100] as $key=>$max) {
            $value=$query[$key] ?? ($key==='page' ? '1' : '25');
            if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,6}$/D',$value) || (int)$value>$max) throw new HttpException(422,'INVALID_INPUT');
            $filters[$key]=(int)$value;
        }
        return $filters;
    }
}
