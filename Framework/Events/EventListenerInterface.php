<?php

namespace Framework\Events;

interface EventListenerInterface
{
    /**
     * @param mixed $payload
     */
    public function handle(string $eventName, $payload = null): void;
}
