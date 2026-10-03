<?php
declare(strict_types=1);
if(getenv('SEARCH_TEST_MODE')!=='1'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/app/autoload.php';
$messages=(new \App\Helpers\Translator(dirname(__DIR__,2),'ja'))->messages();
$boot=json_encode(['messages'=>$messages,'providers'=>[]],JSON_THROW_ON_ERROR|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
(new \App\Http\Response('<!doctype html><html lang="ja"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>地域設定の保存検証</title><link rel="stylesheet" href="/assets/css/core.css"><link rel="stylesheet" href="/assets/css/search.css"><main class="panel"><h1>地域設定の保存検証</h1><p>位置は生成データだけを使用します。OSの位置取得・通常ページの保存領域には触れません。</p><button id="success">生成位置を返す</button><button id="denied">許可拒否を返す</button><button id="failure">保存失敗を有効にする</button><button id="retry">保存失敗を解除</button><button id="discard">入力を破棄</button><section id="settings-appearance"></section><p id="saved" role="status"></p><script id="search-bootstrap" type="application/json">'.$boot.'</script><script type="module" src="/_test/region-preview.mjs"></script></main></html>'))->send();
