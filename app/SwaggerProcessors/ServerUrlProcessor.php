<?php

namespace App\SwaggerProcessors;

use OpenApi\Analysis;

class ServerUrlProcessor
{
    /**
     * Process the OpenAPI analysis to set server URL dynamically from APP_URL
     * This processor is called during OpenAPI spec generation
     */
    public function __invoke(Analysis $analysis)
    {
        if ($analysis->openapi && $analysis->openapi->servers) {
            // Get APP_URL from environment or config
            $appUrl = env('APP_URL', config('app.url', 'http://localhost'));
            $appUrl = rtrim($appUrl, '/');
            
            // Always update all server URLs to use APP_URL from .env
            foreach ($analysis->openapi->servers as $server) {
                $server->url = $appUrl;
            }
        }
    }
}

