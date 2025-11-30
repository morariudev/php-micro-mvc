<?php

namespace Framework\Events;

class EventDispatcher
{
    /**
     * @var array<string, array<int, array{priority:int, listener:callable|EventListenerInterface}>>
     */
    private array $listeners = [];

    /**
     * Add a listener with optional priority (higher = earlier).
     */
    public function addListener(string $eventName, callable|EventListenerInterface $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][] = [
            'priority' => $priority,
            'listener' => $listener,
        ];

        // Sort by priority (descending)
        usort($this->listeners[$eventName], fn($a, $b) => $b['priority'] <=> $a['priority']);
    }

    /**
     * Remove a listener.
     */
    public function removeListener(string $eventName, callable|EventListenerInterface $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        $this->listeners[$eventName] = array_filter(
            $this->listeners[$eventName],
            fn($entry) => $entry['listener'] !== $listener
        );

        if (empty($this->listeners[$eventName])) {
            unset($this->listeners[$eventName]);
        }
    }

    /**
     * Dispatch an event.
     *
     * Returns:
     *   - The last listener's return value
     *   - Or null if no listener handled the event
     *
     * Supports:
     *   - Stopping propagation by returning EventResult::stop()
     *   - Wildcard listeners (e.g. "user.*")
     *
     * @param mixed $payload
     */
    public function dispatch(string $eventName, $payload = null)
    {
        $listeners = $this->getListenersForEvent($eventName);

        $result = null;

        foreach ($listeners as $entry) {
            $listener = $entry['listener'];

            // Listener object
            if ($listener instanceof EventListenerInterface) {
                $response = $listener->handle($eventName, $payload);
            }
            // Callable listener (Closure, function, invokable class)
            else {
                $response = $listener($eventName, $payload);
            }

            // Stop propagation?
            if ($response instanceof EventResult && $response->shouldStop()) {
                return $response->getValue();
            }

            // Store last non-null result
            if ($response !== null) {
                $result = $response;
            }
        }

        return $result;
    }

    /**
     * Return listeners for:
     *   - The exact event
     *   - Wildcard listeners (e.g. "user.*")
     *
     * Sorted by priority.
     *
     * @return array<int, array{priority:int, listener:mixed}>
     */
    private function getListenersForEvent(string $eventName): array
    {
        $matched = [];

        // Exact listeners
        if (isset($this->listeners[$eventName])) {
            $matched = $this->listeners[$eventName];
        }

        // Wildcard listeners: event "user.created" matches "user.*"
        foreach ($this->listeners as $pattern => $listeners) {
            if (str_contains($pattern, '*')) {
                $regex = '/^' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '$/';

                if (preg_match($regex, $eventName)) {
                    $matched = array_merge($matched, $listeners);
                }
            }
        }

        // Sort by priority (high → low)
        usort($matched, fn($a, $b) => $b['priority'] <=> $a['priority']);

        return $matched;
    }
}
