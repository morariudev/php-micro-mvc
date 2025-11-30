<?php

namespace Framework\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class JsonBodyMiddleware implements MiddlewareInterface
{
    private bool $merge = false;

    public function enableMerge(): self
    {
        $this->merge = true;
        return $this;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = strtolower($request->getHeaderLine('Content-Type'));

        if ($this->isJson($contentType)) {

            $raw = (string)$request->getBody();

            if ($raw !== '') {
                $decoded = json_decode($raw, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->jsonError('Invalid JSON: ' . json_last_error_msg());
                }

                if (is_array($decoded)) {
                    $existing = $request->getParsedBody();

                    if ($this->merge && is_array($existing)) {
                        $decoded = array_merge($existing, $decoded);
                    }

                    $request = $request->withParsedBody($decoded);
                }
            }
        }

        return $handler->handle($request);
    }

    private function isJson(string $contentType): bool
    {
        return str_starts_with($contentType, 'application/json');
    }

    private function jsonError(string $message): ResponseInterface
    {
        $factory = new Psr17Factory();
        $response = $factory->createResponse(400);

        $response->getBody()->write(json_encode([
            'error' => $message
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
