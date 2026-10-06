<?php
declare(strict_types=1);
// Disposable test identities only. This entry is outside public and is served solely by the guarded test router.
$root=dirname(__DIR__,3);$host=getenv('TEST_EXTENSION_HOST');
if($root!=='/tmp/search-extension-web'||getenv('SEARCH_TEST_MODE')!=='1'
    ||!in_array($host,['search-extension-ui-mysql-20261006','search-extension-ui-mariadb-20261006'],true)
    ||!is_file($root.'/storage/extension-test-only')){http_response_code(404);exit;}
require $root.'/app/autoload.php';
$config=App\Config::load($root);
if($config->get('database.host')!==$host||$config->get('database.name')!=='extension_ui'){http_response_code(404);exit;}
App\Auth\Session::start($config);
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'')==='POST'){
    if(!is_string($_POST['csrf']??null)||!App\Auth\Session::verifyCsrf($_POST['csrf'])){http_response_code(403);exit;}
    $identity=match($_POST['account']??''){'primary'=>['id'=>'999999999999999980','username'=>'Generated isolated extension primary'],'other'=>['id'=>'999999999999999981','username'=>'Generated isolated extension other'],default=>null};
    if($identity===null){http_response_code(422);exit;}
    $auth=new App\Auth\Auth($config,new App\Repositories\AuthRepository(App\Database\Database::connect($config->get('database'))));
    $auth->login($identity+['display_name'=>null,'avatar'=>null],$_SERVER['HTTP_USER_AGENT']??'Generated test');
    header('Location: /',true,303);exit;
}
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html><html lang="ja"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>隔離した拡張検証</title><h1>Chrome拡張の隔離検証</h1>
<p>生成した通常ユーザーだけを使用します。Discordの実ログイン検証ではありません。</p>
<form method="post"><input type="hidden" name="csrf" value="<?= App\Helpers\View::escape(App\Auth\Session::csrf()) ?>">
<button name="account" value="primary">生成テストユーザーで開始</button>
<button name="account" value="other">別の生成ユーザーへ切替</button></form>
