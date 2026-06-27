<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Goods\MarginCalc;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Page extends Admin
{
  /**
   * 상품 마진 계산기 frame 화면을 렌더링합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $result = $this->service->execute([
      'query' => $this->getQueryData(),
    ]);

    return $this->render('goods/margin_calc', $result);
  }
}
