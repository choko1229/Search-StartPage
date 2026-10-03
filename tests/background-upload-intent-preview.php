<?php
declare(strict_types=1);
if(getenv('SEARCH_TEST_MODE')!=='1'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/app/autoload.php';
(new \App\Http\Response('<!doctype html><html lang="ja"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>背景送信記録の保存検証</title><main class="panel"><h1>背景送信記録の保存検証</h1><p>専用の保存領域で検証します。通常ページのデータと認証には触れません。</p><button id="prepare">背景と送信記録を保存</button><button id="failure">保存失敗と競合時の保持を確認</button><button id="ack">保存結果を確定</button><p id="result" role="status"></p><p id="loaded"></p><img id="preview" alt="保存された検証画像" width="96" height="96"></main><script type="module" src="/_test/background-upload-intent-preview.mjs"></script></html>'))->send();
