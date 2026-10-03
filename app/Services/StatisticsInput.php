<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;
final class StatisticsInput {
    public const FEATURES=['command_palette','background','settings','favorites','sync'];
    public static function events(mixed $body):array {
        if(!is_object($body)||array_keys((array)$body)!==['events']||!is_array($body->events)||count($body->events)<1||count($body->events)>100)throw new HttpException(422,'INVALID_INPUT');
        $providers=require dirname(__DIR__,2).'/config/providers.php';$result=[];
        foreach($body->events as $event){
            if(!is_object($event)||count((array)$event)!==6||array_diff(array_keys((array)$event),['event_id','anonymous_id','event_type','event_data','source','created_at']))throw new HttpException(422,'INVALID_INPUT');
            foreach(['event_id','anonymous_id'] as $key)if(!isset($event->$key)||!is_string($event->$key)||!preg_match('/^[a-f0-9]{32}$/D',$event->$key))throw new HttpException(422,'INVALID_INPUT');
            if(!in_array($event->source??null,['web','extension'],true)||!in_array($event->event_type??null,['visit','search','ai_search','favorite_open','feature'],true)||!is_object($event->event_data??null))throw new HttpException(422,'INVALID_INPUT');
            // Event time is supplied for offline delivery, never an arbitrary future timestamp.
            if(!is_int($event->created_at??null)||$event->created_at<0||$event->created_at>time()+300)throw new HttpException(422,'INVALID_INPUT');
            $data=(array)$event->event_data;
            if(in_array($event->event_type,['search','ai_search'],true)){
                $mode=$event->event_type==='search'?'web':'ai';$allowed=[...array_column($providers[$mode],'id'),'custom'];
                if(array_keys($data)!==['provider']||!in_array($data['provider'],$allowed,true))throw new HttpException(422,'INVALID_INPUT');
            }elseif($event->event_type==='feature'){
                if(array_keys($data)!==['feature']||!in_array($data['feature'],self::FEATURES,true))throw new HttpException(422,'INVALID_INPUT');
            }elseif($data!==[])throw new HttpException(422,'INVALID_INPUT');
            $result[]=(array)$event;
        }
        return $result;
    }
}
