<?php
declare(strict_types=1);
if(getenv('SEARCH_TEST_MODE')!=='1'){http_response_code(404);exit;}
// Installed only in the isolated UI container, outside production source routes.
$_SERVER['REQUEST_URI']='/';
require dirname(__DIR__,2).'/app/bootstrap.php';
echo '<aside><button type="button" id="choose">生成した検証画像を選択</button><output id="status" aria-live="polite">背景カテゴリを開いてください</output></aside><script type="module" src="/_test/background-file-preview.mjs"></script>';
