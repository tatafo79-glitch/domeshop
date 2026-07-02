<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\Register;

use App\Repositories\Admin\SettingRepository;

class GoodsRegisterSetting
{
  public const GROUP = 'goods_register';

  private const DEFAULTS = [
    'pricing_method' => 'SUPPLY_PRICE',
    'margin_rate' => 20.0,
    'rounding_unit' => 10,
    'rounding_type' => 'ROUND',
    'block_under_supply_price' => 'Y',
    'default_shipping_type' => 'PAID',
    'shipping_fee' => 2500,
    'actual_shipping_fee' => 2500,
    'shipping_qty_limit' => 1,
    'max_image_count' => 10,
    'max_option_count' => 100,
    'max_text_option_count' => 20,
    'image_min_width' => 600,
    'image_min_height' => 600,
    'image_max_upload_mb' => 20,
    'thumbnail_enabled' => 'Y',
    'thumb_list_fit' => 'CONTAIN',
    'thumb_list_size' => 600,
    'thumb_detail_fit' => 'CONTAIN',
    'thumb_detail_size' => 1000,
    'thumb_detail_list_fit' => 'CONTAIN',
    'thumb_detail_list_size' => 300,
    'thumb_etc_list_fit' => 'CONTAIN',
    'thumb_etc_list_size' => 120,
    'platform_image_enabled' => 'Y',
    'platform_image_width' => 1000,
    'platform_image_height' => 1000,
    'platform_image_small_fit' => 'CONTAIN',
    'extra_shipping_jeju' => 0,
    'extra_shipping_island' => 0,
    'return_shipping_fee' => 2500,
    'exchange_shipping_fee' => 5000,
  ];

  private const META = [
    'pricing_method' => ['value_type' => 'string', 'description' => '기본 판매가 산정방식'],
    'margin_rate' => ['value_type' => 'float', 'description' => '기본 마진율'],
    'rounding_unit' => ['value_type' => 'int', 'description' => '반올림 단위'],
    'rounding_type' => ['value_type' => 'string', 'description' => '반올림 처리 방식'],
    'block_under_supply_price' => ['value_type' => 'string', 'description' => '가격 보호'],
    'default_shipping_type' => ['value_type' => 'string', 'description' => '기본 배송 정책'],
    'shipping_fee' => ['value_type' => 'int', 'description' => '노출 배송비'],
    'actual_shipping_fee' => ['value_type' => 'int', 'description' => '실제 배송비'],
    'shipping_qty_limit' => ['value_type' => 'int', 'description' => '합포장 기준 수량'],
    'max_image_count' => ['value_type' => 'int', 'description' => '이미지 등록 수'],
    'max_option_count' => ['value_type' => 'int', 'description' => '옵션 등록 수'],
    'max_text_option_count' => ['value_type' => 'int', 'description' => '텍스트 옵션 등록 수'],
    'image_min_width' => ['value_type' => 'int', 'description' => '원본 최소 가로 크기'],
    'image_min_height' => ['value_type' => 'int', 'description' => '원본 최소 세로 크기'],
    'image_max_upload_mb' => ['value_type' => 'int', 'description' => '원본 업로드 허용 용량'],
    'thumbnail_enabled' => ['value_type' => 'string', 'description' => '썸네일 자동 생성 사용 여부'],
    'thumb_list_fit' => ['value_type' => 'string', 'description' => '목록이미지 노출방법'],
    'thumb_list_size' => ['value_type' => 'int', 'description' => '목록이미지 기준 크기'],
    'thumb_detail_fit' => ['value_type' => 'string', 'description' => '상세이미지 노출방법'],
    'thumb_detail_size' => ['value_type' => 'int', 'description' => '상세이미지 기준 크기'],
    'thumb_detail_list_fit' => ['value_type' => 'string', 'description' => '상세목록이미지 노출방법'],
    'thumb_detail_list_size' => ['value_type' => 'int', 'description' => '상세목록이미지 기준 크기'],
    'thumb_etc_list_fit' => ['value_type' => 'string', 'description' => '기타목록이미지 노출방법'],
    'thumb_etc_list_size' => ['value_type' => 'int', 'description' => '기타목록이미지 기준 크기'],
    'platform_image_enabled' => ['value_type' => 'string', 'description' => '플랫폼용 이미지 생성 여부'],
    'platform_image_width' => ['value_type' => 'int', 'description' => '플랫폼용 이미지 가로 크기'],
    'platform_image_height' => ['value_type' => 'int', 'description' => '플랫폼용 이미지 세로 크기'],
    'platform_image_small_fit' => ['value_type' => 'string', 'description' => '플랫폼용 이미지 생성 방식'],
    'extra_shipping_jeju' => ['value_type' => 'int', 'description' => '제주 추가 배송비'],
    'extra_shipping_island' => ['value_type' => 'int', 'description' => '도서산간 추가 배송비'],
    'return_shipping_fee' => ['value_type' => 'int', 'description' => '반품 배송비'],
    'exchange_shipping_fee' => ['value_type' => 'int', 'description' => '교환 배송비'],
  ];

