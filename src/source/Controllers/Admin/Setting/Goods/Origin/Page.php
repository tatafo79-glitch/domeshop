<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Setting\Goods\Origin;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Page extends Admin
{
  /**
   * 상품 원산지 관리 화면을 렌더링합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    return $this->render('setting/goods_origin', $this->service->execute());
  }
}
