<?php

spl_autoload_register(function ($class) {
    $class = str_replace('\\', '/', $class);
    $file = __DIR__ . '/../src/' . $class . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

$dbConfig = require __DIR__ . '/../config/database.php';
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $dbConfig['host'], $dbConfig['database']);
$db = new PDO($dsn, $dbConfig['user'], $dbConfig['password']);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$jwtConfig = require __DIR__ . '/../config/jwt.php';

$router = new Core\Router();

$userController = new Controllers\UserController($db, $jwtConfig);
$trackController = new Controllers\TrackController($db, $jwtConfig);

$router->post('/api/register', [$userController, 'register']);
$router->post('/api/login', [$userController, 'login']);
$router->post('/api/upload/init', [$trackController, 'uploadInit']);
$router->get('/api/tracks', function() {
    echo json_encode(['message' => 'provide id']);
});
$router->get('/api/tracks/', function() {
    echo json_encode(['message' => 'provide id']);
});
$router->get('/api/tracks/:id', function() {}); // placeholder not used

// simple dispatcher with path parameters
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

if (preg_match('#^/api/tracks/(\d+)$#', $uri, $m)) {
    $trackController->getTrack((int)$m[1]);
    return;
}

$router->dispatch($method, $uri);
