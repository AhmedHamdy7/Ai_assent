<?php

namespace App\AI\Tool;

use App\AI\Provider\ToolResult;

abstract class BaseTool
{
    abstract public function getName(): string;

    abstract public function getDescription(): string;

    abstract public function getParameters(): array;

    abstract public function execute(array $arguments): ToolResult;
}
