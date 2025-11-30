<?php

namespace Framework\Support;

use ReflectionClass;
use ReflectionException;

class Container
{
    /** @var array<string, mixed> Singleton instances */
    private array $instances = [];

    /** @var array<string, callable> Singleton bindings (lazy-loaded) */
    private array $bindings = [];

    /** @var array<string, callable> Factory bindings (new instance per get()) */
    private array $factories = [];

    /**
     * Register a singleton instance or binding.
     *
     * If $concrete is:
     *   - callable: lazily evaluated, stored as singleton
     *   - non-callable: stored directly as singleton instance
     */
    public function set(string $id, $concrete): void
    {
        if (is_callable($concrete)) {
            $this->bindings[$id] = $concrete;
        } else {
            $this->instances[$id] = $concrete;
        }
    }

    /**
     * Register a factory binding (returns a new instance each time).
     */
    public function factory(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /**
     * Check if container can resolve the given ID.
     */
    public function has(string $id): bool
    {
        return isset($this->instances[$id])
            || isset($this->bindings[$id])
            || isset($this->factories[$id])
            || class_exists($id);
    }

    /**
     * Resolve a dependency from the container.
     *
     * @template T
     * @param class-string<T>|string $id
     * @return T|mixed
     */
    public function get(string $id)
    {
        // 1. Existing singleton instance
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // 2. Singleton binding → create instance, cache it
        if (isset($this->bindings[$id])) {
            $this->instances[$id] = $this->bindings[$id]($this);
            return $this->instances[$id];
        }

        // 3. Factory binding → return NEW instance every time
        if (isset($this->factories[$id])) {
            return $this->factories[$id]($this);
        }

        // 4. Class autowiring
        if (!class_exists($id)) {
            throw new \InvalidArgumentException("Class or binding not found: $id");
        }

        try {
            $reflection = new ReflectionClass($id);
        } catch (ReflectionException $e) {
            throw new \RuntimeException("Unable to reflect class: $id", 0, $e);
        }

        if (!$reflection->isInstantiable()) {
            throw new \RuntimeException("Class is not instantiable: $id");
        }

        $constructor = $reflection->getConstructor();

        // 5. Class with no constructor or no params → instantiate directly
        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            $instance = new $id();
            $this->instances[$id] = $instance;
            return $instance;
        }

        // 6. Resolve dependencies recursively
        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type !== null && !$type->isBuiltin()) {
                $dependencyClass = $type->getName();
                $dependencies[] = $this->get($dependencyClass);
            } elseif ($parameter->isDefaultValueAvailable()) {
                $dependencies[] = $parameter->getDefaultValue();
            } else {
                throw new \RuntimeException("Unresolvable dependency: {$parameter->getName()} in $id");
            }
        }

        // 7. Create instance with resolved deps and store singleton
        $instance = $reflection->newInstanceArgs($dependencies);
        $this->instances[$id] = $instance;

        return $instance;
    }
}
