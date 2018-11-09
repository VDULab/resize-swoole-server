<?php

namespace ResizeServer\Http;

interface RewriteRuleStorageInterface
{
    public function getRules(): array;
    public function addPaths(array $paths): void;
}
