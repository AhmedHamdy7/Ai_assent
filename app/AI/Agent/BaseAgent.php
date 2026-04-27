<?php

namespace App\AI\Agent;

use App\AI\Tool\BaseTool;

abstract class BaseAgent
{
    abstract public function getInstruction(): string;

    /**
     * @return BaseTool[]
     */
    abstract public function getTools(): array;
}
