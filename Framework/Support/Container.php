<?php

namespace Framework\Support;

use ReflectionClass;
use ReflectionException;

class Container
{
    /** @var array<string, mixed> */
    private array $instances = [];

    /** @var array<string, callable> */
    private array $bindings = [];

    public function set(string $id, $concrete): void
    {
        if (is_callable($concrete)) {
            $this->bindings[$id] = $concrete;
        } else {
            $this->instances[$id] = $concrete;
        }
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->bindings[$id]) || class_exists($id);
    }

    /**
     * @template T
     * @param class-string<T>|string $id
     * @return T|mixed
     */
    public function get(string $id)
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $this->instances[$id] = $this->bindings[$id]($this);
            return $this->instances[$id];
        }

        if (!class_exists($id)) {
            throw new \InvalidArgumentException('Class or binding not found: ' . $id);
        }

        try {
            $reflection = new ReflectionClass($id);
        } catch (ReflectionException $e) {
            throw new \RuntimeException('Unable to reflect class: ' . $id, 0, $e);
        }

        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException('Class is not instantiable: ' . $id);
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            $instance = new $id();
            $this->instances[$id] = $instance;

            return $instance;
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type !== null && !$type->isBuiltin()) {
                $dependencyClass = $type->getName();
                $dependencies[] = $this->get($dependencyClass);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \RuntimeException('Unresolvable dependency: ' . $parameter->getName() . ' in ' . $id);
            }
        }

        $instance = $reflection->newInstanceArgs($dependencies);
        $this->instances[$id] = $instance;

        return $instance;
    }
}
