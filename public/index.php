<?php declare(strict_types=1);


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RtspChecker\AuthenticationMiddleware;
use RtspChecker\Application;
use Slim\Factory\AppFactory;

require __DIR__.'/../vendor/autoload.php';


$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(false, false, false);
$app->addMiddleware(new AuthenticationMiddleware());


$app->post('/rtsp/describe', function (
    Request $request, Response $response,
): Response {
    return (new Application)->handleRequest('DESCRIBE', $request, $response);
});

$app->post('/rtsp/options', function (
    Request $request, Response $response,
): Response {
    return (new Application)->handleRequest('OPTIONS', $request, $response);
});

$app->get('/', function (
    Request $request, Response $response,
): Response {
    $body = $response->getBody();
    $body->write('Index page');

    return $response->withBody($body);
});

$app->run();
