<?php

namespace Framework\View;

use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

class TwigRenderer
{
    private Environment $twig;
    private Psr17Factory $factory;

    public function __construct(string $viewsPath)
    {
        $loader = new FilesystemLoader($viewsPath);

        $this->twig = new Environment($loader, [
            'cache'      => false, // change to /cache/twig for prod
            'autoescape' => 'html'
        ]);

        $this->factory = new Psr17Factory();

        $this->registerHelpers();
    }

    /**
     * Register custom Twig functions (helpers)
     */
    private function registerHelpers(): void
    {
        /**
         * ----------------------------------------------------
         * {{ csrf() }}
         * Prints a hidden input with the session CSRF token.
         * ----------------------------------------------------
         */
        $this->twig->addFunction(new TwigFunction('csrf', function () {
            $token = $_SESSION['_csrf_token'] ?? '';
            return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        }, ['is_safe' => ['html']]));

        /**
         * ----------------------------------------------------
         * {{ asset('css/app.css') }}
         * Produces: /css/app.css
         * (No /public prefix — public directory IS the web root)
         * ----------------------------------------------------
         */
        $this->twig->addFunction(new TwigFunction('asset', function (string $path) {
            return '/' . ltrim($path, '/');
        }));

        /**
         * ----------------------------------------------------
         * {{ url('users') }} → /users
         * {{ url('/users/5') }} → /users/5
         * Simple, root-relative URL generator.
         * ----------------------------------------------------
         */
        $this->twig->addFunction(new TwigFunction('url', function (string $path) {
            return '/' . ltrim($path, '/');
        }));

        /**
         * ----------------------------------------------------
         * {{ flash('success') }}
         * Retrieves and clears a flash message.
         *
         * IMPORTANT: this helper ONLY retrieves.
         * Flash storage is handled in SessionManager.
         * ----------------------------------------------------
         */
        $this->twig->addFunction(new TwigFunction('flash', function (string $key) {
            if (!isset($_SESSION['_flash'][$key])) {
                return null;
            }

            $value = $_SESSION['_flash'][$key];
            unset($_SESSION['_flash'][$key]);
            return $value;
        }));
    }

    /**
     * Render a Twig view into a PSR-7 Response.
     *
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
     * Create a JSON PSR-7 response.
     */
    public function createJsonResponse(string $json, int $status = 200): ResponseInterface
    {
        $response = $this->factory->createResponse($status);
        $response->getBody()->write($json);

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Access raw Twig environment (for adding filters, globals, etc.)
     */
    public function getTwig(): Environment
    {
        return $this->twig;
    }
}
