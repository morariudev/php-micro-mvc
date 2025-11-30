<?php

namespace Framework\View;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;
use Framework\Session\SessionManager;

class TwigRenderer
{
    private Environment $twig;
    private Psr17Factory $factory;
    private SessionManager $session;

    /**
     * @param string $viewsPath Path to Twig templates
     * @param bool $debug Enable debug mode (disables caching)
     * @param SessionManager|null $session Session instance for helpers
     */
    public function __construct(string $viewsPath, bool $debug = true, ?SessionManager $session = null)
    {
        $loader = new FilesystemLoader($viewsPath);

        // Cache path only in non-debug mode
        $cachePath = $debug ? false : __DIR__ . '/../../cache/twig';

        if ($cachePath !== false && !is_dir($cachePath)) {
            mkdir($cachePath, 0775, true);
        }

        $this->twig = new Environment($loader, [
            'cache'      => $cachePath,
            'autoescape' => 'html',
            'debug'      => $debug,
        ]);

        $this->factory = new Psr17Factory();
        $this->session = $session ?? new SessionManager();

        $this->registerHelpers();
    }

    /**
     * Register custom Twig functions (helpers)
     */
    private function registerHelpers(): void
    {
        // CSRF token helper
        $this->twig->addFunction(new TwigFunction('csrf', function () {
            $token = $this->session->get('_csrf_token') ?? '';
            return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        }, ['is_safe' => ['html']]));

        // Asset URL helper
        $this->twig->addFunction(new TwigFunction('asset', function (string $path) {
            return '/' . ltrim($path, '/');
        }));

        // URL helper
        $this->twig->addFunction(new TwigFunction('url', function (string $path) {
            return '/' . ltrim($path, '/');
        }));

        // Flash messages helper
        $this->twig->addFunction(new TwigFunction('flash', function (string $key) {
            return $this->session->getFlash($key);
        }));
    }

    /**
     * Render a Twig template into a PSR-7 Response
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): ResponseInterface
    {
        $response = $this->factory->createResponse(200);
        $html = $this->twig->render($template, $data);
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * Create a JSON PSR-7 response
     */
    public function createJsonResponse(string $json, int $status = 200): ResponseInterface
    {
        $response = $this->factory->createResponse($status);
        $response->getBody()->write($json);

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Access raw Twig environment
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }
}
