<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||getenv('SEARCH_TEST_MODE')!=='1')exit(1);
require dirname(__DIR__).'/app/autoload.php';
use App\Config;use App\Database\Database;use App\Repositories\{AuthRepository,SyncRepository,SyncProjectionRepository};use App\Services\SyncDocument;use App\Http\HttpException;
$pdo=Database::connect(Config::load(dirname(__DIR__))->get('database'));$auth=new AuthRepository($pdo);$repo=new SyncRepository($pdo);
$pdo->exec("DELETE FROM users WHERE discord_id IN ('999999999999999971','999999999999999972')");
$uid=$auth->upsertIdentity(['id'=>'999999999999999971','username'=>'Projection Test','display_name'=>null,'avatar'=>null],'en');
$other=$auth->upsertIdentity(['id'=>'999999999999999972','username'=>'Projection Other','display_name'=>null,'avatar'=>null],'en');
$count=0;$check=static function(bool $ok,string $label)use(&$count){if(!$ok)throw new RuntimeException($label);$count++;echo "PASS: $label\n";};
$rows=static function(string $table,int $owner)use($pdo){$query=$pdo->prepare("SELECT * FROM $table WHERE user_id=?");$query->execute([$owner]);return $query->fetchAll(PDO::FETCH_ASSOC);};
$constraint=false;
$dropConstraint=static function()use($pdo){$suffix=str_contains($pdo->getAttribute(PDO::ATTR_SERVER_VERSION),'MariaDB')?'CONSTRAINT':'CHECK';$pdo->exec("ALTER TABLE user_settings DROP $suffix sync_projection_failure");};
try {
    $pdo->prepare("INSERT INTO favorite_folders (id,user_id,client_id,name,created_at,updated_at) VALUES ('legacy-folder',?,'legacy-folder','Legacy',UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$uid]);
    $pdo->prepare("INSERT INTO favorites (id,user_id,client_id,folder_id,name,url,created_at,updated_at) VALUES ('legacy-favorite',?,'legacy-favorite','legacy-folder','Legacy favorite','https://example.test',UTC_TIMESTAMP(),UTC_TIMESTAMP())")->execute([$uid]);
    $legacy=$repo->read($uid);
    $check($legacy['version']===0 && $legacy['document']->favorites->{'legacy-favorite'}->folderId==='legacy-folder','existing relational data loaded before first sync');
    $check($repo->write($uid,0,$legacy['document'])['version']===1,'first sync preserves legacy favorites');
    $doc=json_decode('{"settings":{"theme":"dark","fontSize":18},"favorite-folders":{"folder":{"id":"folder","name":"Work","sortOrder":0}},"favorites":{"same":{"id":"same","name":"Favorite","url":"https://example.test","folderId":"folder","tags":["é","e"],"sortOrder":1700000000000,"usageCount":2,"lastAccess":1700000000000}},"history":{"h":{"id":"h","query":"search","provider":"google","mode":"web","at":1700000000000}},"providers-web":{"google":{"id":"google","name":"Google","url":"https://google.com/?q={query}","prefix":"g","sortOrder":0}},"providers-ai":{"claude":{"id":"claude","name":"Claude","url":"https://claude.ai/new","prefix":"cl","copy":true}}}');
    $doc->history->h->at=(int)floor(microtime(true)*1000);
    $saved=$repo->write($uid,1,$doc);$check($saved['version']===2,'document transaction advances');
    $favorite=$rows('favorites',$uid)[0];$folder=$rows('favorite_folders',$uid)[0];
    $check($favorite['client_id']==='same' && $favorite['folder_id']===$folder['id'],'client IDs map to owned relational folder');
    $check((int)$favorite['sort_order']===1700000000000,'millisecond order fits DB');
    $check((int)$favorite['usage_count']===2 && $favorite['last_access_at']==='2023-11-14 22:13:20','usage and UTC access projected');
    $check(count($rows('tags',$uid))===2 && count($rows('favorite_tags',$uid))===2,'accent-distinct tags and links preserved');
    $check(count($rows('user_settings',$uid))===2,'settings projected per key');
    $check($rows('search_history',$uid)[0]['query']==='search','history projected');
    $check($rows('search_engines',$uid)[0]['client_id']==='google','search engine projected');
    $check(json_decode($rows('ai_providers',$uid)[0]['payload'])->copy===true,'AI extra fields preserved');
    $projected=(new SyncProjectionRepository($pdo))->read($uid);
    $check(json_encode($projected->favorites->same)===json_encode($doc->favorites->same),'projection reconstructs complete favorite');
    $check($repo->write($other,0,$doc)['version']===1,'same client IDs accepted for other owner');
    $check($rows('favorites',$other)[0]['id']!==$favorite['id'],'owners have distinct internal IDs');
    $check($repo->write($uid,1,(object)[])===null && count($rows('favorites',$uid))===1,'stale sync cannot remove relational rows');
    $pdo->exec(sprintf('ALTER TABLE user_settings ADD CONSTRAINT sync_projection_failure CHECK (user_id<>%d OR setting_key<>\'theme\' OR setting_value<>\'"light"\')',$uid));$constraint=true;
    $changed=json_decode(json_encode($doc));$changed->settings->theme='light';
    try {$repo->write($uid,2,$changed);throw new RuntimeException('late failure not raised');}catch(PDOException) {}
    $check($repo->read($uid)['version']===2 && $repo->read($uid)['document']->settings->theme==='dark','late SQL failure rolls back canonical state');
    $settings=array_column($rows('user_settings',$uid),'setting_value','setting_key');
    $check(count($rows('favorites',$uid))===1 && json_decode($settings['theme'])==='dark','late failure rolls back relational deletion and settings');
    $dropConstraint();$constraint=false;
    $changed=json_decode(json_encode($doc));unset($changed->favorites->same,$changed->{'favorite-folders'}->folder);
    $check($repo->write($uid,2,$changed)['version']===3,'entity deletion commits');
    $check($rows('favorites',$uid)===[] && $rows('favorite_folders',$uid)===[] && $rows('tags',$uid)===[],'deletion removes owned relations');
    $versions=$pdo->prepare("SELECT version FROM sync_versions WHERE user_id=? AND entity_type='favorites' AND entity_id='same'");$versions->execute([$uid]);
    $check((int)$versions->fetchColumn()===3,'deleted entity retains version tombstone');
    $check(count($rows('favorites',$other))===1,'deletion leaves other owner intact');
    $badCases=[
        '{"favorites":{"f":{"id":"f","name":"F","url":"https://example.test","folderId":"foreign"}}}',
        '{"favorites":{"f":{"id":"f","name":"F","url":"https://user:secret@example.test"}}}',
        '{"favorites":{"f":{"id":"f","name":"F","url":"https://example.test","usageCount":-1}}}',
        '{"favorites":{"f":{"id":"f","name":"F","url":"https://example.test","visible":"true"}}}',
        '{"favorites":{"f":{"id":"f","name":"F","url":"https://example.test","tags":["Tag","tag"]}}}',
        '{"favorites":{"f":{"id":"f","name":"F","url":"https://example.test","shortcut":"Control+Control+a"}}}',
        '{"favorite-folders":{"a":{"id":"a","name":"Work"},"b":{"id":"b","name":"work"}}}',
        '{"providers-web":{"a":{"id":"a","name":"A","url":"https://example.test","prefix":"g"},"b":{"id":"b","name":"B","url":"https://example.test","prefix":"G"}}}',
        '{"history":{"a":{"id":"a","query":"Q","provider":"g","mode":"unsafe","at":1}}}',
        '{"settings":{"syncHistory":true}}',
        '{"settings":{"historyLimit":0}}',
        '{"settings":{"historyDays":-1}}',
        '{"settings":{"syncRules":{"invalid":"cloud"}}}',
    ];
    foreach($badCases as $bad) {
        try {SyncDocument::validate(json_decode($bad));throw new RuntimeException('invalid entity accepted');}
        catch(HttpException $error) {$check($error->status===422,'invalid entity rejected');}
    }
} finally {
    if($constraint)$dropConstraint();
    $pdo->prepare('DELETE FROM users WHERE id IN (?,?)')->execute([$uid,$other]);
}
echo "$count projection assertions passed.\n";
