<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\Register;

use App\Services\BaseService;

class PageService extends BaseService
{
  /**
   * 상품 등록설정 화면의 DB 설정값을 반환합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    return [
      'register_setting' => $this->container->get(GoodsRegisterSetting::class)->getSettings(),
    ];
  }
}
