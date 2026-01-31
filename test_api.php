<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make('Illuminate\Contracts\Http\Kernel');

$request = Illuminate\Http\Request::create(
    '/api/cek-ketersediaan?check_in=2026-02-01&check_out=2026-02-02',
    'GET'
);

$response = $kernel->handle($request);
echo $response->getContent();
$kernel->terminate($request, $response);
