<?php

namespace Utility;

class Response
{
    public function generate(int $httpCode, array $globalError = NULL, array $resourceError = NULL, $content)
    {
        http_response_code($httpCode);
        header('Content-Type: application/json');

        if (isset($globalError) || isset($resourceError)) {
            $response = ["errors" => $globalError, "resources" => $resourceError];
            return json_encode($response);
        }

        return json_encode($content);
    }
}
