<?php
declare(strict_types=1);
if (getenv('SEARCH_TEST_MODE') !== '1') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/app/autoload.php';
(new \App\Http\Response('<!doctype html><html lang="ja"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>パレット保存処理の検証</title><link rel="stylesheet" href="/assets/css/core.css"><link rel="stylesheet" href="/assets/css/search.css"><main class="panel"><h1>パレット保存処理の検証</h1><p>生成したデータと専用IndexedDBだけを使います。通常ページのユーザーデータ・認証・外部通信は使いません。</p><button id="verify">生成データで保存処理を検証</button><p id="result" role="status"></p><p id="loaded"></p></main><script type="module" src="/_test/palette-storage-preview.mjs"></script></html>'))->send();
