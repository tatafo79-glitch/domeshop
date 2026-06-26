<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Member\Asset;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Page extends Admin
{
  /**
   * 회원 자산 관리 frame 화면을 렌더링합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $result = $this->service->execute([
      'id' => (int) $this->resolveArg('id'),
      'asset' => (string) $this->resolveArg('asset'),
      'query' => $this->getQueryData(),
    ]);

    return $this->render('member/asset', $result);
  }
}
