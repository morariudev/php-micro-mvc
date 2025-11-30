<?php

namespace Framework\Middleware;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class JsonBodyMiddleware implements MiddlewareInterface
{
    /**
     * If true: JSON merges with existing parsed body.
     * If false: JSON replaces it entirely.
     */
    private bool $merge = false;

    public function enableMerge(): self
    {
        $this->merge = true;
        return $this;
    }

    public function process(ServerRequestInterface $request, callable $next): ResponseInterface
    {
        $contentType = strtolower($request->getHeaderLine('Content-Type'));

        if ($this->isJsonContentType($contentType)) {

            // Extract raw body safely
            $body = (string) $request->getBody();

            if ($body !== '') {
                $data = json_decode($body, true);

                // If JSON is invalid, return a structured 400 error
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->jsonError('Invalid JSON payload: ' . json_last_error_msg());
                }

                if (is_array($data)) {
                    $existing = $request->getParsedBody();

                    if ($this->merge && is_array($existing)) {
                        $data = array_merge($existing, $data);
                    }

                    // Override parsed body
                    $request = $request->withParsedBody($data);
                }
            }
        }

        return $next($request);
    }

    private function isJsonContentType(string $contentType): bool
    {
        return
            str_starts_with($contentType, 'application/json') ||
            str_starts_with($contentType, 'application/ld+json') ||
            str_starts_with($contentType, 'application/vnd.api+json');
    }

    private function jsonError(string $message): ResponseInterface
    {
        $factory = new Psr17Factory();

        $response = $factory->createResponse(400);
        $response->getBody()->write(json_encode([
            'error' => $message,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
