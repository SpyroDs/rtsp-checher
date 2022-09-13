<?php declare(strict_types=1);


namespace RtspChecker;


use Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;


final class AuthenticationMiddleware implements MiddlewareInterface {


  /**
   * @throws Exception
   */
  public function process(
    ServerRequestInterface $request,
    RequestHandlerInterface $handler
  ): ResponseInterface {

    if ($request->hasHeader('Authorization')
      && (false != ($token = trim(
          str_replace(
            "Bearer",
            "",
            $request->getHeaderLine('Authorization')
          )
        )))
    ) {
        if ($token !== getenv('ACCESS_TOKEN')) {
            return new Response(403);
        }

    } else {
        return new Response(400);
    }

    return $handler->handle($request);
  }

}
