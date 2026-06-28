<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\PlatformFee;

trait PlatformFeeInput
{
  /**
   * 플랫폼 수수료 입력값을 저장 가능한 데이터로 정규화합니다.
   *
   * @param array $data 입력 데이터
   *
   * @return array
   */
  private function normalizePlatformFeeData(array $data): array
  {
    $platformName = trim((string) ($data['platform_name'] ?? ''));
    if ($platformName === '' || mb_strlen($platformName) > 50) {
      return $this->fail('플랫폼명을 50자 이내로 입력해 주세요.', 'platform_name');
    }

    $platformCode = trim((string) ($data['platform_code'] ?? ''));
    if (preg_match('/^[a-z0-9_-]{2,50}$/', $platformCode) !== 1) {
      return $this->fail('플랫폼 코드는 영문 소문자, 숫자, 하이픈, 언더바 2~50자로 입력해 주세요.', 'platform_code');
    }

    $platformFeeRate = $this->normalizeRate($data, 'platform_fee_rate', '플랫폼 수수료는 0~100 사이의 숫자로 입력해 주세요.');
    if (($platformFeeRate['success'] ?? false) !== true) {
      return $platformFeeRate;
    }

    $shippingFeeRate = $this->normalizeRate($data, 'shipping_fee_rate', '배송비 수수료는 0~100 사이의 숫자로 입력해 주세요.');
    if (($shippingFeeRate['success'] ?? false) !== true) {
      return $shippingFeeRate;
    }

    $instantDiscountRate = $this->normalizeRate($data, 'instant_discount_rate', '즉시할인은 0~100 사이의 숫자로 입력해 주세요.');
    if (($instantDiscountRate['success'] ?? false) !== true) {
      return $instantDiscountRate;
    }

    $additionalDiscountRate = $this->normalizeRate($data, 'additional_discount_rate', '부가할인은 0~100 사이의 숫자로 입력해 주세요.');
    if (($additionalDiscountRate['success'] ?? false) !== true) {
      return $additionalDiscountRate;
    }

    $additionalFixedDiscount = $this->normalizeInt($data, 'additional_fixed_discount', '부가정액할인은 0 이상의 숫자로 입력해 주세요.', 0, 10000000);
    if (($additionalFixedDiscount['success'] ?? false) !== true) {
      return $additionalFixedDiscount;
    }

    $sortOrder = $this->normalizeInt($data, 'sort_order', '정렬 순서는 0 이상의 숫자로 입력해 주세요.', 0, 999999);
    if (($sortOrder['success'] ?? false) !== true) {
      return $sortOrder;
    }

    $isDefault = (string) ($data['is_default'] ?? 'N');
    if (!in_array($isDefault, ['Y', 'N'], true)) {
      return $this->fail('기본여부 값을 올바르게 선택해 주세요.', 'is_default');
    }

    $memo = trim((string) ($data['memo'] ?? ''));
    if (mb_strlen($memo) > 255) {
      return $this->fail('메모는 255자 이내로 입력해 주세요.', 'memo');
    }

    return [
      'success' => true,
      'data' => [
        'platform_name' => $platformName,
        'platform_code' => $platformCode,
        'platform_fee_rate' => $platformFeeRate['value'],
        'shipping_fee_rate' => $shippingFeeRate['value'],
        'instant_discount_rate' => $instantDiscountRate['value'],
        'additional_discount_rate' => $additionalDiscountRate['value'],
        'additional_fixed_discount' => $additionalFixedDiscount['value'],
        'sort_order' => $sortOrder['value'],
        'is_default' => $isDefault,
        'memo' => $memo === '' ? null : $memo,
      ],
    ];
  }

  /**
   * 비율 입력값을 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param string $field 필드명
   * @param string $message 오류 메시지
   *
   * @return array
   */
  private function normalizeRate(array $data, string $field, string $message): array
  {
    $value = str_replace(',', '', trim((string) ($data[$field] ?? '')));
    if ($value === '' || is_numeric($value) !== true) {
      return $this->fail($message, $field);
    }

    $rate = (float) $value;
    if ($rate < 0 || $rate > 100) {
      return $this->fail($message, $field);
    }

    return ['success' => true, 'value' => number_format($rate, 3, '.', '')];
  }

  /**
   * 정수 입력값을 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param string $field 필드명
   * @param string $message 오류 메시지
   * @param int $min 최소값
   * @param int $max 최대값
   *
   * @return array
   */
  private function normalizeInt(array $data, string $field, string $message, int $min, int $max): array
  {
    $value = str_replace(',', '', trim((string) ($data[$field] ?? '')));
    if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
      return $this->fail($message, $field);
    }

    $number = (int) $value;
    if ($number < $min || $number > $max) {
      return $this->fail($message, $field);
    }

    return ['success' => true, 'value' => $number];
  }

  /**
   * 실패 응답 배열을 생성합니다.
   *
   * @param string $message 오류 메시지
   * @param string $field 오류 필드
   * @param int $status HTTP 상태 코드
   *
   * @return array
   */
  private function fail(string $message, string $field, int $status = 400): array
  {
    return [
      'success' => false,
      'message' => $message,
      'field' => $field,
      'status' => $status,
    ];
  }
}
