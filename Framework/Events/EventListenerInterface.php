<?php

namespace Framework\Events;

interface EventListenerInterface
{
    /**
     * Handle the event.
     *
     * Return:
     *   - mixed value → returned to the dispatcher
     *   - EventResult::stop(...) → stop propagation
     *   - null → continue normally
     *
     * @param mixed $payload
     * @return mixed
     */
    public function handle(string $eventName, $payload = null);
}