  private const PRICING_METHODS = ['SUPPLY_PRICE', 'MARGIN_RATE'];
  private const ROUNDING_UNITS = [1, 10, 100, 1000];
  private const ROUNDING_TYPES = ['CEIL', 'ROUND', 'FLOOR'];
  private const SHIPPING_TYPES = ['PAID', 'QUANTITY', 'FREE', 'COD'];
  private const YES_NO_VALUES = ['Y', 'N'];
  private const THUMBNAIL_FITS = ['CONTAIN', 'COVER'];

  private ?array $cachedSettings = null;

  /**
   * 상품 등록설정 서비스를 초기화합니다.
   *
   * @param SettingRepository $repository 설정 저장소
   *
   * @return void
   */
  public function __construct(private readonly SettingRepository $repository)
  {
  }

  /**
   * 상품 등록설정 기본값을 반환합니다.
   *
   * @return array
   */
  public static function defaults(): array
  {
    return self::DEFAULTS;
  }

  /**
   * 상품 등록설정 메타 정보를 반환합니다.
   *
   * @return array
   */
  public static function meta(): array
  {
    return self::META;
  }

  /**
   * DB 설정과 기본값을 병합해 반환합니다.
   *
   * @return array
   */
  public function getSettings(): array
  {
    if ($this->cachedSettings !== null) {
      return $this->cachedSettings;
    }

    $stored = $this->repository->getGroupSettings(self::GROUP);
    $settings = array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    $this->cachedSettings = $this->normalizeStoredSettings($settings);

    return $this->cachedSettings;
  }

