<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Settings\SettingInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

class DebuggingMiddleware extends CommonMiddleware
{
  /**
   * 디버그 모드 접근 허용 IP를 검사한다.
   *
   * @param Request $request HTTP 요청
   * @param RequestHandler $handler 다음 요청 핸들러
   *
   * @return Response
   */
  public function action(Request $request, RequestHandler $handler): Response
  {
    $settings = $this->container->get(SettingInterface::class);
    $isDebug = (bool) $settings->get('debug');
    $allowIps = $settings->get('security.debugging_allow_ip') ?? [];

    if ($isDebug && $allowIps !== []) {
      $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
      if (!in_array($clientIp, $allowIps, true)) {
        $response = new SlimResponse(503);
        $response->getBody()->write('현재 점검 중입니다. 잠시 후 다시 이용해 주세요.');

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
      }
    }

    return $handler->handle($request);
  }
}