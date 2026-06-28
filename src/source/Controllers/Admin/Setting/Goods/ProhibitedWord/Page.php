<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Setting\Goods\ProhibitedWord;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Page extends Admin
{
  /**
   * 금지단어 관리 화면을 렌더링합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $result = $this->service->execute([
      'query' => $this->getQueryData(),
    ]);

    return $this->render('setting/goods_prohibited_word', $result);
  }
}
