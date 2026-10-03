<?php
declare(strict_types=1);
namespace App\Services;
use App\Repositories\BackgroundRepository;
use App\Http\HttpException;

/** Recover files left behind by interrupted uploads or failed unlink operations. */
final class BackgroundCleanup
{
    public const GRACE_SECONDS=86400;
    public function __construct(private readonly string $root,private readonly BackgroundRepository $repository){}
    public function run(bool $apply=false,?int $now=null): array
    {
        $now??=time();$result=['candidates'=>0,'deleted'=>0,'busy_owners'=>0,'failed'=>0];
        $root=realpath($this->root);
        if($root===false)throw new HttpException(503,'BACKGROUND_STORAGE_UNAVAILABLE');
        $directory=str_replace('\\','/',$root);
        foreach(['storage','uploads','backgrounds'] as $part){
            $directory.='/'.$part;
            if(is_link($directory))throw new HttpException(503,'BACKGROUND_STORAGE_UNAVAILABLE');
            if(!file_exists($directory))return $result;
            if(!is_dir($directory)||str_replace('\\','/',realpath($directory)?:'')!==$directory)throw new HttpException(503,'BACKGROUND_STORAGE_UNAVAILABLE');
        }
        $storage=new BackgroundUpload($root);
        foreach(new \DirectoryIterator($directory) as $entry){
            $owner=$entry->getFilename();
            if($entry->isDot()||$entry->isLink()||!$entry->isDir()||!preg_match('/^[1-9][0-9]*$/D',$owner)||(string)(int)$owner!==$owner)continue;
            $count=$storage->withOwnerLock((int)$owner,function()use($owner,$directory,$apply,$now):array{
                // Include archived and Cloud Sync OFF rows: they still own files.
                $referenced=array_fill_keys(array_filter(array_column($this->repository->list((int)$owner),'file_path')),true);
                $count=['candidates'=>0,'deleted'=>0,'failed'=>0];
                foreach(new \DirectoryIterator($directory.'/'.$owner) as $file){
                    $name=$file->getFilename();
                    if($file->isDot()||$file->isLink()||!$file->isFile()||!preg_match('/^[a-f0-9]{48}\.(jpg|png|gif|webp|avif|mp4|webm)$/D',$name)||isset($referenced[$name]))continue;
                    if($file->getMTime()>$now-self::GRACE_SECONDS)continue;
                    $count['candidates']++;
                    if($apply){if(@unlink($file->getPathname()))$count['deleted']++;else $count['failed']++;}
                }
                return $count;
            },false);
            if($count===null)$result['busy_owners']++;
            else foreach($count as $key=>$value)$result[$key]+=$value;
        }
        return $result;
    }
}
