<?php declare(strict_types=1);


use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use RtspChecker\RtspClient;
use Slim\Factory\AppFactory;

require __DIR__.'/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, true, true);


function rtspRequestHandler(string $method, Request $request, Response $response): Response
{
    $res = [];
    $requestBody = $request->getBody()->getContents();
    $requestBody = json_decode($requestBody, true);

    if (isset($requestBody['url'])) {
        $url = $requestBody['url'];
        $client = new RtspClient();
        $client->init($url);

        $result = $client->send($method, $url);
        if ($result) {
            if ($result['code'] === '401') {
                $client->authenticateFromResponse($url, $method, $result);
                $result = $client->send($method, $url);
            }

            if ($result['code'] === '200') {
                $res = $result;
            }
        }

        $client->disconnect();
    }

    $body = $response->getBody();
    $body->write(json_encode($res));

    return $response->withBody($body)
        ->withHeader('Content-Type', 'application/json')
        ->withoutHeader('X-Powered-By');
}

$app->post('/rtsp/describe', function (
    Request $request, Response $response,
): Response {
    return rtspRequestHandler('DESCRIBE', $request, $response);
});

$app->post('/rtsp/options', function (
    Request $request, Response $response,
): Response {
    return rtspRequestHandler('OPTIONS', $request, $response);
});

$app->run();