  /**
   * 입력값을 검증하고 저장 가능한 설정으로 정규화합니다.
   *
   * @param array $data 입력 데이터
   *
   * @return array
   */
  public function normalizeForSave(array $data): array
  {
    $normalized = [];

    $pricingMethod = (string) ($data['pricing_method'] ?? '');
    if (!in_array($pricingMethod, self::PRICING_METHODS, true)) {
      return $this->fail('기본 산정방식을 올바르게 선택해 주세요.', 'pricing_method');
    }
    $normalized['pricing_method'] = $pricingMethod;

    $marginRateResult = $this->normalizeDecimal($data, 'margin_rate', '기본 마진율은 0 이상의 숫자로 입력해 주세요.', 'margin_rate', 0, 1000);
    if (($marginRateResult['success'] ?? false) !== true) {
      return $marginRateResult;
    }
    $normalized['margin_rate'] = $marginRateResult['value'];

    $roundingUnitResult = $this->normalizeInt($data, 'rounding_unit', '반올림 단위를 올바르게 선택해 주세요.', 'rounding_unit', 1, 1000);
    if (($roundingUnitResult['success'] ?? false) !== true) {
      return $roundingUnitResult;
    }
    if (!in_array((int) $roundingUnitResult['value'], self::ROUNDING_UNITS, true)) {
      return $this->fail('반올림 단위를 올바르게 선택해 주세요.', 'rounding_unit');
    }
    $normalized['rounding_unit'] = $roundingUnitResult['value'];

    $roundingType = (string) ($data['rounding_type'] ?? '');
    if (!in_array($roundingType, self::ROUNDING_TYPES, true)) {
      return $this->fail('처리 방식을 올바르게 선택해 주세요.', 'rounding_type');
    }
    $normalized['rounding_type'] = $roundingType;

    $blockUnderSupplyPrice = (string) ($data['block_under_supply_price'] ?? 'N');
    if (!in_array($blockUnderSupplyPrice, self::YES_NO_VALUES, true)) {
      return $this->fail('가격 보호 값을 올바르게 선택해 주세요.', 'block_under_supply_price');
    }
    $normalized['block_under_supply_price'] = $blockUnderSupplyPrice;

    foreach ([
      'max_image_count' => ['이미지 등록 수는 1~20개로 입력해 주세요.', 1, 20],
      'max_option_count' => ['옵션 등록 수는 1~500개로 입력해 주세요.', 1, 500],
      'max_text_option_count' => ['텍스트 옵션 등록 수는 1~100개로 입력해 주세요.', 1, 100],
    ] as $field => [$message, $min, $max]) {
      $result = $this->normalizeInt($data, $field, $message, $field, $min, $max);
      if (($result['success'] ?? false) !== true) {
        return $result;
      }
      $normalized[$field] = $result['value'];
    }

    foreach ([
      'image_min_width' => ['원본 최소 가로는 100~5000px로 입력해 주세요.', 100, 5000],
      'image_min_height' => ['원본 최소 세로는 100~5000px로 입력해 주세요.', 100, 5000],
      'image_max_upload_mb' => ['원본 업로드 허용 용량은 1~100MB로 입력해 주세요.', 1, 100],
    ] as $field => [$message, $min, $max]) {
      $result = $this->normalizeInt($data, $field, $message, $field, $min, $max);
      if (($result['success'] ?? false) !== true) {
        return $result;
      }
      $normalized[$field] = $result['value'];
    }

    $thumbnailEnabled = (string) ($data['thumbnail_enabled'] ?? 'N');
    if (!in_array($thumbnailEnabled, self::YES_NO_VALUES, true)) {
      return $this->fail('썸네일 사용 여부를 올바르게 선택해 주세요.', 'thumbnail_enabled');
    }
    $normalized['thumbnail_enabled'] = $thumbnailEnabled;

    foreach ([
      'thumb_list_size' => '목록이미지 기준 크기는 50~3000px로 입력해 주세요.',
      'thumb_detail_size' => '상세이미지 기준 크기는 50~3000px로 입력해 주세요.',
      'thumb_detail_list_size' => '상세목록이미지 기준 크기는 50~3000px로 입력해 주세요.',
      'thumb_etc_list_size' => '기타목록이미지 기준 크기는 50~3000px로 입력해 주세요.',
    ] as $field => $message) {
      $result = $this->normalizeInt($data, $field, $message, $field, 50, 3000);
      if (($result['success'] ?? false) !== true) {
        return $result;
      }
      $normalized[$field] = $result['value'];
    }

    foreach ([
      'thumb_list_fit' => '목록이미지 노출방법을 올바르게 선택해 주세요.',
      'thumb_detail_fit' => '상세이미지 노출방법을 올바르게 선택해 주세요.',
      'thumb_detail_list_fit' => '상세목록이미지 노출방법을 올바르게 선택해 주세요.',
      'thumb_etc_list_fit' => '기타목록이미지 노출방법을 올바르게 선택해 주세요.',
    ] as $field => $message) {
      $value = (string) ($data[$field] ?? '');
      if (!in_array($value, self::THUMBNAIL_FITS, true)) {
        return $this->fail($message, $field);
      }
      $normalized[$field] = $value;
    }

    $platformImageEnabled = (string) ($data['platform_image_enabled'] ?? 'N');
    if (!in_array($platformImageEnabled, self::YES_NO_VALUES, true)) {
      return $this->fail('플랫폼용 이미지 생성 여부를 올바르게 선택해 주세요.', 'platform_image_enabled');
    }
    $normalized['platform_image_enabled'] = $platformImageEnabled;

    foreach ([
      'platform_image_width' => '플랫폼용 이미지 가로는 50~5000px로 입력해 주세요.',
      'platform_image_height' => '플랫폼용 이미지 세로는 50~5000px로 입력해 주세요.',
    ] as $field => $message) {
      $result = $this->normalizeInt($data, $field, $message, $field, 50, 5000);
      if (($result['success'] ?? false) !== true) {
        return $result;
      }
      $normalized[$field] = $result['value'];
    }

    $platformImageSmallFit = (string) ($data['platform_image_small_fit'] ?? '');
    if (!in_array($platformImageSmallFit, self::THUMBNAIL_FITS, true)) {
      return $this->fail('플랫폼용 이미지 생성 방식을 올바르게 선택해 주세요.', 'platform_image_small_fit');
    }
    $normalized['platform_image_small_fit'] = $platformImageSmallFit;

    $shippingType = (string) ($data['default_shipping_type'] ?? '');
    if (!in_array($shippingType, self::SHIPPING_TYPES, true)) {
      return $this->fail('기본 배송 정책을 올바르게 선택해 주세요.', 'default_shipping_type');
    }
    $normalized['default_shipping_type'] = $shippingType;

    foreach ([
      'shipping_fee' => '노출배송비는 0 이상의 숫자로 입력해 주세요.',
      'actual_shipping_fee' => '실제배송비는 0 이상의 숫자로 입력해 주세요.',
      'extra_shipping_jeju' => '제주 추가 배송비는 0 이상의 숫자로 입력해 주세요.',
      'extra_shipping_island' => '도서산간 추가 배송비는 0 이상의 숫자로 입력해 주세요.',
      'return_shipping_fee' => '반품 배송비는 0 이상의 숫자로 입력해 주세요.',
      'exchange_shipping_fee' => '교환 배송비는 0 이상의 숫자로 입력해 주세요.',
    ] as $field => $message) {
      $result = $this->normalizeInt($data, $field, $message, $field, 0, 10000000);
      if (($result['success'] ?? false) !== true) {
        return $result;
      }
      $normalized[$field] = $result['value'];
    }

    $qtyMin = $shippingType === 'QUANTITY' ? 1 : 0;
    $qtyResult = $this->normalizeInt($data, 'shipping_qty_limit', '합포장 기준 수량은 1 이상의 숫자로 입력해 주세요.', 'shipping_qty_limit', $qtyMin, 100000);
    if (($qtyResult['success'] ?? false) !== true) {
      return $qtyResult;
    }
    $normalized['shipping_qty_limit'] = $shippingType === 'QUANTITY' ? $qtyResult['value'] : 0;

    return ['success' => true, 'settings' => $normalized];
  }

