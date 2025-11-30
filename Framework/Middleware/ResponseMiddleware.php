<?php

namespace Framework\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ResponseMiddleware
 *
 * Handles:
 * - Cache-Control headers & ETag
 * - Optional Gzip compression
 * - Content negotiation (HTML / JSON) based on Accept header
 */
class ResponseMiddleware implements MiddlewareInterface
{
    private bool $gzip;

    /**
     * @param bool $gzip Enable Gzip/Deflate compression
     */
    public function __construct(bool $gzip = true)
    {
        $this->gzip = $gzip;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // -----------------------------
        // 1) Cache-Control & ETag
        // -----------------------------
        if ($response->getStatusCode() === 200) {
            $body = (string) $response->getBody();
            $etag = '"' . md5($body) . '"';

            $response = $response
                ->withHeader('ETag', $etag)
                ->withHeader('Cache-Control', 'private, max-age=60');

            $ifNoneMatch = $request->getHeaderLine('If-None-Match');
            if ($ifNoneMatch === $etag) {
                // Client cache is valid
                $response = $response->withStatus(304)->withBody(new \Nyholm\Psr7\Stream(fopen('php://temp', 'r+')));
            }
        }

        // -----------------------------
        // 2) Content Negotiation
        // -----------------------------
        $accept = $request->getHeaderLine('Accept');
        if (str_contains($accept, 'application/json') && strpos($response->getHeaderLine('Content-Type'), 'json') === false) {
            // If client prefers JSON but response is HTML, wrap it in a JSON object
            $content = (string) $response->getBody();
            $jsonBody = json_encode(['html' => $content], JSON_UNESCAPED_UNICODE);
            $response->getBody()->rewind();
            $response->getBody()->write($jsonBody);
            $response = $response->withHeader('Content-Type', 'application/json; charset=utf-8');
        }

        // -----------------------------
        // 3) Gzip compression (optional)
        // -----------------------------
        if ($this->gzip && str_contains($request->getHeaderLine('Accept-Encoding'), 'gzip')) {
            $body = (string) $response->getBody();
            $compressed = gzencode($body);

            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $compressed);
            rewind($stream);

            $response = $response
                ->withBody(new \Nyholm\Psr7\Stream($stream))
                ->withHeader('Content-Encoding', 'gzip')
                ->withHeader('Vary', 'Accept-Encoding')
                ->withHeader('Content-Length', (string) strlen($compressed));
        }

        return $response;
    }
}
