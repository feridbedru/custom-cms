<?php

namespace api;

require("../Database/ConnectionManager.php");
require("../Utility/Response.php");
require("../Utility/Paginator.php");
require("../Utility/Validator.php");

use Database\ConnectionManager;
use Utility\Logger;
use Utility\Paginator;
use Utility\Response;
use Utility\Validator;

class Content
{
    private $method;
    private $uri;
    private $con;
    private $requestData;
    private $resourceErrors = [];
    private $globalErrors = [];

    public function __construct()
    {
        $this->method  = $_SERVER['REQUEST_METHOD'];
        $this->uri  = $_SERVER['REQUEST_URI'];
        $this->requestData = $_REQUEST;
        $manager = new ConnectionManager();
        $manager->connect();
        $manager->selectDatabase();
        $this->con = $manager->con;
        $this->index();
    }

    public function create()
    {
        $requestBody = file_get_contents('php://input');
        $requestData = json_decode($requestBody, true);
        $this->requestData = $requestData;

        $validate = $this->validate($requestData);
        if (count($validate) > 0) {
            Logger::log("error",   __METHOD__ . " Data: " . json_encode($this->requestData) . " Validation errors: " . json_encode($validate));
            $response = (new Response)->generate(400, (new Validator)->mapErrorMessage($this->globalErrors), ["type" => $this->requestData['resources']['type'], "errors" => (new Validator)->mapErrorMessage($this->resourceErrors)], NULL);
            exit($response);
        }

        $content =  $this->requestData['resources'][0];
        $type = Validator::sanitizeInput($content['type']);
        $title = Validator::sanitizeInput($content['title']);
        $body = Validator::sanitizeInput($content['content']);

        $contentSql = "INSERT INTO content (user_id, type, title, content) VALUES (?, ?, ?, ?)";
        $contentStmt = $this->con->prepare($contentSql);
        $contentStmt->bind_param("isss", $this->requestData['user_id'], $type, $title, $body);

        $contentStmt->execute();
        $contentID = $this->con->insert_id;

        $mediaSql = "INSERT INTO content_media (content_id, file_name, file_type, file_size) VALUES (?, ?, ?, ?)";
        $mediaStmt = $this->con->prepare($mediaSql);

        foreach ($content['media'] as $media) {
            $mediaName = $media['name'];
            $mediaType = $media['type'];
            $mediaSize = $media['size'];

            $mediaStmt->bind_param("issi", $contentID, $mediaName, $mediaType, $mediaSize);
            $mediaStmt->execute();
        }

        if ($contentStmt->affected_rows === 1) {
            $response = (new Response)->generate(201, NULL, NULL, '');
            exit($response);
        } else {
            $response = (new Response)->generate(400, NULL, NULL, '');
            exit($response);
        }
    }

    public function read()
    {
        $currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
        $config = require(__DIR__ . "../../config.php");
        $itemsPerPage = $config['MAX_RESOURCE'];
        $offset = ($currentPage - 1) * $itemsPerPage;
        $result = $this->getData($offset, $itemsPerPage);
        $totalItems = $this->getTotal();
        Logger::log("info", __METHOD__ . " fetched items " . json_encode($result));

        $paginated = Paginator::paginate($totalItems, $currentPage, $result);
        $response = (new Response)->generate(200, NULL, NULL, $paginated);
        exit($response);
    }

    public function readById()
    {
        $id = str_replace('/', '', $_SERVER['PATH_INFO']);
        $sql = "SELECT `id`, `type`, `title`, `content`, `created_at` FROM `content` WHERE id=$id";
        $result = $this->con->query($sql);
        $result = $result->fetch_assoc();
        if (!$result)
            $result = ["message" => "Invalid ID."];
        $result['media'] = $this->getMedia($id);

        Logger::log("info", __METHOD__ . " fetched item " . json_encode($result));

        $response = (new Response)->generate(200, NULL, NULL, $result);
        exit($response);
    }

