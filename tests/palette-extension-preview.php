<?php
declare(strict_types=1);
if (getenv('SEARCH_TEST_MODE') !== '1') { http_response_code(404); exit; }
// Copy only into public/_test in the isolated test container, never production.
$_SERVER['REQUEST_URI'] = '/';
require dirname(__DIR__, 2) . '/app/bootstrap.php';
echo '<aside class="panel"><h2>Palette extension verification</h2><p>Generated command only; no external requests or account changes.</p><button id="extension-register">Register test command</button><button id="extension-remove">Remove test command</button><button id="extension-fail">Fail next execution</button><button id="history-prepare">Prepare localhost search provider</button><output id="extension-status" role="status"></output></aside><script type="module" src="/_test/palette-extension-preview.mjs"></script>';
