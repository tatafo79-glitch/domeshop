<?php

declare(strict_types=1);

namespace App\Services\Admin\Index;

use App\Services\BaseService;

class PageService extends BaseService
{
  /**
   * Method execute
   *
   * @param array $params [explicit description]
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    return [
      'title' => 'Domemall Admin',
      'menus' => [
        ['label' => '회원', 'path' => '/member/lists'],
        ['label' => '상품', 'path' => '/goods/lists'],
        ['label' => '주문', 'path' => '/order/lists'],
      ],
    ];
  }
}