    public function update()
    {
        $requestBody = file_get_contents('php://input');
        $requestData = json_decode($requestBody, true);
        $this->requestData = $requestData;
        $id = str_replace('/', '', $_SERVER['PATH_INFO']);

        $validate = $this->validate($requestData);
        if (count($validate) > 0) {
            Logger::log("error",   __METHOD__ . " Data: " . json_encode($this->requestData) . " Validation errors: " . json_encode($validate));
            $resourceErrors = ["type" => $this->requestData['resources'][0]['type'], "errors" => (new Validator)->mapErrorMessage($this->resourceErrors)];
            $response = (new Response)->generate(400, (new Validator)->mapErrorMessage($this->globalErrors), $resourceErrors, NULL);
            exit($response);
        }

        $content =  $this->requestData['resources'][0];
        $type = Validator::sanitizeInput($content['type']);
        $title = Validator::sanitizeInput($content['title']);
        $body = Validator::sanitizeInput($content['content']);

        $contentStmt = $this->con->prepare("UPDATE content SET user_id =?, type =?, title=? , content=?  WHERE id= ?");
        $contentStmt->bind_param("isssi", $this->requestData['user_id'], $type, $title, $body, $id);
        $contentStmt->execute();

        // delete existing media
        $removeMedia = "DELETE FROM `content_media` WHERE `content_id` = $id";
        $this->con->query($removeMedia);

        $mediaSql = "INSERT INTO content_media (content_id, file_name, file_type, file_size) VALUES (?, ?, ?, ?)";
        $mediaStmt = $this->con->prepare($mediaSql);

        foreach ($content['media'] as $media) {
            $mediaName = $media['name'];
            $mediaType = $media['type'];
            $mediaSize = $media['size'];

            $mediaStmt->bind_param("issi", $id, $mediaName, $mediaType, $mediaSize);
            $mediaStmt->execute();
        }

        if ($contentStmt->affected_rows > 0 || $mediaStmt->affected_rows > 0) {
            $response = (new Response)->generate(204, NULL, NULL, '');
            exit($response);
        } else {
            $response = (new Response)->generate(400, NULL, NULL, '');
            exit($response);
        }
    }

    public function delete()
    {
        $id = str_replace('/', '', $_SERVER['PATH_INFO']);
        $sql = "UPDATE `content` SET `deleted_at` = CURRENT_TIMESTAMP WHERE `id` = $id";
        $sql2 = "UPDATE `content_media` SET `deleted_at` = CURRENT_TIMESTAMP WHERE `content_id` = $id";
        $query = $sql . ';' . $sql2;
        $this->con->multi_query($query);
        if ($this->con->affected_rows > 0) {
            Logger::log("info", __METHOD__ . " deleted item id $id");
            $response = (new Response)->generate(204, NULL, NULL, '');
            exit($response);
        } else {
            Logger::log("error", __METHOD__ . " multi query failed for id: $id");
            $response = (new Response)->generate(400, (new Validator)->mapErrorMessage(['0']), NULL, '');
            exit($response);
        }
    }

    private function validate($request)
    {
        $errors = [];
        $config = require(__DIR__ . "../../config.php");

        if (!isset($request['user_id']) || !is_int($request['user_id'])) {
            $errors[] = "Invalid user_id. user_id is required and must be an integer.";
            $this->globalErrors[] = 1;
        }

        if (!isset($request['action']) || !in_array($request['action'], ['retrieve', 'create', 'update', 'delete'])) {
            $errors[] = "Invalid action. action must be one of the following: retrieve, create, update, delete.";
            $this->globalErrors[] = 1;
        }

        if (isset($request['resources'])) {
            if (!isset($request['resources'][0]['type']) || !in_array($request['resources'][0]['type'], ['article', 'page', 'media'])) {
                $errors[] = "Invalid resource type. type must be one of the following: article, page, media.";
                $this->globalErrors[] = 1;
            }

            if (!isset($request['resources'][0]['title']) || !is_string($request['resources'][0]['title'])) {
                $errors[] = "Invalid resource title. title is required and must be a string";
                $this->globalErrors[] = 1;
            }

            if (strlen($request['resources'][0]['title']) > $config['TITLE_LENGTH']) {
                $errors[] = "Title must be a string with a maximum length of " . $config['TITLE_LENGTH'] . " characters.";
                $this->resourceErrors[] = 4;
                Logger::log("error",   __METHOD__ . " Title length is " . strlen($request['resources'][0]['title']));
            }

            if (!isset($request['resources'][0]['content']) || !is_string($request['resources'][0]['content'])) {
                $errors[] = "Invalid resource content. content is required and must be a string";
                $this->globalErrors[] = 1;
            }

            if (strlen($request['resources'][0]['content']) > $config['CONTENT_LENGTH']) {
                $errors[] = "Content must be a string with a maximum length of " . $config['CONTENT_LENGTH'] . " characters.";
                $this->resourceErrors[] = 5;
                Logger::log("error",   __METHOD__ . " Content length is " . strlen($request['resources'][0]['content']));
            }

            if (isset($request['resources'][0]['media'])) {
                foreach ($request['resources'][0]['media'] as $media) {
                    if (!isset($media['name']) || !is_string($media['name'])) {
                        $errors[] = "Invalid media name. name is required and must be a string.";
                        $this->globalErrors[] = 1;
                    }

                    if (!isset($media['type']) || !is_string($media['type'])) {
                        $errors[] = "Media type is required and must be a string.";
                        $this->globalErrors[] = 1;
                    }

                    if (isset($media['type']) && !in_array($media['type'], $config['FILE_TYPE'])) {
                        $errors[] = "Invalid media type.";
                        $this->resourceErrors[] = 6;
                        Logger::log("error",   __METHOD__ . " Invalid Media Type for " . $media['type']);
                    }

                    if (!isset($media['size']) || !is_int($media['size'])) {
                        $errors[] = "Media size is required and must be an integer.";
                        $this->globalErrors[] = 1;
                    }

                    if ($media['size'] > $config['FILE_SIZE']) {
                        $errors[] = "Invalid media size.";
                        $this->resourceErrors[] = 7;
                        Logger::log("error",   __METHOD__ . " Invalid Media size for " . $media['size']);
                    }
                }
            }
        }

        return $errors;
    }

