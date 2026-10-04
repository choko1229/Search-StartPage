<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\HttpException;
use PDO;

/** Three-way manual rollback: undo migration changes while retaining later user changes. */
final class UpdateDatabaseMerge
{
    public function __construct(private readonly PDO $pdo) {}
    private static function quote(string $name):string
    {
        if(!preg_match('/^[A-Za-z0-9_]{1,64}$/D',$name))throw new HttpException(503,'UPDATE_DB_ROLLBACK_CONFLICT');return '`'.$name.'`';
    }
    private static function columns(string $list):array
    {
        $parts=explode(',',$list);$result=[];
        foreach($parts as $part){if(!preg_match('/^\s*`([A-Za-z0-9_]{1,64})`\s*$/D',$part,$match))throw new HttpException(503,'UPDATE_DB_ROLLBACK_CONFLICT');$result[]=$match[1];}return $result;
    }
    private static function primary(array $schema):array
    {
        if(!preg_match('/\bPRIMARY KEY\s*\(([^)]+)\)/',$schema['ddl'],$match))throw new HttpException(503,'UPDATE_DB_ROLLBACK_CONFLICT');
        $positions=[];foreach(self::columns($match[1]) as $column){$position=array_search($column,$schema['columns'],true);if($position===false)throw new HttpException(503,'UPDATE_DB_ROLLBACK_CONFLICT');$positions[]=$position;}return $positions;
    }
    private static function normalized(array $schema):array
    {
        $ddl=preg_replace('/ AUTO_INCREMENT=\d+\b/','',$schema['ddl']);
        // MySQL may spell out a column's inherited character set after CREATE
        // from SHOW CREATE. Only remove the redundant table-default spelling;
        // keep the column collation and every actual schema difference.
        if(preg_match('/\n\) ENGINE=InnoDB .*\bDEFAULT CHARSET=([a-z0-9_]+)\b/',$ddl,$match)){
            $charset=preg_quote($match[1],'/');
            $ddl=preg_replace('/^(\s*`[A-Za-z0-9_]+` (?:varchar\(\d+\)|char\(\d+\)|tinytext|mediumtext|longtext|text)) CHARACTER SET '.$charset.'(?= COLLATE )/m','$1',$ddl);
        }
        return ['ddl'=>$ddl,'columns'=>$schema['columns']];
    }
    private static function write($stream,array $value):void
    {
        $line=json_encode($value,JSON_THROW_ON_ERROR)."\n";if(strlen($line)>67108864||ftell($stream)+strlen($line)>2147483647)throw new HttpException(503,'UPDATE_DB_BACKUP_LIMIT');
        $offset=0;while($offset<strlen($line)){$written=fwrite($stream,substr($line,$offset));if($written===false||$written===0)throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');$offset+=$written;}
    }
    private function temporary(string $name,array $schema):void
    {
        $ddl=preg_replace('/^CREATE TABLE `[^`]+`/','CREATE TEMPORARY TABLE '.self::quote($name),$schema['ddl'],1);
        $lines=explode("\n",$ddl);$lines=array_filter($lines,static fn($line)=>!preg_match('/^\s*(?:CONSTRAINT `[^`]+` )?FOREIGN KEY\b/',$line));$ddl=implode("\n",$lines);$ddl=preg_replace('/,\s*\n\)/',"\n)",$ddl);
        // MySQL names CHECK constraints within the schema; keep expressions but give them fresh names.
        $ddl=preg_replace_callback('/CONSTRAINT `([^`]+)` CHECK/',static fn($match)=>'CONSTRAINT `'.substr(hash('sha256',$name.':'.$match[1]),0,32).'` CHECK',$ddl);
        $this->pdo->exec($ddl);
    }
    public function prepare(array $before,array $baseline,array $current,string $output):array
    {
        UpdatePackagePaths::directory(dirname($output));if(file_exists($output)||is_link($output)||$this->pdo->inTransaction())throw new HttpException(503,'UPDATE_DB_ROLLBACK_CONFLICT');
        $database=new UpdateDatabase($this->pdo);$map='rollback_'.bin2hex(random_bytes(10));$temps=[];$stream=null;$success=false;$headers=[];$buffered=$this->pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        try{
            $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,true);
            $this->pdo->exec('CREATE TEMPORARY TABLE '.self::quote($map).' (table_name VARBINARY(64) NOT NULL,key_hash BINARY(32) NOT NULL,key_data LONGBLOB NOT NULL,old_row LONGBLOB NULL,new_row LONGBLOB NULL,current_row LONGBLOB NULL,PRIMARY KEY(table_name,key_hash)) ENGINE=InnoDB');$temps[]=$map;
            foreach(['old_row'=>$before,'new_row'=>$baseline,'current_row'=>$current] as $field=>$snapshot){
                $insert=$this->pdo->prepare('INSERT INTO '.self::quote($map).' (table_name,key_hash,key_data,'.$field.') VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE '.$field.'=VALUES('.$field.')');
                $lookup=$this->pdo->prepare('SELECT key_data,'.$field.' FROM '.self::quote($map).' WHERE table_name=? AND key_hash=?');$primary=[];
                foreach($database->records($snapshot['path'],$snapshot['metadata']) as $record){
                    if(isset($record['format'])){$headers[$field]=$record;foreach($record['tables'] as $name=>$schema)$primary[$name]=self::primary($schema);continue;}
                    if(isset($record['end']))continue;
                    $name=$record['table'];$key=json_encode(array_map(static fn($index)=>$record['values'][$index],$primary[$name]),JSON_THROW_ON_ERROR);$hash=hash('sha256',$key,true);
                    $lookup->execute([$name,$hash]);$previous=$lookup->fetch(PDO::FETCH_NUM);$lookup->closeCursor();if($previous!==false&&($previous[0]!==$key||$previous[1]!==null))throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
                    $insert->execute([$name,$hash,$key,json_encode($record['values'],JSON_THROW_ON_ERROR)]);
                }
            }
            if($headers['old_row']['database']!==$headers['new_row']['database']||$headers['old_row']['database']!==$headers['current_row']['database'])throw new HttpException(422,'INVALID_UPDATE_DB_BACKUP');
            $old=$headers['old_row']['tables'];$new=$headers['new_row']['tables'];$now=$headers['current_row']['tables'];
            if(array_keys($new)!==array_keys($now))throw new HttpException(503,'UPDATE_DB_ROLLBACK_CONFLICT');
            $changed=$this->pdo->query('SELECT DISTINCT table_name FROM '.self::quote($map).' WHERE NOT(new_row <=> current_row)')->fetchAll(PDO::FETCH_COLUMN);$changed=array_fill_keys($changed,true);$compatible=[];$validation=[];$inserts=[];
            foreach($new as $name=>$schema){
                if(self::normalized($schema)!==self::normalized($now[$name]))throw new HttpException(503,'UPDATE_DB_ROLLBACK_CONFLICT');
                $compatible[$name]=isset($old[$name])&&self::normalized($old[$name])===self::normalized($schema);
                if($name!=='migrations'&&!$compatible[$name]&&isset($changed[$name]))throw new HttpException(503,'UPDATE_DB_ROLLBACK_CONFLICT');
            }
            foreach($old as $name=>&$schema){
                // Preserve the ID high-water mark, including IDs deleted after the update.
                if(isset($now[$name])&&preg_match('/ AUTO_INCREMENT=(\d+)\b/',$now[$name]['ddl'],$newAuto)&&preg_match('/ AUTO_INCREMENT=(\d+)\b/',$schema['ddl'],$oldAuto)){
                    if(strlen($newAuto[1])>strlen($oldAuto[1])||(strlen($newAuto[1])===strlen($oldAuto[1])&&strcmp($newAuto[1],$oldAuto[1])>0))$schema['ddl']=str_replace($oldAuto[0],$newAuto[0],$schema['ddl']);
                }
                $temporary='rowcheck_'.bin2hex(random_bytes(10));$this->temporary($temporary,$schema);$temps[]=$temporary;$validation[$name]=$temporary;
                $inserts[$name]=$this->pdo->prepare('INSERT INTO '.self::quote($temporary).' ('.implode(',',array_map(self::quote(...),$schema['columns'])).') VALUES ('.implode(',',array_fill(0,count($schema['columns']),'?')).')');
            }unset($schema);
            $stream=@fopen($output,'xb');if($stream===false||!chmod($output,0600))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');self::write($stream,['format'=>1,'database'=>$headers['old_row']['database'],'tables'=>$old]);$rows=0;
            $query=$this->pdo->prepare('SELECT table_name,key_hash,old_row,new_row,current_row FROM '.self::quote($map).' WHERE table_name>? OR (table_name=? AND key_hash>?) ORDER BY table_name,key_hash LIMIT 1');$cursorName='';$cursorHash='';
            while(true){
                $query->execute([$cursorName,$cursorName,$cursorHash]);$record=$query->fetch(PDO::FETCH_NUM);$query->closeCursor();if($record===false)break;
                [$name,$cursorHash,$b,$u,$c]=$record;$cursorName=$name;if(!isset($old[$name]))continue;$value=$b;
                if($name!=='migrations'&&($compatible[$name]??false)&&$c!==$u){
                    if($c===null)$value=null;
                    elseif($b===null||$u===null)$value=$c;
                    else{$base=json_decode($b,true,8,JSON_THROW_ON_ERROR);$updated=json_decode($u,true,8,JSON_THROW_ON_ERROR);$edited=json_decode($c,true,8,JSON_THROW_ON_ERROR);foreach($edited as $index=>$cell)if($cell!==$updated[$index])$base[$index]=$cell;$value=json_encode($base,JSON_THROW_ON_ERROR);}
                }
                if($value===null)continue;$values=json_decode($value,true,8,JSON_THROW_ON_ERROR);$inserts[$name]->execute(array_map(static fn($cell)=>$cell===null?null:base64_decode($cell,true),$values));self::write($stream,['table'=>$name,'values'=>$values]);++$rows;
            }
            // Validate old foreign keys against all merged rows before any live replacement.
            foreach($old as $name=>$schema){
                preg_match_all('/FOREIGN KEY \(([^)]+)\) REFERENCES `([A-Za-z0-9_]+)` \(([^)]+)\)/',$schema['ddl'],$keys,PREG_SET_ORDER);
                if(count($keys)!==substr_count($schema['ddl'],'FOREIGN KEY'))throw new HttpException(503,'UPDATE_DB_ROLLBACK_CONFLICT');
                foreach($keys as $key){$columns=self::columns($key[1]);$parentColumns=self::columns($key[3]);if(count($columns)!==count($parentColumns)||!isset($validation[$key[2]]))throw new HttpException(503,'UPDATE_DB_ROLLBACK_CONFLICT');$join=[];$notNull=[];foreach($columns as $index=>$column){$join[]='c.'.self::quote($column).'=p.'.self::quote($parentColumns[$index]);$notNull[]='c.'.self::quote($column).' IS NOT NULL';}
                    $sql='SELECT 1 FROM '.self::quote($validation[$name]).' c LEFT JOIN '.self::quote($validation[$key[2]]).' p ON '.implode(' AND ',$join).' WHERE '.implode(' AND ',$notNull).' AND p.'.self::quote($parentColumns[0]).' IS NULL LIMIT 1';if($this->pdo->query($sql)->fetchColumn()!==false)throw new HttpException(503,'UPDATE_DB_ROLLBACK_CONFLICT');
                }
            }
            self::write($stream,['end'=>$rows]);if(!fflush($stream)||!fsync($stream))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
            $success=true;return ['bytes'=>ftell($stream),'sha256'=>hash_file('sha256',$output),'database'=>$headers['old_row']['database'],'tables'=>count($old),'rows'=>$rows];
        }catch(HttpException $error){throw $error;}
        catch(\Throwable){throw new HttpException(503,'UPDATE_DB_ROLLBACK_CONFLICT');}
        finally{
            if(is_resource($stream))fclose($stream);foreach(array_reverse($temps) as $temporary)$this->pdo->exec('DROP TEMPORARY TABLE IF EXISTS '.self::quote($temporary));$this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,$buffered);
            if(!$success&&is_file($output)&&!unlink($output))throw new HttpException(503,'UPDATE_STORAGE_UNAVAILABLE');
        }
    }
}
