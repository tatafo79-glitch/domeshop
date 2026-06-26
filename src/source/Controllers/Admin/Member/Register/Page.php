<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Member\Register;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Page extends Admin
{
  /**
   * 회원 등록 화면을 렌더링합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $result = $this->service->execute();

    return $this->render('member/register', $result);
  }
}
