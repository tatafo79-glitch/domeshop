<?php

declare(strict_types=1);

namespace App\Middlewares;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

abstract class CommonMiddleware implements MiddlewareInterface
{
  /**
   * Method __construct
   *
   * @param ContainerInterface $container DI 컨테이너
   *
   * @return void
   */
  public function __construct(protected readonly ContainerInterface $container)
  {
  }

  /**
   * 미들웨어 공통 진입점을 처리한다.
   *
   * @param Request $request HTTP 요청
   * @param RequestHandler $handler 다음 요청 핸들러
   *
   * @return Response
   */
  public function process(Request $request, RequestHandler $handler): Response
  {
    return $this->action($request, $handler);
  }

  /**
   * 개별 미들웨어 동작을 수행한다.
   *
   * @param Request $request HTTP 요청
   * @param RequestHandler $handler 다음 요청 핸들러
   *
   * @return Response
   */
  abstract public function action(Request $request, RequestHandler $handler): Response;
}