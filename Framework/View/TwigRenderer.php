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
            'cache' => false,      // set to 'cache/twig' for production
            'autoescape' => 'html'
        ]);

        $this->factory = new Psr17Factory();

        $this->registerHelpers();
    }

    private function registerHelpers(): void
    {
        /**
         * {{ csrf() }}
         */
        $this->twig->addFunction(new TwigFunction('csrf', function () {
            $token = $_SESSION['_csrf_token'] ?? '';
            return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token) . '">';
        }, ['is_safe' => ['html']]));

        /**
         * {{ asset('css/app.css') }}
         */
        $this->twig->addFunction(new TwigFunction('asset', function (string $path) {
            return '/public/' . ltrim($path, '/');
        }));

        /**
         * {{ url('/user/' ~ user.id) }}
         */
        $this->twig->addFunction(new TwigFunction('url', function (string $path) {
            return $path;
        }));
    }

    /**
     * Render Twig view into PSR-7 response
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

    public function createJsonResponse(string $json, int $status = 200): ResponseInterface
    {
        $response = $this->factory->createResponse($status);
        $response->getBody()->write($json);

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }
}
