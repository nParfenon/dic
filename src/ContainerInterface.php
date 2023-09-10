<?php

namespace nParfenon\dic;

interface ContainerInterface
{
    public function get(string $class, array $params = []);
    public function has(string $class);
}