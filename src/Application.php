<?php

namespace RtspChecker;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamInterface;

class Application
{
    public function handleRequest(string $method, Request $request, Response $response): Response
    {
        $res = [];
        $requestBody = $request->getBody()->getContents();
        $requestBody = json_decode($requestBody, true);

        if (isset($requestBody['url'])) {
            $url = $requestBody['url'];
            $client = new RtspClient();
            if (!$client->init($url)) {
                return $this->getErrorResponse($response, 1503, 'Could not init connection');
            }

            $result = $client->send($method, $url);
            if ($result) {
                if ($result['headers']['code'] === '401') {
                    $client->authenticateFromResponse($url, $method, $result['headers']);
                    $result = $client->send($method, $url);
                }

                if ($result['headers']['code'] === '200') {
                    $res = $result;
                } else {
                    $client->disconnect();
                    return $this->getErrorResponse(
                        $response,
                        1401,
                        sprintf(
                            'Could not authorize. Response code is %s. Response: %s',
                            $result['headers']['code'],
                            serialize($result)
                        ),
                    );
                }
            }

            $client->disconnect();
        }

        return $this->getSuccessResponse($response, $res);
    }

    public function getErrorResponse(Response $response, int $code, string $msg): Response
    {
        $body = $response->getBody();
        $body->write(json_encode([
            'error' => [
                'code' => $code,
                'msg' => $msg
            ]
        ]));

        return $this->getResponse($response, $body);
    }

    public function getResponse(Response $response, StreamInterface $body): Response
    {
        return $response->withBody($body)
            ->withHeader('Content-Type', 'application/json')
            ->withoutHeader('X-Powered-By');
    }

    public function getSuccessResponse(Response $response, array $res): Response
    {
        $body = $response->getBody();
        $body->write(json_encode($res));

        return $this->getResponse($response, $body);
    }
}