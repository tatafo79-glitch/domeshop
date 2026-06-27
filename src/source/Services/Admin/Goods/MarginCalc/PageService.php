<?php

declare(strict_types=1);

namespace App\Services\Admin\Goods\MarginCalc;

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

    return [
      'calculator' => [
        'supply_price' => $this->normalizeMoney($query['supply_price'] ?? null),
        'sell_price' => $this->normalizeMoney($query['sell_price'] ?? null),
        'shipping_fee' => $this->normalizeMoney($query['shipping_fee'] ?? null),
        'can_apply' => $this->isGoodsFormSource($query['source'] ?? null),
      ],
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
}
