<?php

namespace YassineDabbous\JsonableRequest;

use Illuminate\Http\Client\Response;

interface RequestBuilderContract
{
    public function parse(array $template, array $data): array;
    
    public function send(array $template, ?array $data = null): Response;
}
