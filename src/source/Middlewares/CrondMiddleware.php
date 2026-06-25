<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Settings\SettingInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpForbiddenException;

class CrondMiddleware extends CommonMiddleware
{
  /**
   * 크론 요청 허용 IP를 검사한다.
   *
   * @param Request $request HTTP 요청
   * @param RequestHandler $handler 다음 요청 핸들러
   *
   * @return Response
   */
  public function action(Request $request, RequestHandler $handler): Response
  {
    $settings = $this->container->get(SettingInterface::class);
    $allowIps = $settings->get('crond.allow_ip') ?? [];
    $allowIps = array_values(array_unique(array_merge($allowIps, ['127.0.0.1', '::1'])));
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';

    if (!in_array($clientIp, $allowIps, true)) {
      throw new HttpForbiddenException($request, '접근이 거부되었습니다.');
    }

    return $handler->handle($request);
  }
}