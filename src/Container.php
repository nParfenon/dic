<?php

namespace nParfenon\dic;

class Container implements ContainerInterface
{
    private array $_definitions = [];

    private array $_params = [];

    private array $_singletons = [];

    public function transient(string $class, mixed $definition = [], array $params = []): void
    {
        $this->_definitions[$class] = $this->normalizeDefinition($class, $definition);

        $this->_params[$class] = $params;

        unset($this->_singletons[$class]);
    }

    public function singleton(string $class, mixed $definition = [], array $params = []): void
    {
        $this->_definitions[$class] = $this->normalizeDefinition($class, $definition);

        $this->_params[$class] = $params;

        $this->_singletons[$class] = null;
    }

    public function get(string $class, array $params = [])
    {
        if (isset($this->_singletons[$class])) {
            return $this->_singletons[$class];
        } elseif (!isset($this->_definitions[$class])) {
            return $this->build($class, $params);
        }

        $definition = $this->_definitions[$class];

        if (is_array($definition))
        {
            $concrete = $definition['class'];

            $params = $this->_params[$class];

            if ($concrete === $class) {
                $object = $this->build($class, $params);
            } else {
                $object = $this->get($concrete, $params);
            }
        } elseif (is_callable($definition)) {
            $object = call_user_func($definition, $params);
        } elseif (is_object($definition)) {
            return $this->_singletons[$class] = $definition;
        } else {
            throw new InvalidConfigContainerException('Unexpected object definition type '. gettype($definition));
        }

        if (array_key_exists($class, $this->_singletons)) {
            $this->_singletons[$class] = $object;
        }

        return $object;
    }

    public function has(string $class)
    {
        return isset($this->_definitions[$class]);
    }

    private function normalizeDefinition($class, $definition = [])
    {
        if (empty($definition)) {
            return ['class' => $class];
        } elseif (is_string($definition)) {
            return ['class' => $definition];
        } elseif (is_callable($definition) || is_object($definition)) {
            return $definition;
        } elseif (is_array($definition)) {
            if (!isset($definition['class'])) {
                $definition['class'] = $class;
            }
            return $definition;
        }

        throw new InvalidConfigContainerException("Unsupported definition type for $class: " . gettype($definition));
    }

    private function build($class, $params)
    {
        list($reflection, $dependencies) = $this->getDependencies($class);

        $dependencies = $this->resolveDependencies($dependencies);

        $dependencies = array_merge($dependencies, $params);

        if (!$reflection->isInstantiable()) {
            throw new NotInstantiableContainerException('Can not instantiate '. $reflection->name);
        }

        return $reflection->newInstanceArgs($dependencies);
    }

    private function getDependencies($class)
    {
        $dependencies = [];

        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new NotInstantiableContainerException("Failed to instantiate class $class");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor) {
            foreach ($constructor->getParameters() as $parameter) {
                $type = $parameter->getType();

                if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                    $dependencies[$parameter->getName()] = $type->getName();
                }
            }
        }

        return [$reflection, $dependencies];
    }

    private function resolveDependencies($dependencies)
    {
        foreach ($dependencies as $index => $dependency) {
            $dependencies[$index] = $this->get($dependency);
        }

        return $dependencies;
    }
}