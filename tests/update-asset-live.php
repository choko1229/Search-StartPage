<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/autoload.php';
use App\Services\GitHubUpdateAsset;
use App\Http\HttpException;
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
// Public fixture only. An impossible ID exercises actual TLS/cURL error handling.
try{(new GitHubUpdateAsset('octocat/Hello-World'))->select(PHP_INT_MAX);throw new RuntimeException('Unexpected asset');}
catch(HttpException $error){if($error->errorCode!=='UPDATE_SOURCE_NOT_FOUND')throw $error;echo "PASS: live public GitHub TLS/cURL returns safe not-found; no target download claimed.\n";}
