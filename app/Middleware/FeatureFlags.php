<?php
declare(strict_types=1);
namespace App\Middleware;
use App\Http\{Request,Response,HttpException};

final class FeatureFlags
{
    public function __construct(private readonly \Closure $resolve) {}
    public static function feature(Request $request): ?string
    {
        $path=$request->path;
        if($path==='/api/search/suggest')return 'external_suggestions';
        if($path==='/api/favorites/metadata')return 'favorite_metadata';
        if($path==='/api/weather')return 'weather';
        if($request->method==='POST' && preg_match('~^/api/backgrounds(?:/[^/]+)?/upload$~D',$path))return 'background_uploads';
        // Existing private files remain readable when synchronization is disabled.
        if(in_array($request->method,['GET','HEAD'],true) && preg_match('~^/api/backgrounds/[^/]+/file$~D',$path))return null;
        if(preg_match('~^/api/(?:sync|settings|favorites|favorite-folders|search/history|search-engines|ai-providers|backgrounds)(?:/|$)~',$path))return 'cloud_sync';
        return null;
    }
    public function __invoke(Request $request,callable $next): Response
    {
        $feature=self::feature($request);
        if($feature!==null){$flags=($this->resolve)()['flags'];if(!$flags[$feature])throw new HttpException(403,'FEATURE_DISABLED');
            if($feature==='background_uploads' && !$flags['cloud_sync'])throw new HttpException(403,'FEATURE_DISABLED');}
        return $next($request);
    }
}
