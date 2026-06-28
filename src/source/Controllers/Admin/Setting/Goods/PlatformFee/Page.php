<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Setting\Goods\PlatformFee;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Page extends Admin
{
  /**
   * 플랫폼 수수료 설정 화면을 렌더링합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $result = $this->service->execute();

    return $this->render('setting/goods_platform_fee', $result);
  }
}
