<?php
declare(strict_types=1);
if (getenv('SEARCH_TEST_MODE') !== '1') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/app/autoload.php';
$messages = (new \App\Helpers\Translator(dirname(__DIR__, 2), 'ja'))->messages();
$boot = json_encode(['messages' => $messages, 'providers' => []], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
(new \App\Http\Response('<!doctype html><html lang="ja"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>設定保存の競合検証</title><link rel="stylesheet" href="/assets/css/core.css"><main class="panel"><h1>設定保存の競合検証</h1><p>専用テストoriginで使用。生成位置だけを保存し、OSの位置取得・クラウド送信は行いません。</p><button id="stress">無関係な更新と25回の地域保存を検証</button><section id="settings-appearance"></section><p id="result" role="status"></p><p id="saved" role="status"></p><script id="search-bootstrap" type="application/json">'.$boot.'</script><script type="module" src="/_test/settings-contention-preview.mjs"></script></main></html>'))->send();
