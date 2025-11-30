<?php

namespace Framework\Session;

class SessionManager
{
    private bool $started = false;

    /**
     * Start the session safely.
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        // If headers already sent, session_start() will explode
        if (headers_sent($file, $line)) {
            throw new \RuntimeException(
                "Cannot start session: headers already sent in $file:$line"
            );
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'use_strict_mode' => true,
                'cookie_httponly' => true,
                'cookie_secure'   => isset($_SERVER['HTTPS']),
                'cookie_samesite' => 'Lax',
            ]);
        }

        // Ensure flash array exists
        if (!isset($_SESSION['_flash']) || !is_array($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }

        $this->started = true;
    }

    /**
     * Get a session value.
     */
    public function get(string $key)
    {
        $this->start();
        return $_SESSION[$key] ?? null;
    }

    /**
     * Set a value.
     */
    public function set(string $key, $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    /**
     * Remove a key.
     */
    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /**
     * Destroy session completely.
     */
    public function destroy(): void
    {
        $this->start();

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $this->started = false;
    }

    /**
     * Store flash values for next request only.
     */
    public function flash(string $key, $value): void
    {
        $this->start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Retrieve and remove a flash message.
     */
    public function getFlash(string $key, $default = null)
    {
        $this->start();

        if (!array_key_exists($key, $_SESSION['_flash'])) {
            return $default;
        }

        $value = $_SESSION['_flash'][$key];
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    /**
     * Retrieve all flash messages.
     * Removes them after reading.
     *
     * @return array<string, mixed>
     */
    public function allFlash(): array
    {
        $this->start();

        $flash = $_SESSION['_flash'];
        $_SESSION['_flash'] = [];
        return $flash;
    }

    /**
     * Regenerate session ID — for login security.
     */
    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }
}
