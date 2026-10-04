<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;
use PDO;

/** Private application-schema snapshot. The engine must quiesce all writers/DDL. */
final class UpdateDatabase
{
    private const LIMIT=2147483647;
    private const LINE_LIMIT=67108864;
    public function __construct(private readonly PDO $pdo) {}
    private static function name(string $name): string
    {
        if(!preg_match('/^[A-Za-z0-9_]{1,64}$/D',$name))throw new HttpException(503,'UPDATE_DB_SCHEMA_UNSUPPORTED');return '`'.$name.'`';
    }
    private function identity(): string
    {
        $row=$this->pdo->query('SELECT DATABASE(), @@hostname, @@port')->fetch(PDO::FETCH_NUM);
        if(!$row||!is_string($row[0])||$row[0]==='')throw new HttpException(503,'UPDATE_DB_BACKUP_FAILED');
        return hash('sha256',json_encode(array_map(static fn($value)=>(string)$value,$row),JSON_THROW_ON_ERROR));
    }
    private function tables(): array
    {
        $rows=$this->pdo->query('SELECT TABLE_NAME, TABLE_TYPE, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() ORDER BY TABLE_NAME')->fetchAll(PDO::FETCH_NUM);
        if(count($rows)>4096)throw new HttpException(503,'UPDATE_DB_SCHEMA_UNSUPPORTED');
        $tables=[];
        foreach($rows as [$name,$type,$engine]){self::name($name);if($type!=='BASE TABLE'||strtoupper((string)$engine)!=='INNODB')throw new HttpException(503,'UPDATE_DB_SCHEMA_UNSUPPORTED');$tables[]=$name;}
        foreach(['TRIGGERS'=>'TRIGGER_SCHEMA','ROUTINES'=>'ROUTINE_SCHEMA','EVENTS'=>'EVENT_SCHEMA'] as $table=>$column){
            if((int)$this->pdo->query('SELECT COUNT(*) FROM information_schema.'.$table.' WHERE '.$column.'=DATABASE()')->fetchColumn()!==0)throw new HttpException(503,'UPDATE_DB_SCHEMA_UNSUPPORTED');
        }
        return $tables;
    }
    private static function write($stream,array $record): void
    {
        $line=json_encode($record,JSON_THROW_ON_ERROR)."\n";
        if(strlen($line)>self::LINE_LIMIT||ftell($stream)+strlen($line)>self::LIMIT)throw new HttpException(503,'UPDATE_DB_BACKUP_LIMIT');
        $offset=0;while($offset<strlen($line)){$written=fwrite($stream,substr($line,$offset));if($written===false||$written===0)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$offset+=$written;}
    }
    private function restoreObjects(): array
    {
        $objects=[];
        foreach($this->pdo->query('SELECT TABLE_NAME,TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_NUM) as [$name,$type]){
            self::name($name);if(!in_array($type,['BASE TABLE','VIEW'],true))throw new HttpException(503,'UPDATE_DB_SCHEMA_UNSUPPORTED');$objects[]=[$type==='VIEW'?'VIEW':'TABLE',$name];
        }
        foreach($this->pdo->query('SELECT ROUTINE_NAME,ROUTINE_TYPE FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_NUM) as [$name,$type]){
            self::name($name);if(!in_array($type,['PROCEDURE','FUNCTION'],true))throw new HttpException(503,'UPDATE_DB_SCHEMA_UNSUPPORTED');$objects[]=[$type,$name];
        }
        foreach($this->pdo->query('SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN) as $name){self::name($name);$objects[]=['EVENT',$name];}
        if(count($objects)>4096)throw new HttpException(503,'UPDATE_DB_SCHEMA_UNSUPPORTED');
        // Views first; triggers disappear with their owning tables.
        usort($objects,static fn($a,$b)=>($a[0]!=='VIEW')<=>($b[0]!=='VIEW'));return $objects;
    }
    public function snapshot(string $path,?\Closure $afterSnapshot=null): array
    {
        UpdatePackagePaths::directory(dirname($path));
        if(file_exists($path)||is_link($path))throw new HttpException(409,'UPDATE_BACKUP_EXISTS');
        if($this->pdo->inTransaction())throw new HttpException(409,'UPDATE_DB_TRANSACTION_ACTIVE');
        $stream=@fopen($path,'xb');if($stream===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$success=false;$buffered=$this->pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);$stringify=$this->pdo->getAttribute(PDO::ATTR_STRINGIFY_FETCHES);
        try{
            if(!chmod($path,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            $tables=$this->tables();$identity=$this->identity();$schemas=[];$rows=0;
            $this->pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');$this->pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
            foreach($tables as $name){
                $quoted=self::name($name);$ddl=$this->pdo->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM)[1];
                $columns=[];foreach($this->pdo->query('SHOW FULL COLUMNS FROM '.$quoted)->fetchAll() as $column){if(preg_match('/^(?:bit|geometry|point|linestring|polygon|multipoint|multilinestring|multipolygon|geometrycollection)\b/i',$column['Type']))throw new HttpException(503,'UPDATE_DB_SCHEMA_UNSUPPORTED');if(preg_match('/(?:VIRTUAL|STORED|PERSISTENT)/i',$column['Extra']))continue;self::name($column['Field']);$columns[]=$column['Field'];}
                if(!$columns||count($columns)>512)throw new HttpException(503,'UPDATE_DB_SCHEMA_UNSUPPORTED');
                $schemas[$name]=['ddl'=>$ddl,'columns'=>$columns];
            }
            self::write($stream,['format'=>1,'database'=>$identity,'tables'=>$schemas]);
            if($afterSnapshot)$afterSnapshot();
            $this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,true);
            $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
            foreach($schemas as $name=>$schema){
                $query=$this->pdo->query('SELECT '.implode(',',array_map(self::name(...),$schema['columns'])).' FROM '.self::name($name));
                try{while(($values=$query->fetch(PDO::FETCH_NUM))!==false){self::write($stream,['table'=>$name,'values'=>array_map(static fn($value)=>$value===null?null:base64_encode((string)$value),$values)]);++$rows;}}
                finally{$query->closeCursor();}
            }
            self::write($stream,['end'=>$rows]);$this->pdo->commit();
            if(!fflush($stream))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            $digest=hash_file('sha256',$path);if($digest===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            $success=true;return ['bytes'=>ftell($stream),'sha256'=>$digest,'database'=>$identity,'tables'=>count($schemas),'rows'=>$rows];
        }catch(HttpException $error){throw $error;}
        catch(\Throwable){throw new HttpException(503,'UPDATE_DB_BACKUP_FAILED');}
        finally{
            $cleanupFailed=false;
            try{if($this->pdo->inTransaction())$this->pdo->rollBack();$this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered);$this->pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES,$stringify);}catch(\Throwable){$cleanupFailed=true;}
            fclose($stream);
            if(!$success&&!unlink($path))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            if($cleanupFailed)throw new HttpException(503,'UPDATE_DB_BACKUP_FAILED');
        }
    }
    private static function read($stream): ?array
    {
        $line=fgets($stream,self::LINE_LIMIT+1);if($line===false){if(feof($stream))return null;throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');}
        if(!str_ends_with($line,"\n"))throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
        try{$record=json_decode($line,true,16,JSON_THROW_ON_ERROR);}catch(\Throwable){throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');}
        if(!is_array($record))throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');return $record;
    }
    private function preflight($stream,array $metadata): array
    {
        $header=self::read($stream);
        if(!$header||count($header)!==3||($header['format']??null)!==1||($header['database']??null)!==$metadata['database']||$metadata['database']!==$this->identity()||!is_array($header['tables']??null)||count($header['tables'])!==$metadata['tables'])throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
        $tables=$header['tables'];
        foreach($tables as $name=>$schema){
            self::name($name);
            if(!is_array($schema)||count($schema)!==2||!is_string($schema['ddl']??null)||!str_starts_with($schema['ddl'],'CREATE TABLE '.self::name($name).' (')||!is_array($schema['columns']??null)||!array_is_list($schema['columns'])||!$schema['columns']||count($schema['columns'])>512)throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
            foreach($schema['columns'] as $column){if(!is_string($column))throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');self::name($column);}
            if(count(array_unique($schema['columns']))!==count($schema['columns']))throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
        }
        $rows=0;$ended=false;
        while(($record=self::read($stream))!==null){
            if($ended)throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
            if(count($record)===1&&isset($record['end'])){if($record['end']!==$rows||$rows!==$metadata['rows'])throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');$ended=true;continue;}
            $name=$record['table']??null;$values=$record['values']??null;
            if(count($record)!==2||!is_string($name)||!isset($tables[$name])||!is_array($values)||!array_is_list($values)||count($values)!==count($tables[$name]['columns']))throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
            foreach($values as $value){if($value!==null&&(!is_string($value)||($decoded=base64_decode($value,true))===false||base64_encode($decoded)!==$value))throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');}++$rows;
        }
        if(!$ended)throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');return $tables;
    }
    /** Only trusted private snapshot+metadata; DDL auto-commits, so errors keep maintenance active. */
    public function records(string $path,array $metadata): \Generator
    {
        UpdatePackagePaths::directory(dirname($path));
        if(is_link($path)||!is_file($path)||filesize($path)!==($metadata['bytes']??null)||!is_string($metadata['sha256']??null)||!hash_equals($metadata['sha256'],hash_file('sha256',$path)))throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
        $stream=@fopen($path,'rb');if($stream===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        try{
            if(!flock($stream,LOCK_SH))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            $this->preflight($stream,$metadata);rewind($stream);
            while(($record=self::read($stream))!==null)yield $record;
        }finally{flock($stream,LOCK_UN);fclose($stream);}
    }
    public function restore(string $path,array $metadata,?\Closure $afterTable=null): void
    {
        UpdatePackagePaths::directory(dirname($path));
        if($this->pdo->inTransaction())throw new HttpException(409,'UPDATE_DB_TRANSACTION_ACTIVE');
        if(!is_int($metadata['bytes']??null)||$metadata['bytes']<1||$metadata['bytes']>self::LIMIT||!is_int($metadata['tables']??null)||$metadata['tables']<0||$metadata['tables']>4096||!is_int($metadata['rows']??null)||$metadata['rows']<0
            ||!is_string($metadata['database']??null)||!preg_match('/^[a-f0-9]{64}$/D',$metadata['database'])||!is_string($metadata['sha256']??null)||!preg_match('/^[a-f0-9]{64}$/D',$metadata['sha256'])||is_link($path)||!is_file($path))throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
        $stream=@fopen($path,'rb');if($stream===false)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$modified=false;$mode=null;$foreign=null;
        try{
            if(!flock($stream,LOCK_SH)||fstat($stream)['size']!==$metadata['bytes'])throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
            $hash=hash_init('sha256');hash_update_stream($hash,$stream);if(!hash_equals($metadata['sha256'],hash_final($hash)))throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
            rewind($stream);$tables=$this->preflight($stream,$metadata);$current=$this->restoreObjects();
            $mode=(string)$this->pdo->query('SELECT @@SESSION.sql_mode')->fetchColumn();$foreign=(int)$this->pdo->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn();
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');$this->pdo->exec('SET SESSION sql_mode='.$this->pdo->quote($mode.($mode===''?'':',').'NO_AUTO_VALUE_ON_ZERO'));$modified=true;
            foreach($current as [$type,$name])$this->pdo->exec('DROP '.$type.' '.self::name($name));
            foreach($tables as $name=>$schema){$this->pdo->exec($schema['ddl']);if($afterTable)$afterTable($name);}
            $this->pdo->beginTransaction();rewind($stream);self::read($stream);$statements=[];
            foreach($tables as $name=>$schema)$statements[$name]=$this->pdo->prepare('INSERT INTO '.self::name($name).' ('.implode(',',array_map(self::name(...),$schema['columns'])).') VALUES ('.implode(',',array_fill(0,count($schema['columns']),'?')).')');
            while(($record=self::read($stream))!==null){if(isset($record['end']))break;$statements[$record['table']]->execute(array_map(static fn($value)=>$value===null?null:base64_decode($value,true),$record['values']));}
            $this->pdo->commit();
        }catch(HttpException $error){throw $error;}
        catch(\Throwable){throw new HttpException(503,'UPDATE_DB_RESTORE_FAILED');}
        finally{
            $cleanupFailed=false;
            try{if($this->pdo->inTransaction())$this->pdo->rollBack();if($modified){$this->pdo->exec('SET SESSION sql_mode='.$this->pdo->quote($mode));$this->pdo->exec('SET FOREIGN_KEY_CHECKS='.$foreign);}}catch(\Throwable){$cleanupFailed=true;}
            flock($stream,LOCK_UN);fclose($stream);
            if($cleanupFailed)throw new HttpException(503,'UPDATE_DB_RESTORE_FAILED');
        }
    }
}
