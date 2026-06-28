<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\PlatformFee;

use App\Repositories\Admin\PlatformFeeRepository;
use App\Services\BaseService;

class PageService extends BaseService
{
  /**
   * 플랫폼 수수료 설정 화면 데이터를 반환합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $repository = $this->container->get(PlatformFeeRepository::class);

    return [
      'platform_fees' => array_map([$this, 'formatPlatformFee'], $repository->getPlatformFees()),
    ];
  }

  /**
   * 화면 출력용 플랫폼 수수료 값을 보정합니다.
   *
   * @param array $fee 원본 데이터
   *
   * @return array
   */
  private function formatPlatformFee(array $fee): array
  {
    foreach (['platform_fee_rate', 'shipping_fee_rate', 'instant_discount_rate', 'additional_discount_rate'] as $field) {
      $fee[$field] = rtrim(rtrim(number_format((float) ($fee[$field] ?? 0), 3, '.', ''), '0'), '.');
    }

    $fee['additional_fixed_discount'] = (int) ($fee['additional_fixed_discount'] ?? 0);
    $fee['memo'] = (string) ($fee['memo'] ?? '');

    return $fee;
  }
}
