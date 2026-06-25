<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\SecureDb;
use App\Settings\SettingInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Psr7\Response as SlimResponse;
use Whoops\Util\Misc;

class AdminSessionMiddleware extends CommonMiddleware
{
  /**
   * 관리자 세션을 검사한다.
   *
   * @param Request $request HTTP 요청
   * @param RequestHandler $handler 다음 요청 핸들러
   *
   * @return Response
   */
  public function action(Request $request, RequestHandler $handler): Response
  {
    $settings = $this->container->get(SettingInterface::class);
    $adminDir = trim((string) ($settings->get('site.admin_directory') ?? 'dmmt'), '/');
    $loginUrl = '/' . $adminDir . '/login';
    $logoutUrl = '/' . $adminDir . '/logout';
    $path = $request->getUri()->getPath();
    $session = $this->container->get('session');
    $admin = $session->get('admin');

    if ($path === $loginUrl && is_array($admin) && !empty($admin['id'])) {
      if (!$this->isActiveAdminSession($admin)) {
        $session->delete('admin');

        return $handler->handle($request);
      }

      return (new SlimResponse())->withHeader('Location', '/' . $adminDir)->withStatus(302);
    }

    if (in_array($path, [$loginUrl, $logoutUrl], true)) {
      return $handler->handle($request);
    }

    if (!is_array($admin) || empty($admin['id'])) {
      if (Misc::isAjaxRequest()) {
        throw new HttpUnauthorizedException($request, '관리자 세션이 만료되었습니다. 다시 로그인해 주세요.');
      }

      return (new SlimResponse())->withHeader('Location', $loginUrl)->withStatus(302);
    }

    if (!$this->isActiveAdminSession($admin)) {
      $session->delete('admin');

      if (Misc::isAjaxRequest()) {
        throw new HttpUnauthorizedException($request, '사용할 수 없는 관리자 계정입니다.');
      }

      return (new SlimResponse())->withHeader('Location', $loginUrl)->withStatus(302);
    }

    return $handler->handle($request);
  }

  /**
   * 관리자 세션의 계정 상태가 ACTIVE인지 확인한다.
   *
   * @param array $admin 관리자 세션 데이터
   *
   * @return bool
   */
  private function isActiveAdminSession(array $admin): bool
  {
    $adminId = (int) ($admin['id'] ?? 0);
    if ($adminId <= 0) {
      return false;
    }

    $row = $this->container->get(SecureDb::class)->fetchRow(
      'SELECT id FROM members WHERE id = ? AND role = ? AND status = ? LIMIT 1',
      [$adminId, 'ADMIN', 'ACTIVE']
    );

    return $row !== null;
  }
}