<?php
declare(strict_types=1);
namespace App\Repositories;
use App\Services\UpdateCommands;
use App\Http\HttpException;

/** Idempotent DB projection and audit of the private update command ledger. */
final class UpdateHistoryRepository
{
    public function __construct(private readonly \PDO $pdo) {}
    public function matches(array $request):bool
    {
        UpdateCommands::validateRequest($request);$query=$this->pdo->prepare('SELECT operation,from_version,to_version,channel,repository,release_id,requested_by,created_at FROM update_history WHERE request_id=?');$query->execute([$request['id']]);$row=$query->fetch(\PDO::FETCH_ASSOC);if($row===false)return false;
        foreach(['operation','from_version','to_version','channel','repository'] as $field)if($row[$field]!==$request[$field])return false;
        return ($row['release_id']===null?null:(int)$row['release_id'])===$request['release_id']&&(int)$row['requested_by']===$request['actor']&&$row['created_at']===gmdate('Y-m-d H:i:s',$request['created_at']);
    }
    public function record(array $request,string $event):void
    {
        UpdateCommands::validateRequest($request);if(!in_array($event,['requested','started','finished'],true)||$this->pdo->inTransaction())throw new HttpException(503,'UPDATE_AUDIT_FAILED');
        if(($event==='requested'&&$request['status']!=='prepared')||($event==='started'&&$request['status']!=='running')||($event==='finished'&&!in_array($request['status'],[...UpdateCommands::TERMINAL,'recovery_required'],true)))throw new HttpException(503,'UPDATE_AUDIT_FAILED');
        $status=$event==='requested'?'queued':$request['status'];$this->pdo->beginTransaction();
        try{
            $query=$this->pdo->prepare('INSERT INTO update_history(request_id,operation,from_version,to_version,channel,repository,release_id,requested_by,status,error_code,created_at,completed_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE request_id=request_id');
            $query->execute([$request['id'],$request['operation'],$request['from_version'],$request['to_version'],$request['channel'],$request['repository'],$request['release_id'],$request['actor'],$status,$request['error'],gmdate('Y-m-d H:i:s',$request['created_at']),$request['completed_at']===null?null:gmdate('Y-m-d H:i:s',$request['completed_at'])]);
            if(!$this->matches($request))throw new HttpException(409,'UPDATE_STATE_CHANGED');
            $query=$this->pdo->prepare('SELECT status FROM update_history WHERE request_id=?');$query->execute([$request['id']]);$previous=$query->fetchColumn();
            if((in_array($previous,UpdateCommands::TERMINAL,true)&&$previous!==$status)||($status==='queued'&&$previous!=='queued'))throw new HttpException(409,'UPDATE_STATE_CHANGED');
            $query=$this->pdo->prepare('UPDATE update_history SET status=?,error_code=?,completed_at=? WHERE request_id=?');$query->execute([$status,$request['error'],$request['completed_at']===null?null:gmdate('Y-m-d H:i:s',$request['completed_at']),$request['id']]);
            // A DB snapshot predates later requests. Restore their acceptance audit too.
            (new LogRepository($this->pdo))->recordApplication(['event_id'=>substr(hash('sha256',$request['id'].':requested:queued'),0,32),'type'=>'admin_audit','error_code'=>$request['operation']==='apply'?'UPDATE_APPLY_REQUESTED':'UPDATE_ROLLBACK_REQUESTED','user_id'=>$request['actor'],'context'=>['request_id'=>$request['id'],'operation'=>$request['operation'],'from_version'=>$request['from_version'],'to_version'=>$request['to_version'],'channel'=>$request['channel'],'status'=>'queued','error'=>null],'created_at'=>gmdate('Y-m-d H:i:s',$request['created_at']),'file_written'=>false]);
            (new LogRepository($this->pdo))->recordApplication(['event_id'=>substr(hash('sha256',$request['id'].':'.$event.':'.$status),0,32),'type'=>'admin_audit','error_code'=>$event==='requested'?($request['operation']==='apply'?'UPDATE_APPLY_REQUESTED':'UPDATE_ROLLBACK_REQUESTED'):'UPDATE_JOB_'.strtoupper($status),'user_id'=>$request['actor'],'context'=>['request_id'=>$request['id'],'operation'=>$request['operation'],'from_version'=>$request['from_version'],'to_version'=>$request['to_version'],'channel'=>$request['channel'],'status'=>$status,'error'=>$request['error']],'created_at'=>gmdate('Y-m-d H:i:s',$request['updated_at']),'file_written'=>false]);
            if($event==='finished'&&$request['error']!==null){
                // Same transaction and stable ID let snapshot reprojection restore errors without duplicates.
                (new LogRepository($this->pdo))->recordApplication(['event_id'=>substr(hash('sha256',$request['id'].':update-error:'.$status.':'.$request['error']),0,32),'type'=>'update_error','error_code'=>$request['error'],'user_id'=>$request['actor'],'context'=>['outbox'=>'update_job','request_id'=>$request['id'],'operation'=>$request['operation'],'status'=>$status],'created_at'=>gmdate('Y-m-d H:i:s',$request['updated_at']),'file_written'=>false]);
            }
            $this->pdo->commit();
        }catch(\Throwable $error){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $error;}
    }
    public function listing(int $limit=20):array
    {
        if($limit<1||$limit>100)throw new HttpException(422,'INVALID_INPUT');$query=$this->pdo->prepare('SELECT id,request_id,operation,from_version,to_version,channel,status,error_code,created_at,completed_at FROM update_history ORDER BY created_at DESC,id DESC LIMIT ?');$query->bindValue(1,$limit,\PDO::PARAM_INT);$query->execute();$rows=$query->fetchAll(\PDO::FETCH_ASSOC);foreach($rows as &$row)$row['id']=(int)$row['id'];return $rows;
    }
}
