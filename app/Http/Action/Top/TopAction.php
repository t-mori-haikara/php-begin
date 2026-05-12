<?php

declare(strict_types=1);

namespace App\Http\Action\Top;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment as View;

class TopAction {
    public function __construct(
        protected ResponseFactoryInterface $responseFactory,
        protected View $view
    ) {}

    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        // クエリパラメータを取得
        $queryParams = $request->getQueryParams();
        $name = (string)filter_var($queryParams['name'] ?? '世界');

        // クエリパラメータをViewに渡してHTMLを生成
        $html = $this->view->render('top.twig.html', [
            'name' => $name,
        ]);

        // Responseを生成してHTMLを書き込む
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write($html);

        return $response;
    }
}
