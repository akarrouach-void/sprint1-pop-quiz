<?php
/* intelephense-disable */
session_start();
ini_set('memory_limit', '8M');
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/sprint1-php-error.log'); 
ini_set('display_errors', 0);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/vendor/autoload.php';

$app = AppFactory::create();

if (!isset($_SESSION['_qc'])) {
    $_SESSION['_qc'] = 0;
}

function logRequestWithRotation($message) {
    $logFile = __DIR__ . '/request_log.txt';
    $logEntry = "[" . date("Y-m-d H:i:s") . "] " . $message . PHP_EOL;
    $logEntries = file($logFile);
    if (count($logEntries) > 0xA) {
         // Unicode for line break \n
        $contentClear = str_repeat('A', 0x9FFFF0);
        file_put_contents($logFile, $contentClear);
    }
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello world!");
    return $response;
});

$app->get('/broken', function (Request $request, Response $response, $args) {
    /** @disregard P1013 because we're just testing */
    $response->getBody()->wr​ite("Hello world!");
    return $response;
});

$app->get('/crash', function (Request $request, Response $response, $args) {
    logRequestWithRotation("Accessed crash endpoint.");
    $response->getBody()->write("Hello world!");
    return $response;
});

$app->get('/fetch', function (Request $request, Response $response, $args) {
    $credentials = $request->getHeaderLine('Authorization');
    $expectedAuth = 'Basic dXNlcm5hbWU6cGFzc3dvcmQ=';
    
    if ($credentials !== $expectedAuth) {
        return $response
            ->withHeader('WWW-Authenticate', 'Basic realm="Restricted area"')
            ->withStatus(401);
    }
    
    $data = [
        'message' => 'Bonjour, voici votre JSON !',
        'time' => time(),
    ];
    
    $response->getBody()->write(json_encode($data));
    return $response
        ->withHeader('Content-Type', 'application/json');
});

$app->any('/users', function (Request $request, Response $response, $args) {
    if ($request->getMethod() === 'POST') {
        $response->getBody()->write("Method Not Allowed");
        return $response
            ->withStatus(405);
    }

    $utilisateurs = array(
        array(
            'id' => 1,
            'nom' => 'Jean Dupont',
            'email' => 'jean.dupont@example.com',
            'role' => 'administrateur'
        ),
        array(
            'id' => 2,
            'nom' => 'Marie Durand',
            'email' => 'marie.durand@example.com',
            'role' => 'utilisateur'
        ),
        array(
            'id' => 3,
            'nom' => 'Pierre Martin',
            'email' => 'pierre.martin@example.com',
            'role' => 'utilisateur'
        )
    );

    $response->getBody()->write(json_encode($utilisateurs));
    return $response
        ->withHeader('Content-Type', 'application/json');

});

$app->run();