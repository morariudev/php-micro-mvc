<?php

namespace Framework\Events;

class EventResult
{
    private mixed $value;
    private bool $stop;

    private function __construct(mixed $value, bool $stop)
    {
        $this->value = $value;
        $this->stop = $stop;
    }

    public static function stop(mixed $value = null): self
    {
        return new self($value, true);
    }

    public static function value(mixed $value): self
    {
        return new self($value, false);
    }

    public function shouldStop(): bool
    {
        return $this->stop;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
