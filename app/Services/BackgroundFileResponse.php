<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\{Request,Response,HttpException};

final class BackgroundFileResponse
{
    public static function create(Request $request,string $path,string $mime): Response
    {
        $size=filesize($path);if($size===false||$size<1)throw new HttpException(404,'NOT_FOUND');
        $start=0;$end=$size-1;$status=200;
        $headers=['Content-Type'=>$mime,'Accept-Ranges'=>'bytes','Cache-Control'=>'private, no-store',
            'Content-Disposition'=>'inline; filename="background.'.pathinfo($path,PATHINFO_EXTENSION).'"'];
        $range=$request->server['HTTP_RANGE']??null;
        if($range!==null) {
            if(!is_string($range)||!preg_match('/^bytes=(\d*)-(\d*)$/D',$range,$match)||($match[1]===''&&$match[2]==='')) {
                return new Response('',416,$headers+['Content-Range'=>'bytes */'.$size,'Content-Length'=>'0']);
            }
            if($match[1]===''){$length=(int)$match[2];$start=max(0,$size-$length);if($length<1)$start=$size;}
            else {$start=(int)$match[1];$end=$match[2]===''?$end:min($end,(int)$match[2]);}
            if($start>$end||$start>=$size)return new Response('',416,$headers+['Content-Range'=>'bytes */'.$size,'Content-Length'=>'0']);
            $status=206;$headers['Content-Range']='bytes '.$start.'-'.$end.'/'.$size;
        }
        $length=$end-$start+1;$headers['Content-Length']=(string)$length;
        $handle=@fopen($path,'rb');if($handle===false)throw new HttpException(404,'NOT_FOUND');
        if(fseek($handle,$start)!==0){fclose($handle);throw new HttpException(503,'BACKGROUND_STORAGE_UNAVAILABLE');}
        return new Response('',$status,$headers,static function()use($handle,$length):void {
            if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
            try { $remaining=$length;while($remaining>0&&!feof($handle)){$chunk=fread($handle,min(65536,$remaining));if($chunk===false||$chunk==='')break;echo $chunk;$remaining-=strlen($chunk);} }
            finally {fclose($handle);}
        });
    }
}
