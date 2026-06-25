<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Auth;

use App\Controllers\Actions\Admin;
use App\Settings\SettingInterface;
use Psr\Http\Message\ResponseInterface as Response;

class Post extends Admin
{
  /**
   * 관리자 로그인을 처리한다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $result = $this->service->execute($this->getFormData());

    if (($result['success'] ?? false) !== true) {
      return $this->respondWithData([
        'success' => false,
        'message' => (string) ($result['message'] ?? '아이디 또는 비밀번호가 올바르지 않습니다.'),
        'field' => (string) ($result['field'] ?? 'admin_id'),
      ], (int) ($result['status'] ?? 400));
    }

    $adminDir = trim((string) ($this->container->get(SettingInterface::class)->get('site.admin_directory') ?? 'dmmt'), '/');
    $response = $this->respondWithData([
      'success' => true,
      'message' => '로그인되었습니다.',
      'redirect' => '/' . $adminDir,
    ]);

    return $this->withRememberCookie($response, (string) ($result['saved_id'] ?? ''), (bool) ($result['remember_id'] ?? false));
  }

  /**
   * 아이디 저장 쿠키를 적용한다.
   *
   * @param Response $response 응답 객체
   * @param string $savedId 저장할 관리자 아이디
   * @param bool $rememberId 아이디 저장 여부
   *
   * @return Response
   */
  private function withRememberCookie(Response $response, string $savedId, bool $rememberId): Response
  {
    $cookie = $rememberId
      ? sprintf('admin_saved_id=%s; Max-Age=%d; Path=/; SameSite=Lax; HttpOnly', rawurlencode($savedId), 60 * 60 * 24 * 30)
      : 'admin_saved_id=; Max-Age=0; Path=/; SameSite=Lax; HttpOnly';

    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
      $cookie .= '; Secure';
    }

    return $response->withAddedHeader('Set-Cookie', $cookie);
  }
}