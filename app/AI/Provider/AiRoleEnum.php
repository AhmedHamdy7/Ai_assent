<?php

namespace App\AI\Provider;

enum AiRoleEnum: string
{
    case System = 'system';
    case User = 'user';
    case Assistant = 'assistant';
    case Tool = 'tool';
}
