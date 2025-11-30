<?php

namespace Framework\Session;

class SessionManager
{
    private bool $started = false;

    public function start(): void
    {
        if ($this->started) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->started = true;
    }

    /**
     * @return mixed|null
     */
    public function get(string $key)
    {
        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            // Clear session array for current request
            $_SESSION = [];

            // Destroy session storage
            session_destroy();
        }

        $this->started = false;
    }

    public function flash(string $key, $value): void
    {
        $this->start();
        $_SESSION['_flash'][$key] = $value;
    }

    public function getFlash(string $key, $default = null)
    {
        $this->start();

        if (!isset($_SESSION['_flash'][$key])) {
            return $default;
        }

        $value = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function allFlash(): array
    {
        $this->start();

        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return $flash;
    }

}
