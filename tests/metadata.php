<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/autoload.php';
$service = new App\Services\MetadataService();
foreach (['http://127.0.0.1','http://10.0.0.1','http://169.254.169.254','http://192.168.1.1','http://100.64.0.1','file:///etc/passwd','http://localhost:8080','http://user:pass@example.com'] as $url) {
    try { $service->publicDestination($url); throw new RuntimeException('Unsafe destination accepted'); }
    catch (App\Http\HttpException $error) { if ($error->status !== 422) { throw $error; } }
}
echo "Metadata SSRF rejection: 8 cases passed.\n";
