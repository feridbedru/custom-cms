<?php

namespace Utility;

class Logger
{
    public static function log(string $type, $message)
    {

        $logFile = '';
        if ($type == "error")
            $logFile = __DIR__ . "/../logs/error.log";
        else
            $logFile = __DIR__ . "/../logs/info.log";

        $dateTime = date('Y-m-d H:i:s');
        $logMessage = "$dateTime - $message" . PHP_EOL;

        try {
            file_put_contents($logFile, $logMessage, FILE_APPEND);
        } catch (\Exception $e) {
            echo 'Message: ' . $e->getMessage();
        }
    }
}
