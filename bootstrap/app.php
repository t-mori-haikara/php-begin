<?php

declare(strict_types=1);

use App\Http\Action\Top\TopAction;
use Laminas\HttpHandlerRunner\Emitter\SapiEmitter;
use League\Route\Router;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// 何はともあれオートローダーを読み込む
require_once __DIR__ . '/../vendor/autoload.php';

$container = require_once __DIR__ . '/../bootstrap/dependencies.php';
assert($container instanceof ContainerInterface);

// Routerを取得
$router = $container->get(Router::class);
assert($router instanceof Router);

// ルーティング
$router->get('/', TopAction::class);

$router->get('/list', function () use ($container): ResponseInterface {
    $responseFactory = $container->get(ResponseFactoryInterface::class);
    assert($responseFactory instanceof ResponseFactoryInterface);

    $response = $responseFactory->createResponse();
    $response->getBody()->write("<p>何かの一覧画面</p>");

    return $response;
});

// Requestを取得
$request = $container->get(ServerRequestInterface::class);
assert($request instanceof ServerRequestInterface);

$response = $router->dispatch($request);

(new SapiEmitter)->emit($response);
