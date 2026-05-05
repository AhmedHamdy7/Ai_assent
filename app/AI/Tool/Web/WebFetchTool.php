<?php

namespace App\AI\Tool\Web;

class WebFetchTool extends FetchUrlTool
{
    public function getName(): string
    {
        return 'web_fetch';
    }
}
