<?php
declare(strict_types=1);
namespace App\Services;

use App\Http\HttpException;
use App\Repositories\LogRepository;

/** A private durable queue bridges DB outages. Event IDs deduplicate DB replay. */
final class ApplicationLogger
{
    public function __construct(private readonly ?LogRepository $repository,private readonly FileLogger $files,private readonly string $queueDirectory) {}

    public static function classify(\Throwable $error,string $path): string
    {
        if (!$error instanceof HttpException) return 'php_error';
        if (in_array($error->status,[401,403,429],true)) return 'security';
        if (preg_match('~^/(?:api/)?auth/discord(?:/|$)~',$path)) return 'oauth_error';
        if (preg_match('~^/(?:api/)?admin/(?:update|rollback)(?:/|$)~',$path)) return 'update_error';
        if (preg_match('~^/api/(?:sync|settings|favorites|favorite-folders|search/history|search-engines|ai-providers|backgrounds)(?:/|$)~',$path)) return 'sync_error';
        return 'api_error';
    }

    public function record(\Throwable $error,string $path,string $requestId,?int $userId=null,string $method='GET'): string
    {
        $eventId=bin2hex(random_bytes(16));
        $status=$error instanceof HttpException ? $error->status : 500;
        $code=$error instanceof HttpException && preg_match('/^[A-Z][A-Z0-9_]{0,79}$/D',$error->errorCode) ? $error->errorCode : 'INTERNAL_ERROR';
        // Never include messages, stack traces, query strings, tokens, bodies or URLs.
        $class=get_class($error);
        $context=['request_id'=>preg_match('/^[a-f0-9]{16,32}$/D',$requestId) ? $requestId : $eventId,
            'status'=>$status,'class'=>preg_match('/^[A-Za-z0-9_\\\\]{1,200}$/D',$class) ? $class : 'Throwable','file'=>mb_substr(basename($error->getFile()),0,200),'line'=>$error->getLine(),
            'method'=>in_array($method,['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS'],true) ? $method : 'OTHER'];
        $entry=['event_id'=>$eventId,'type'=>self::classify($error,$path),'error_code'=>$code,'user_id'=>$userId!==null && $userId>0 ? $userId : null,
            'context'=>$context,'created_at'=>gmdate('Y-m-d H:i:s'),'file_written'=>false];
        LogFileLock::run($this->queueDirectory,function()use($entry):void {
            $this->save($entry);
            $this->deliver($entry);
        });
        return $eventId;
    }

    public function recover(int $limit=200): array
    {
        return LogFileLock::run($this->queueDirectory,function()use($limit):array {
            $processed=0;$delivered=0;$expired=0;$invalid=0;
            foreach (glob($this->queueDirectory.'/queue-*') ?: [] as $path) {
                if (!is_link($path) && is_file($path) && filemtime($path)<time()-90*86400) {
                    if(!unlink($path))throw new \RuntimeException('Expired log temporary cleanup failed');++$expired;
                }
            }
            foreach (glob($this->queueDirectory.'/*.invalid') ?: [] as $path) {
                if (!is_link($path) && is_file($path) && preg_match('/^[a-f0-9]{32}\.invalid$/D',basename($path)) && filemtime($path)<time()-90*86400) {
                    if(!unlink($path))throw new \RuntimeException('Expired invalid log cleanup failed');++$expired;
                }
            }
            foreach (glob($this->queueDirectory.'/*.json') ?: [] as $path) {
                if ($processed>=max(1,min(1000,$limit))) break;
                if (is_link($path) || !preg_match('/^[a-f0-9]{32}\.json$/D',basename($path))) continue;
                ++$processed;
                $entry=filesize($path)<=8192 ? json_decode(file_get_contents($path),true) : null;
                if (!$this->valid($entry,basename($path,'.json'))) {
                    if (!rename($path,substr($path,0,-5).'.invalid')) throw new \RuntimeException('Invalid log quarantine failed');
                    ++$invalid;continue;
                }
                if (strtotime($entry['created_at'].' UTC')<time()-90*86400) {if(!unlink($path))throw new \RuntimeException('Expired log queue cleanup failed');++$expired;continue;}
                if ($this->deliver($entry)) ++$delivered;
            }
            return ['processed'=>$processed,'delivered'=>$delivered,'expired'=>$expired,'invalid'=>$invalid];
        });
    }

