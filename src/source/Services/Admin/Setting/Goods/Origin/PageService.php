<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\Origin;

use App\Repositories\Admin\GoodsOriginRepository;
use App\Services\BaseService;

class PageService extends BaseService
{
  /**
   * 상품 원산지 관리 화면 데이터를 반환합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $repository = $this->container->get(GoodsOriginRepository::class);

    return [
      'origins' => array_map([$this, 'formatOrigin'], $repository->getGoodsOrigins()),
    ];
  }

  /**
   * 화면 출력용 원산지 값을 보정합니다.
   *
   * @param array $origin 원본 데이터
   *
   * @return array
   */
  private function formatOrigin(array $origin): array
  {
    $origin['level'] = (int) ($origin['level'] ?? 0);
    $origin['sort'] = (int) ($origin['sort'] ?? 0);
    $origin['cd0'] = $origin['cd0'] === null ? null : (int) $origin['cd0'];
    $origin['cd1'] = $origin['cd1'] === null ? null : (int) $origin['cd1'];
    $origin['pathnm0'] = (string) ($origin['pathnm0'] ?? '');
    $origin['pathnm1'] = (string) ($origin['pathnm1'] ?? '');

    return $origin;
  }
}