<?php

declare(strict_types=1);

namespace Elibardev\NotificationOrchestrator\Push;

use Illuminate\Http\Client\Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class GoogleAuthHttpHandler
{
    public function __construct(private Factory $http) {}

    /** @param array<string,mixed> $options */
    public function __invoke(RequestInterface $request, array $options = []): ResponseInterface
    {
        return $this->http->withHeaders($request->getHeaders())->withBody((string) $request->getBody(), $request->getHeaderLine('Content-Type'))
            ->connectTimeout(5)->timeout(15)->send($request->getMethod(), (string) $request->getUri())->toPsrResponse();
    }
}
