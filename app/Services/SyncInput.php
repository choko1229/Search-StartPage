<?php
declare(strict_types=1);
namespace App\Services;
use App\Http\{Request,HttpException};
final class SyncInput
{
    public static function version(Request $request,int $userId): int
    {
        if(array_key_exists('user_id',$request->body) && (!is_string($request->body['user_id']) || $request->body['user_id']!==(string)$userId))throw new HttpException(403,'AUTH_REQUIRED');
        $version=$request->body['version']??null;
        if(!is_int($version) || $version<0 || $version>9007199254740990)throw new HttpException(422,'INVALID_INPUT');
        return $version;
    }
}
