<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Settings\SettingInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpBadRequestException;

class HttpOriginMiddleware extends CommonMiddleware
{
  /**
   * Origin/Referer 기반 요청 출처를 검증한다.
   *
   * @param Request $request HTTP 요청
   * @param RequestHandler $handler 다음 요청 핸들러
   *
   * @return Response
   */
  public function action(Request $request, RequestHandler $handler): Response
  {
    if (strtoupper($request->getMethod()) === 'GET') {
      return $handler->handle($request);
    }

    $settings = $this->container->get(SettingInterface::class);
    $allowedOrigins = $settings->get('security.origin_allow_hosts') ?? [];

    if ($allowedOrigins === [] || $this->isLocalHost()) {
      return $handler->handle($request);
    }

    $origin = $request->getHeaderLine('Origin');
    if ($origin === '') {
      $origin = $this->originFromReferer($request->getHeaderLine('Referer'));
    }

    if ($origin === '' || !in_array($origin, $allowedOrigins, true)) {
      throw new HttpBadRequestException($request, '잘못된 요청입니다.');
    }

    return $handler->handle($request);
  }

  /**
   * 로컬 개발 호스트 여부를 확인한다.
   *
   * @return bool
   */
  private function isLocalHost(): bool
  {
    $host = $_SERVER['HTTP_HOST'] ?? '';

    return str_contains($host, 'localhost')
      || str_contains($host, '127.0.0.1')
      || str_starts_with($host, '192.168.');
  }

  /**
   * Referer 헤더에서 Origin 값을 추출한다.
   *
   * @param string $referer Referer 헤더 값
   *
   * @return string
   */
  private function originFromReferer(string $referer): string
  {
    if ($referer === '') {
      return '';
    }

    $parts = parse_url($referer);
    if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
      return '';
    }

    $origin = $parts['scheme'] . '://' . $parts['host'];
    if (isset($parts['port'])) {
      $origin .= ':' . $parts['port'];
    }

    return $origin;
  }
}