<?php

namespace Database;

require_once(__DIR__ . "../../Utility/Logger.php");

use Utility\Logger;

class ConnectionManager
{
    private $host;
    private $username;
    private $password;
    private $database;
    public  $con;

    public function __construct()
    {
        $config = require(__DIR__ . "../../config.php");
        $this->host     = $config['DB_HOST'];
        $this->username = $config['DB_USER'];
        $this->password = $config['DB_PASSWORD'];
        $this->database = $config['DB_NAME'];
    }

    public function connect()
    {
        try {
            $this->con = new \mysqli($this->host, $this->username, $this->password);

            Logger::log("info", __METHOD__ . " Connected to the database successfully!");
            return true;
        } catch (\Exception $e) {
            Logger::log("error", __METHOD__ . " Unable to connect to the database. " . $e->getMessage());
            return false;
        }
    }

    public function selectDatabase()
    {
        try {
            $this->con->select_db($this->database);
            Logger::log("info", __METHOD__ . " Selected the database successfully!");

            return true;
        } catch (\Exception $e) {
            Logger::log("error", __METHOD__ . " Database '{$this->database}' does not exist. " . $e->getMessage());
            return false;
        }
    }
}
