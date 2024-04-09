<?php

require("Database/ConnectionManager.php");
require("Database/DbStructure.php");

use Database\ConnectionManager;
use Database\DbStructure;
use Utility\Logger;

error_reporting(0);
$manager = new ConnectionManager();
$con = $manager->connect();

// check if the database exists
$databaseExists = $manager->selectDatabase();
if ($databaseExists == false) {
    $db = createDb();
    $tbl = createTables();
    $user = generateUser();
    if($db == true && $tbl == true && $user == true)
        echo "Finished setting up db. Username: ferid and Password: 12345678";
    else
        echo "An error occured. Check the logs";
} else {
    echo "database already exists";
}

function createDb()
{
    $db = new DbStructure();
    $config = require(__DIR__ . "/config.php");
    $createDb = $db->createDatabase($config['DB_NAME']);
    
    return $createDb ? true : false;
}

function createTables()
{
    $db = new DbStructure();
    $userColumn = " `id` INT NOT NULL AUTO_INCREMENT , 
                    `username` VARCHAR(100) NOT NULL , 
                    `password` VARCHAR(255) NOT NULL , 
                    `role` JSON NOT NULL , 
                    `is_active` BOOLEAN NOT NULL , 
                    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , 
                    `deleted_at` TIMESTAMP NULL , 
                PRIMARY KEY (`id`),
                UNIQUE(`username`)";
    $userTable = $db->createTable("user", $userColumn);

    $contentColumn = " `id` INT NOT NULL AUTO_INCREMENT , 
                       `user_id` INT NOT NULL , 
                       `type` ENUM('article','page') NOT NULL , 
                       `title` VARCHAR(255) NOT NULL , 
                       `content` MEDIUMTEXT NOT NULL , 
                       `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , 
                       `updated_at` TIMESTAMP on update CURRENT_TIMESTAMP NULL , 
                       `deleted_at` TIMESTAMP NULL , 
                    PRIMARY KEY (`id`),
                    FOREIGN KEY (user_id) REFERENCES user (id)";
    $contentTable = $db->createTable("content", $contentColumn);

    $contentMediaColumn = " `id` INT NOT NULL AUTO_INCREMENT ,  
                            `content_id` INT NOT NULL ,  
                            `file_name` VARCHAR(255) NOT NULL ,  
                            `file_type` VARCHAR(100) NOT NULL ,  
                            `file_size` INT NOT NULL ,  
                            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ,  
                            `deleted_at` TIMESTAMP NULL ,    
                        PRIMARY KEY  (`id`),
                        FOREIGN KEY (content_id) REFERENCES content (id)";
    $contentMediaTable = $db->createTable("content_media", $contentMediaColumn);
    
    if($userTable && $contentTable && $contentMediaTable){
        return true;
    }
    else{
        return false;
    }
}

function generateUser(){
    $manager = new ConnectionManager();
    $manager->connect();
    $manager->selectDatabase();

    $config = require(__DIR__ . "/config.php");
    $password = (string)password_hash("12345678", PASSWORD_DEFAULT);
    $tbl = $config['DB_NAME'];
    try {
        $userData = "INSERT INTO $tbl . `user` (`id`, `username`, `password`, `role`, `is_active`, `created_at`, `deleted_at`) VALUES (NULL, 'ferid', '$password', '[\"Admin\"]', '1', CURRENT_TIMESTAMP, NULL)";
        $inserData = $manager->con->query($userData);

        return $inserData ? true : false;
    } catch (\Exception $e) {
        Logger::log("error", __METHOD__." Populating user data failed. " . $e->getMessage());
    }
    
}