    private function valid(mixed $entry,string $eventId): bool
    {
        if (!is_array($entry) || array_diff(array_keys($entry),['event_id','type','error_code','user_id','context','created_at','file_written']) || ($entry['event_id'] ?? null)!==$eventId || !in_array($entry['type'] ?? null,array_diff(LogFilters::TYPES,['admin_audit']),true)
            || !is_string($entry['error_code'] ?? null) || !preg_match('/^[A-Z][A-Z0-9_]{0,79}$/D',$entry['error_code'])
            || !array_key_exists('user_id',$entry) || ($entry['user_id']!==null && (!is_int($entry['user_id']) || $entry['user_id']<1))
            || !is_bool($entry['file_written'] ?? null) || !is_string($entry['created_at'] ?? null) || !is_array($entry['context'] ?? null)) return false;
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$entry['created_at'],new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d H:i:s')!==$entry['created_at']) return false;
        $context=$entry['context'];
        if (array_diff(array_keys($context),['request_id','status','class','file','line','method'])) return false;
        return is_string($context['request_id'] ?? null) && preg_match('/^[a-f0-9]{16,32}$/D',$context['request_id'])
            && is_int($context['status'] ?? null) && $context['status']>=400 && $context['status']<=599
            && is_string($context['class'] ?? null) && strlen($context['class'])<=200 && preg_match('/^[A-Za-z0-9_\\\\]+$/D',$context['class'])
            && is_string($context['file'] ?? null) && strlen($context['file'])<=200 && basename($context['file'])===$context['file']
            && is_int($context['line'] ?? null) && $context['line']>=0
            && in_array($context['method'] ?? null,['GET','HEAD','POST','PUT','PATCH','DELETE','OPTIONS','OTHER'],true);
    }

    private function save(array $entry): void
    {
        $path=$this->queueDirectory.'/'.$entry['event_id'].'.json';
        if (is_link($path)) throw new \RuntimeException('Log queue cannot be a link');
        $temporary=tempnam($this->queueDirectory,'queue-');
        if ($temporary===false) throw new \RuntimeException('Log queue unavailable');
        try {
            $contents=json_encode($entry,JSON_THROW_ON_ERROR);
            if (file_put_contents($temporary,$contents,LOCK_EX)!==strlen($contents) || !chmod($temporary,0600) || !rename($temporary,$path)) throw new \RuntimeException('Log queue write failed');
        } finally {if(is_file($temporary))unlink($temporary);}
    }

    private function deliver(array $entry): bool
    {
        $databaseWritten=false;
        if ($this->repository) {
            try {$this->repository->recordApplication($entry);$databaseWritten=true;}catch(\Throwable){error_log('Application log DB delivery pending.');}
        }
        if (!$entry['file_written']) {
            try {
                $this->files->write($entry['type'],$entry['error_code'],$entry['context']+['event_id'=>$entry['event_id'],'user_id'=>$entry['user_id']],strtotime($entry['created_at'].' UTC'));
                $entry['file_written']=true;$this->save($entry);
            } catch(\Throwable){error_log('Application log file delivery pending.');}
        }
        if ($databaseWritten && $entry['file_written']) {
            try {
                $this->repository->recordApplication($entry);
                if (!unlink($this->queueDirectory.'/'.$entry['event_id'].'.json')) throw new \RuntimeException('Log queue removal failed');
                return true;
            }catch(\Throwable){error_log('Application log completion pending.');}
        }
        return false;
    }
}