  /**
   * 설정을 저장합니다.
   *
   * @param array $settings 저장할 설정
   *
   * @return void
   */
  public function save(array $settings): void
  {
    $this->repository->upsertGroupSettings(self::GROUP, $settings, self::META);
    $this->cachedSettings = null;
  }

  /**
   * DB 설정값을 허용 범위 안으로 보정합니다.
   *
   * @param array $settings 설정값
   *
   * @return array
   */
  private function normalizeStoredSettings(array $settings): array
  {
    $data = array_merge(self::DEFAULTS, $settings);
    $data['pricing_method'] = in_array((string) $data['pricing_method'], self::PRICING_METHODS, true) ? (string) $data['pricing_method'] : self::DEFAULTS['pricing_method'];
    $data['rounding_unit'] = in_array((int) $data['rounding_unit'], self::ROUNDING_UNITS, true) ? (int) $data['rounding_unit'] : self::DEFAULTS['rounding_unit'];
    $data['rounding_type'] = in_array((string) $data['rounding_type'], self::ROUNDING_TYPES, true) ? (string) $data['rounding_type'] : self::DEFAULTS['rounding_type'];
    $data['block_under_supply_price'] = in_array((string) $data['block_under_supply_price'], self::YES_NO_VALUES, true) ? (string) $data['block_under_supply_price'] : self::DEFAULTS['block_under_supply_price'];
    $data['default_shipping_type'] = in_array((string) $data['default_shipping_type'], self::SHIPPING_TYPES, true) ? (string) $data['default_shipping_type'] : self::DEFAULTS['default_shipping_type'];
    $data['thumbnail_enabled'] = in_array((string) $data['thumbnail_enabled'], self::YES_NO_VALUES, true) ? (string) $data['thumbnail_enabled'] : self::DEFAULTS['thumbnail_enabled'];
    $data['platform_image_enabled'] = in_array((string) $data['platform_image_enabled'], self::YES_NO_VALUES, true) ? (string) $data['platform_image_enabled'] : self::DEFAULTS['platform_image_enabled'];
    $data['margin_rate'] = is_numeric($data['margin_rate']) ? (float) $data['margin_rate'] : self::DEFAULTS['margin_rate'];
    foreach (['shipping_fee', 'actual_shipping_fee', 'shipping_qty_limit', 'max_image_count', 'max_option_count', 'max_text_option_count', 'image_min_width', 'image_min_height', 'image_max_upload_mb', 'thumb_list_size', 'thumb_detail_size', 'thumb_detail_list_size', 'thumb_etc_list_size', 'platform_image_width', 'platform_image_height', 'extra_shipping_jeju', 'extra_shipping_island', 'return_shipping_fee', 'exchange_shipping_fee'] as $field) {
      $data[$field] = is_numeric($data[$field]) ? (int) $data[$field] : self::DEFAULTS[$field];
    }

    foreach (['thumb_list_fit', 'thumb_detail_fit', 'thumb_detail_list_fit', 'thumb_etc_list_fit', 'platform_image_small_fit'] as $field) {
      $data[$field] = in_array((string) $data[$field], self::THUMBNAIL_FITS, true) ? (string) $data[$field] : self::DEFAULTS[$field];
    }

    $data['max_image_count'] = min(20, max(1, (int) $data['max_image_count']));
    $data['max_option_count'] = min(500, max(1, (int) $data['max_option_count']));
    $data['max_text_option_count'] = min(100, max(1, (int) $data['max_text_option_count']));
    $data['image_min_width'] = min(5000, max(100, (int) $data['image_min_width']));
    $data['image_min_height'] = min(5000, max(100, (int) $data['image_min_height']));

    $data['image_max_upload_mb'] = min(100, max(1, (int) $data['image_max_upload_mb']));
    foreach (['thumb_list_size', 'thumb_detail_size', 'thumb_detail_list_size', 'thumb_etc_list_size'] as $field) {
      $data[$field] = min(3000, max(50, (int) $data[$field]));
    }
    $data['platform_image_width'] = min(5000, max(50, (int) $data['platform_image_width']));
    $data['platform_image_height'] = min(5000, max(50, (int) $data['platform_image_height']));
    $data['shipping_qty_limit'] = max(0, (int) $data['shipping_qty_limit']);

    return $data;
  }

