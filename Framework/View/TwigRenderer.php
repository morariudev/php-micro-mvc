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
     * Constructor.
     *
     * @param string      $viewsPath Path to Twig templates
     * @param bool        $debug     If true, disables cache (dev mode)
     * @param string|null $version   Optional cache version string for deploys
     */
    public function __construct(string $viewsPath, bool $debug = true, ?string $version = null)
    {
        $loader = new FilesystemLoader($viewsPath);

        // Set cache folder (disabled in debug)
        if ($debug) {
            $cachePath = false;
        } else {
            $versionSuffix = $version ? '-' . $version : '';
            $cachePath = __DIR__ . '/../../cache/twig' . $versionSuffix;

            // Ensure folder exists
            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0775, true);
            } else {
                // Optional: clear old compiled templates to avoid stale cache
                $this->clearCacheFolder($cachePath);
            }
        }

        // Initialize Twig environment
        $this->twig = new Environment($loader, [
            'cache'      => $cachePath,
            'autoescape' => 'html',
            'debug'      => $debug,
        ]);

        $this->factory = new Psr17Factory();

        $this->registerHelpers();
    }

    /**
     * Register custom Twig functions (helpers)
     */
    private function registerHelpers(): void
    {
        // CSRF token helper
        $this->twig->addFunction(new TwigFunction('csrf', function () {
            $token = $this->session?->get('_csrf_token') ?? '';
            return '<input type="hidden" name="_csrf" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
        }, ['is_safe' => ['html']]));

        // Asset path helper
        $this->twig->addFunction(new TwigFunction('asset', function (string $path) {
            return '/' . ltrim($path, '/');
        }));

        // URL helper
        $this->twig->addFunction(new TwigFunction('url', function (string $path) {
            return '/' . ltrim($path, '/');
        }));

        // Flash message helper
        $this->twig->addFunction(new TwigFunction('flash', function (string $key) {
            return $this->session?->getFlash($key);
        }));
    }

    /**
     * Render a Twig template into a PSR-7 response.
     *
     * @param string $template Template file path
     * @param array  $data     Variables to pass to template
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

    /**
     * Set the session manager for CSRF / flash helpers.
     */
    public function setSession(SessionManager $session): void
    {
        $this->session = $session;
    }

    /**
     * Optional: clear old compiled cache files.
     */
    private function clearCacheFolder(string $folder): void
    {
        $files = glob($folder . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
