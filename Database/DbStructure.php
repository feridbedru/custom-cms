<?php

namespace Database;

require_once(__DIR__ . "../../Utility/Logger.php");

use Database\ConnectionManager;
use Utility\Logger;

class DbStructure
{
    private $con;

    public function __construct()
    {
        $manager = new ConnectionManager();
        $manager->connect();
        $this->con = $manager->con;
    }

    public function createDatabase($name)
    {
        try {
            $sql = "CREATE DATABASE IF NOT EXISTS $name";
            $this->con->query($sql);

            Logger::log("info", __METHOD__ . " $name Database created successfully!");
            return true;
        } catch (\Exception $e) {
            Logger::log("error", __METHOD__ . " Error creating database. " . $e->getMessage());
            return false;
        }
    }

    public function createTable($tablename, $columns)
    {
        $config = require(__DIR__ . "../../config.php");
        $database = $config['DB_NAME'];
        try {
            $sql = "CREATE TABLE IF NOT EXISTS $database.$tablename ($columns)";
            $this->con->query($sql);

            Logger::log("info", __METHOD__ . " Table '$tablename' created successfully");
            return true;
        } catch (\Exception $e) {
            Logger::log("error", __METHOD__ . " Error creating table. " . $e->getMessage());
            return false;
        }
    }
}