  /**
   * 소수 입력값을 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param string $field 필드명
   * @param string $message 오류 문구
   * @param string $focusField 포커스 필드
   * @param float $min 최소값
   * @param float $max 최대값
   *
   * @return array
   */
  private function normalizeDecimal(array $data, string $field, string $message, string $focusField, float $min, float $max): array
  {
    $value = trim((string) ($data[$field] ?? ''));
    if ($value === '' || preg_match('/^\d+(?:\.\d+)?$/', $value) !== 1 || (float) $value < $min || (float) $value > $max) {
      return $this->fail($message, $focusField);
    }

    return ['success' => true, 'value' => (float) $value];
  }

  /**
   * 정수 입력값을 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param string $field 필드명
   * @param string $message 오류 문구
   * @param string $focusField 포커스 필드
   * @param int $min 최소값
   * @param int $max 최대값
   *
   * @return array
   */
  private function normalizeInt(array $data, string $field, string $message, string $focusField, int $min, int $max): array
  {
    $value = trim((string) ($data[$field] ?? ''));
    if ($value === '' || preg_match('/^\d+$/', $value) !== 1 || (int) $value < $min || (int) $value > $max) {
      return $this->fail($message, $focusField);
    }

    return ['success' => true, 'value' => (int) $value];
  }

  /**
   * 실패 응답 배열을 생성합니다.
   *
   * @param string $message 오류 안내 문구
   * @param string $field 오류 필드명
   *
   * @return array
   */
  private function fail(string $message, string $field): array
  {
    return [
      'success' => false,
      'message' => $message,
      'field' => $field,
      'status' => 400,
    ];
  }
}
