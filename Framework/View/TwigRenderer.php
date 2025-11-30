<?php

namespace Framework\View;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Framework\Session\SessionManager;

/**
 * Class TwigRenderer
 *
 * Handles rendering Twig templates and provides helper functions.
 * Supports optional caching in production mode.
 */
class TwigRenderer
{
    private Environment $twig;
    private Psr17Factory $factory;
    private SessionManager $session;
    private bool $debug;

    /**
     * TwigRenderer constructor.
     *
     * @param string $viewsPath Path to the Twig templates
     * @param SessionManager $session The session manager instance (required)
     * @param bool $debug Enable debug mode (optional, default true)
     */
    public function __construct(string $viewsPath, SessionManager $session, bool $debug = true)
    {
        $this->session = $session;
        $this->debug   = $debug;

        // Filesystem loader for Twig
        $loader = new FilesystemLoader($viewsPath);

        // Cache folder only in non-debug mode
        $cachePath = $debug ? false : __DIR__ . '/../../cache/twig';

        if ($cachePath && !is_dir($cachePath)) {
            mkdir($cachePath, 0775, true);
        }

        $this->twig = new Environment($loader, [
            'cache'      => $cachePath,
            'autoescape' => 'html',
            'debug'      => $debug,
        ]);

        $this->factory = new Psr17Factory();

        $this->registerHelpers();
    }

    /**
     * Register custom Twig helper functions.
     */
    private function registerHelpers(): void
    {
        // ----------------------------------------------------
        // CSRF token helper: {{ csrf() }}
        // ----------------------------------------------------
        $this->twig->addFunction(new TwigFunction('csrf', function () {
            $token = $this->session->get('_csrf_token') ?? '';
            return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        }, ['is_safe' => ['html']]));

        // ----------------------------------------------------
        // Asset URL helper: {{ asset('css/app.css') }}
        // ----------------------------------------------------
        $this->twig->addFunction(new TwigFunction('asset', function (string $path) {
            return '/' . ltrim($path, '/');
        }));

        // ----------------------------------------------------
        // Root-relative URL helper: {{ url('/users/5') }}
        // ----------------------------------------------------
        $this->twig->addFunction(new TwigFunction('url', function (string $path) {
            return '/' . ltrim($path, '/');
        }));

        // ----------------------------------------------------
        // Flash message helper: {{ flash('success') }}
        // ----------------------------------------------------
        $this->twig->addFunction(new TwigFunction('flash', function (string $key) {
            return $this->session->getFlash($key);
        }));
    }

    /**
     * Render a Twig template into a PSR-7 response.
     *
     * @param string $template
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): ResponseInterface
    {
        $response = $this->factory->createResponse(200);
        $html     = $this->twig->render($template, $data);

        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Return a JSON PSR-7 response.
     */
    public function createJsonResponse(string $json, int $status = 200): ResponseInterface
    {
        $response = $this->factory->createResponse($status);
        $response->getBody()->write($json);

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Access raw Twig Environment object (for adding filters, globals, etc.)
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }
}
