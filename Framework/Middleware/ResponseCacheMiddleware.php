<?php

namespace Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * ResponseCacheMiddleware
 *
 * Caches full HTTP responses to disk with ETag, Cache-Control, and optional Gzip compression.
 */
class ResponseCacheMiddleware implements MiddlewareInterface
{
    private string $cacheDir;
    private int $ttl;
    private bool $gzip;

    /**
     * @param string $cacheDir Directory to store cache files
     * @param int    $ttl      Time-to-live in seconds
     * @param bool   $gzip     Whether to compress cached responses
     */
    public function __construct(string $cacheDir, int $ttl = 60, bool $gzip = true)
    {
        $this->cacheDir = rtrim($cacheDir, '/');
        $this->ttl      = $ttl;
        $this->gzip     = $gzip;

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $uri       = $request->getUri();
        $key       = md5($request->getMethod() . '|' . $uri->__toString());
        $cacheFile = $this->cacheDir . '/' . $key . '.cache';
        $factory   = new Psr17Factory();

        // Serve cached response if valid
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $this->ttl) {
            $cached = unserialize(file_get_contents($cacheFile));

            $ifNoneMatch = $request->getHeaderLine('If-None-Match');
            $etag        = $cached->getHeaderLine('ETag');

            if ($ifNoneMatch === $etag) {
                return $factory->createResponse(304); // Not Modified
            }

            // Decompress if gzipped
            if ($this->gzip && $cached->hasHeader('X-Content-Encoded') && $cached->getHeaderLine('X-Content-Encoded') === 'gzip') {
                $body = gzdecode((string)$cached->getBody());
                $cached = $cached->withBody($factory->createStream($body))
                                 ->withoutHeader('X-Content-Encoded');
            }

            return $cached;
        }

        // Generate fresh response
        $response = $handler->handle($request);

        // Only cache 200 responses with HTML or JSON
        if ($response->getStatusCode() === 200) {
            $contentType = $response->getHeaderLine('Content-Type');

            if (str_contains($contentType, 'text/html') || str_contains($contentType, 'application/json')) {
                $bodyContents = (string)$response->getBody();

                if ($this->gzip) {
                    $bodyContents = gzencode($bodyContents, 5);
                    $response = $response->withHeader('X-Content-Encoded', 'gzip');
                }

                $etag = '"' . md5($bodyContents) . '"';

                $response = $response
                    ->withHeader('Cache-Control', 'public, max-age=' . $this->ttl)
                    ->withHeader('ETag', $etag)
                    ->withBody($factory->createStream($bodyContents));

                file_put_contents($cacheFile, serialize($response));
            }
        }

        return $response;
    }
}
