<?php

namespace Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * Caches full HTTP responses to disk with ETag and Cache-Control.
 */
class ResponseCacheMiddleware implements MiddlewareInterface
{
    private string $cacheDir;
    private int $ttl;

    public function __construct(string $cacheDir, int $ttl = 60)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->ttl      = $ttl;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $key       = md5($request->getMethod() . '|' . $request->getUri()->getPath());
        $cacheFile = $this->cacheDir . '/' . $key . '.cache';
        $factory   = new Psr17Factory();

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->ttl) {
            $cached = unserialize(file_get_contents($cacheFile));

            $ifNoneMatch = $request->getHeaderLine('If-None-Match');
            $etag        = $cached->getHeaderLine('ETag');

            if ($ifNoneMatch === $etag) {
                return $factory->createResponse(304); // Not Modified
            }

            return $cached;
        }

        $response = $handler->handle($request);

        if ($response->getStatusCode() === 200) {
            $bodyContents = (string)$response->getBody();
            $etag         = '"' . md5($bodyContents) . '"';

            $response = $response
                ->withHeader('Cache-Control', 'public, max-age=' . $this->ttl)
                ->withHeader('ETag', $etag);

            file_put_contents($cacheFile, serialize($response));
        }

        return $response;
    }
}
