<?php

declare(strict_types=1);

namespace App\Services\Admin\Goods\MarginCalc;

use App\Repositories\Admin\PlatformFeeRepository;
use App\Services\BaseService;

class PageService extends BaseService
{
  /**
   * 마진 계산기 초기 입력값을 정리합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $query = is_array($params['query'] ?? null) ? $params['query'] : [];

    $repository = $this->container->get(PlatformFeeRepository::class);
    $platformFees = array_map([$this, 'formatPlatformFee'], $repository->getPlatformFees());

    return [
      'calculator' => [
        'supply_price' => $this->normalizeMoney($query['supply_price'] ?? null),
        'sell_price' => $this->normalizeMoney($query['sell_price'] ?? null),
        'shipping_fee' => $this->normalizeMoney($query['shipping_fee'] ?? null),
        'actual_shipping_fee' => $this->normalizeMoney($query['actual_shipping_fee'] ?? ($query['shipping_fee'] ?? null)),
        'can_apply' => $this->isGoodsFormSource($query['source'] ?? null),
      ],
      'platform_fees' => $platformFees,
      'default_platform_code' => $this->getDefaultPlatformCode($platformFees),
    ];
  }

  /**
   * 상품등록 화면에서 연 계산기인지 확인합니다.
   *
   * @param mixed $value 출처 입력값
   *
   * @return bool
   */
  private function isGoodsFormSource(mixed $value): bool
  {
    return is_scalar($value) && (string) $value === 'goods-form';
  }

  /**
   * 금액 쿼리 값을 0 이상의 정수로 보정합니다.
   *
   * @param mixed $value 금액 입력값
   *
   * @return int
   */
  private function normalizeMoney(mixed $value): int
  {
    if (!is_scalar($value)) {
      return 0;
    }

    $numeric = preg_replace('/[^0-9]/', '', (string) $value) ?? '';

    if ($numeric === '') {
      return 0;
    }

    return max(0, (int) $numeric);
  }

  /**
   * 마진 계산기 출력용 플랫폼 수수료 값을 보정합니다.
   *
   * @param array $fee 원본 플랫폼 수수료 데이터
   *
   * @return array
   */
  private function formatPlatformFee(array $fee): array
  {
    foreach (['platform_fee_rate', 'shipping_fee_rate', 'instant_discount_rate', 'additional_discount_rate'] as $field) {
      $fee[$field] = rtrim(rtrim(number_format((float) ($fee[$field] ?? 0), 3, '.', ''), '0'), '.');
    }

    $fee['additional_fixed_discount'] = (int) ($fee['additional_fixed_discount'] ?? 0);
    $fee['platform_name'] = (string) ($fee['platform_name'] ?? '');
    $fee['platform_code'] = (string) ($fee['platform_code'] ?? '');
    $fee['is_default'] = (string) ($fee['is_default'] ?? 'N');

    return $fee;
  }

  /**
   * 기본 플랫폼 코드를 조회합니다.
   *
   * @param array $platformFees 플랫폼 수수료 목록
   *
   * @return string
   */
  private function getDefaultPlatformCode(array $platformFees): string
  {
    foreach ($platformFees as $fee) {
      if (($fee['is_default'] ?? 'N') === 'Y') {
        return (string) ($fee['platform_code'] ?? '');
      }
    }

    return '';
  }
}