    public function index()
    {
        $routes = [
            'GET' => [
                'api/content.php' => 'read',
                'api/content.php/{id}' => 'readById',
            ],
            'POST' => [
                'api/content.php' => 'create',
            ],
            'PUT' => [
                'api/content.php/{id}' => 'update',
            ],
            'DELETE' => [
                'api/content.php/{id}' => 'delete',
            ],
        ];

        $requestMethod = $this->method;
        $requestUri = str_replace("/custom-cms/", "", $this->uri);
        $requestUri = strtok($requestUri, '?');

        $userId = '';
        if ($requestMethod == 'POST' || $requestMethod == 'PUT') {
            $requestBody = file_get_contents('php://input');
            $requestData = json_decode($requestBody, true);
            $userId = $requestData['user_id'];
        } else {
            $userId = $_REQUEST['user_id'];
        }
        $this->checkUser($userId);

        if (isset($routes[$requestMethod])) {
            foreach ($routes[$requestMethod] as $route => $handler) {
                $regexRoute = preg_replace('/\//', '\\/', $route);
                $regexRoute = preg_replace('/\{[\w]+\}/', '[\w]+', $regexRoute);

                if (preg_match('/^' . $regexRoute . '$/', $requestUri)) {
                    call_user_func([$this, $handler]);
                    exit();
                }
            }
        }

        Logger::log("error",   __METHOD__ . " Unknown Method. Method: " . $this->method);
        $response = (new Response)->generate(400, (new Validator)->mapErrorMessage(['0']), NULL, NULL);
        exit($response);
    }

    private function getData($offset, $itemsPerPage)
    {
        $sql = "SELECT `id`, `type`, `title`, `content`, `created_at` FROM `content` WHERE DELETED_AT IS NULL ORDER BY id LIMIT $itemsPerPage OFFSET $offset";
        $result = $this->con->query($sql);
        $result = $result->fetch_all(MYSQLI_ASSOC);
        $contentsWithMedia = [];

        foreach ($result as $row) {
            $media = $this->getMedia($row['id']);
            $row['media'] = $media;
            $contentsWithMedia[] = $row;
        }
        return $contentsWithMedia;
    }

    private function getTotal()
    {
        $sql = "SELECT * FROM content WHERE DELETED_AT IS NULL";
        $result = $this->con->query($sql);
        $count = mysqli_num_rows($result);

        return $count;
    }

    private function getMedia($content)
    {
        $sql = "SELECT `id`, `file_name`, `file_type`, `file_size`, `created_at` FROM `content_media` WHERE content_id = $content AND DELETED_AT IS NULL";
        $result = $this->con->query($sql);
        $result = $result->fetch_all(MYSQLI_ASSOC);

        return $result;
    }

    private function checkUser($userId)
    {
        $hasPermission = Validator::checkPermission('content', $userId);
        if ($hasPermission == false) {
            Logger::log("error",   __METHOD__ . " Insufficient permission for user_id:  " . $userId);
            $response = (new Response)->generate(400, (new Validator)->mapErrorMessage(['2']), NULL, NULL);
            exit($response);
        }
    }
}

$content = new Content();
