<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Auth;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Page extends Admin
{
  /**
   * 로그인 화면을 렌더링한다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $cookies = $this->request->getCookieParams();

    return $this->render('auth/login', [
      'error_message' => '',
      'saved_id' => (string) ($cookies['admin_saved_id'] ?? ''),
    ]);
  }
}