<?php

namespace Framework\Events;

class EventDispatcher
{
    /** @var array<string, array<int, EventListenerInterface>> */
    private array $listeners = [];

    public function addListener(string $eventName, EventListenerInterface $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    /**
     * @param mixed $payload
     */
    public function dispatch(string $eventName, $payload = null): void
    {
        if (empty($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $listener) {
            $listener->handle($eventName, $payload);
        }
    }
}
