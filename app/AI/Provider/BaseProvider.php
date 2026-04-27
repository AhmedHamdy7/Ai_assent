<?php

namespace App\AI\Provider;

use App\AI\Agent\BaseAgent;

abstract class BaseProvider
{
    public function __construct(protected int $timeout) {}

    abstract public function askAiFuture(
        BaseAgent $agent,
        string $modelName,
        array $messages,
    ): AiModel;
}
