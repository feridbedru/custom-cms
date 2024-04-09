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

class User
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
        $validate = $this->validate($this->requestData);
        if (count($validate) > 0) {
            Logger::log("error",   __METHOD__ . " Data: " . json_encode($this->requestData) . " Validation errors: " . json_encode($validate));
            $response = (new Response)->generate(400, (new Validator)->mapErrorMessage($this->globalErrors), NULL, NULL);
            exit($response);
        }

        $username = Validator::sanitizeInput($_POST['username']);
        $password = Validator::sanitizeInput($_POST['password']);
        $role = json_encode(['role' => Validator::sanitizeInput($_POST['role'])]);
        $is_active = Validator::sanitizeInput($_POST['is_active']);
        $password = password_hash($password, PASSWORD_DEFAULT);

        $qry = $this->con->prepare("INSERT INTO `user` (username, password, role, is_active) VALUES (?, ?, ?, ?)");
        $qry->bind_param("sssi", $username, $password, $role, $is_active);
        $qry->execute();

        if ($qry->affected_rows === 1) {
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
        $sql = "SELECT `id`, `username`, JSON_UNQUOTE(JSON_EXTRACT(`role`, '$.role')) AS role, `is_active`, `created_at` FROM user WHERE id=$id";
        $result = $this->con->query($sql);
        $result = $result->fetch_assoc();
        if (!$result)
            $result = ["message" => "Invalid ID."];

        Logger::log("info", __METHOD__ . " fetched item " . json_encode($result));

        $response = (new Response)->generate(200, NULL, NULL, $result);
        exit($response);
    }

    public function update()
    {
        $putData = file_get_contents("php://input");
        $this->requestData = json_decode($putData, true);

        $id = str_replace('/', '', $_SERVER['PATH_INFO']);
        $validate = $this->validate($this->requestData);
        if (count($validate) > 0) {
            Logger::log("error",   __METHOD__ . " Data: " . json_encode($this->requestData) . " Validation errors: " . json_encode($validate));
            $response = (new Response)->generate(400, (new Validator)->mapErrorMessage($this->globalErrors), NULL, NULL);
            exit($response);
        }

        $username = Validator::sanitizeInput($this->requestData['username']);
        $password = Validator::sanitizeInput($this->requestData['password']);
        $role = json_encode(['role' => Validator::sanitizeInput($this->requestData['role'])]);
        $is_active = Validator::sanitizeInput($this->requestData['is_active']);
        $password = password_hash($password, PASSWORD_DEFAULT);

        $qry = $this->con->prepare("UPDATE `user` SET username =?, password =?, role =?, is_active=? WHERE id= ?");
        $qry->bind_param("sssii", $username, $password, $role, $is_active, $id);
        $qry->execute();

        if ($qry->affected_rows === 1) {
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
        $sql = "UPDATE `user` SET `deleted_at` = CURRENT_TIMESTAMP WHERE `id` = $id";
        $this->con->query($sql);
        if ($this->con->affected_rows > 0) {
            Logger::log("info", __METHOD__ . " deleted item id $id");
            $response = (new Response)->generate(204, NULL, NULL, '');
            exit($response);
        } else {
            Logger::log("error", __METHOD__ . " item id $id not found");
            $response = (new Response)->generate(400, (new Validator)->mapErrorMessage(['0']), NULL, '');
            exit($response);
        }

    }

    function validate($request)
    {
        $errors = [];

        if (!isset($request['username']) || !preg_match('/^[a-zA-Z0-9]{5,}$/', $request['username'])) {
            $errors[] = "Invalid username. Username must be alphanumeric and longer than 4 characters.";
            $this->globalErrors[] = 1;
        }

        if (!isset($request['password']) || strlen($request['password']) < 5) {
            $errors[] = "Invalid password. Password must be longer than 5 characters.";
            $this->globalErrors[] = 1;
        }

        if (!isset($request['role']) || !in_array($request['role'], ['admin', 'editor', 'author'])) {
            $errors[] = "Invalid role. Role must be one of the following: admin, editor, author.";
            $this->globalErrors[] = 1;
        }

        if (!isset($request['is_active']) || !in_array($request['is_active'], [0, 1])) {
            $errors[] = "Invalid is_active value. is_active should be either 0 or 1.";
            $this->globalErrors[] = 1;
        }

        return $errors;
    }

    public function index()
    {
        $routes = [
            'GET' => [
                'api/user.php' => 'read',
                'api/user.php/{id}' => 'readById',
            ],
            'POST' => [
                'api/user.php' => 'create',
            ],
            'PUT' => [
                'api/user.php/{id}' => 'update',
            ],
            'DELETE' => [
                'api/user.php/{id}' => 'delete',
            ],
        ];

        $requestMethod = $this->method;
        $requestUri = str_replace("/custom-cms/", "", $this->uri);
        $requestUri = strtok($requestUri, '?');

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
        $sql = "SELECT `id`, `username`, JSON_UNQUOTE(JSON_EXTRACT(`role`, '$.role')), `is_active`, `created_at` FROM user WHERE DELETED_AT IS NULL ORDER BY id LIMIT $itemsPerPage OFFSET $offset";
        $result = $this->con->query($sql);
        $result = $result->fetch_all(MYSQLI_ASSOC);

        return $result;
    }

    private function getTotal()
    {
        $sql = "SELECT * FROM user WHERE DELETED_AT IS NULL";
        $result = $this->con->query($sql);
        $count = mysqli_num_rows($result);

        return $count;
    }
}

$user = new User();
