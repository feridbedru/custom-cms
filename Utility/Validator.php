<?php

namespace Utility;

use Database\ConnectionManager;
use Utility\Logger;

class Validator
{
    public $errorCodes = [
        0 => 'Unknown error',
        1 => 'Invalid request structure',
        2 => 'Insufficient permissions',
        3 => 'Maximum resources exceeded',
        4 => 'Title exceeds maximum length',
        5 => 'Content exceeds maximum length',
        6 => 'Invalid file type',
        7 => 'File size exceeds limit'
    ];

    public static function json($request)
    {
        $decoded = json_decode($request);

        if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
            return false;
        }

        return true;
    }

    public static function sanitizeInput($input)
    {
        $manager = new ConnectionManager();
        $manager->connect();
        $sanitized = trim(mysqli_real_escape_string($manager->con, $input));

        Logger::log("info",  __METHOD__ . " Sanitized input: " . $input . " output: " . $sanitized);
        return $sanitized;
    }

    public function mapErrorMessage(array $errors)
    {
        sort($errors);
        $uniqueErrors = array_unique($errors);
        $errorMessages = array();

        foreach ($uniqueErrors as $errors) {
            $errorMessage = $this->getErrorMessage($errors);
            $errorMessages[] = $errorMessage;
        }

        return $errorMessages;
    }

    private function getErrorMessage(int $errorCode)
    {
        if (isset($this->errorCodes[$errorCode])) {
            return ["code" => $errorCode, "message" => $this->errorCodes[$errorCode]];
        } else {
            return ["code" => $errorCode, "message" => $this->errorCodes[0]];
        }
    }

    public static function checkPermission($entity, $userId)
    {
        $manager = new ConnectionManager();
        $manager->connect();
        $manager->selectDatabase();

        $qry = $manager->con->prepare("SELECT JSON_UNQUOTE(JSON_EXTRACT(`role`, '$.role')) FROM user WHERE id = ?");
        $qry->bind_param("i", $userId);
        $qry->execute();
        $role = '';
        $qry->bind_result($role);
        $qry->fetch();

        Logger::log("info",  __METHOD__ . " User id: " . $userId . " role: " . $role);

        if ($role === 'admin') {
            return true;
        } elseif ($role === 'editor' || $role === 'author') {
            return $entity === 'content';
        }

        return false;
    }
}
