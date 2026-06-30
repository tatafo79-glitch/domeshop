<?php

declare(strict_types=1);

namespace App\Services\Admin\Goods\Register;

use App\Lib\GoodsLib;
use App\Repositories\Admin\RestrictedWordRepository;
use App\Repositories\Common\UploadRepository;
use App\Services\Admin\Setting\Goods\Register\GoodsRegisterSetting;
use App\Services\BaseService;
use PDOException;
use Throwable;

class PostService extends BaseService
{
  private const DEFAULT_VENDOR_CODE = 'V-ADMN';
  private const GOODS_TYPES = ['NORMAL', 'HEALTH', 'MEDICAL'];
  private const GOODS_STATUSES = ['NEW', 'USED', 'REFURB'];
  private const YES_NO_VALUES = ['Y', 'N'];
  private const TAX_TYPES = ['TAX', 'FREE'];
  private const PRICE_POLICIES = ['FREE', 'COMPLY'];
  private const SHIPPING_TYPES = ['PAID', 'QUANTITY', 'FREE', 'COD'];
  private const SOLDOUT_VALUES = ['0', '1'];
  private const MAX_OPTION_VALUE_LENGTH = 100;
  private const MAX_TEXT_OPTION_TITLE_LENGTH = 100;
  private const MAX_TEXT_OPTION_INPUT_LENGTH = 1000;
  private const MAX_IMAGE_PATH_LENGTH = 255;

  /**
   * 상품 등록 데이터를 검증하고 저장합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $normalizedResult = $this->normalize($data);
    if (($normalizedResult['success'] ?? false) !== true) {
      return $normalizedResult;
    }

    $goodsData = $normalizedResult['goods'];
    $options = $normalizedResult['options'];
    $textOptions = $normalizedResult['text_options'];
    $images = $normalizedResult['images'];

    $this->db->beginTransaction();
    try {
      $goodsId = (int) $this->repo?->insertGoods($goodsData);

      foreach ($images as $image) {
        $image['goods_id'] = $goodsId;
        $this->repo?->insertGoodsImage($image);
      }

      foreach ($options as $option) {
        $option['goods_id'] = $goodsId;
        $this->repo?->insertGoodsOption($option);
      }

      foreach ($textOptions as $textOption) {
        $textOption['goods_id'] = $goodsId;
        $this->repo?->insertGoodsTextOption($textOption);
      }

      $this->syncFileUsage($images, (string) ($goodsData['content'] ?? ''));
      $this->db->commit();
    } catch (PDOException $e) {
      $this->db->rollBack();
      if ($e->getCode() === '23000') {
        return $this->fail('동일 공급사 상품 코드가 이미 등록되어 있습니다.', 'vendor_goods_code', 409);
      }

      throw $e;
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => '상품이 등록되었습니다.',
      'redirect' => $this->adminUrl('/goods/register'),
      'data' => ['id' => $goodsId],
    ];
  }

  /**
   * 등록 데이터를 DB 저장용 구조로 정규화합니다.
   *
   * @param array $data 입력 데이터
   *
   * @return array
   */
  private function normalize(array $data): array
  {
    $settings = $this->container->get(GoodsRegisterSetting::class)->getSettings();

    $categoryIdResult = $this->normalizeRequiredInt($data, 'category_id', '카테고리를 선택해 주세요.', 'categorySearchInput', 1);
    if (($categoryIdResult['success'] ?? false) !== true) {
      return $categoryIdResult;
    }
    $categoryId = (int) $categoryIdResult['value'];
    $category = $this->repo?->getActiveCategoryById($categoryId);
    if ($category === null) {
      return $this->fail('유효한 카테고리를 선택해 주세요.', 'categorySearchInput');
    }

    $vendorCode = trim((string) ($data['vendor_code'] ?? ''));
    // 공급사 미입력 상품은 관리자 직영 코드로 서버에서 확정합니다.
    if ($vendorCode === '') {
      $vendorCode = self::DEFAULT_VENDOR_CODE;
    }
    if (preg_match('/^[A-Za-z0-9_-]{1,20}$/', $vendorCode) !== 1) {
      return $this->fail('공급사 코드를 올바르게 입력해 주세요.', 'vendor_code');
    }
    if ($vendorCode !== self::DEFAULT_VENDOR_CODE && $this->repo?->approvedVendorCodeExists($vendorCode) !== true) {
      return $this->fail('승인된 공급사 코드를 선택해 주세요.', 'vendor_code');
    }

    $vendorGoodsCode = trim((string) ($data['vendor_goods_code'] ?? ''));
    if (mb_strlen($vendorGoodsCode) > 100) {
      return $this->fail('공급사 상품 코드는 100자 이하로 입력해 주세요.', 'vendor_goods_code');
    }

    $name = trim((string) ($data['name'] ?? ''));
    if ($name === '') {
      return $this->fail('상품명을 입력해 주세요.', 'name');
    }
    if (mb_strlen($name) > 255) {
      return $this->fail('상품명은 255자 이하로 입력해 주세요.', 'name');
    }

    $manufacturer = trim((string) ($data['manufacturer'] ?? ''));

    $originResult = $this->normalizeOrigin($data);
    if (($originResult['success'] ?? false) !== true) {
      return $originResult;
    }
    $origin = (string) $originResult['value'];

    $goodsType = trim((string) ($data['goods_type'] ?? ''));
    if (!in_array($goodsType, self::GOODS_TYPES, true)) {
      return $this->fail('상품타입을 올바르게 선택해 주세요.', 'goods_type');
    }

    $goodsStatusResult = $this->normalizeEnum($data, 'goods_status', self::GOODS_STATUSES, '상품상태를 올바르게 선택해 주세요.', 'goods_status');
    if (($goodsStatusResult['success'] ?? false) !== true) {
      return $goodsStatusResult;
    }

    $adultOnlyResult = $this->normalizeEnum($data, 'adult_only', self::YES_NO_VALUES, '성인전용 여부를 올바르게 선택해 주세요.', 'adult_only');
    if (($adultOnlyResult['success'] ?? false) !== true) {
      return $adultOnlyResult;
    }

    $keywordsResult = $this->normalizeKeywords($data);
    if (($keywordsResult['success'] ?? false) !== true) {
      return $keywordsResult;
    }

    $restrictedWordResult = $this->validateRestrictedWords($name, $manufacturer, (string) $keywordsResult['value']);
    if (($restrictedWordResult['success'] ?? false) !== true) {
      return $restrictedWordResult;
    }

    $taxTypeResult = $this->normalizeEnum($data, 'tax_type', self::TAX_TYPES, '과세여부를 올바르게 선택해 주세요.', 'tax_type');
    if (($taxTypeResult['success'] ?? false) !== true) {
      return $taxTypeResult;
    }

    $supplyPriceResult = $this->normalizeRequiredInt($data, 'supply_price', '공급가는 0 이상의 숫자로 입력해 주세요.', 'supply_price');
    if (($supplyPriceResult['success'] ?? false) !== true) {
      return $supplyPriceResult;
    }

    $sellPriceResult = $this->normalizeRequiredInt($data, 'sell_price', '판매가는 0 이상의 숫자로 입력해 주세요.', 'sell_price');
    if (($sellPriceResult['success'] ?? false) !== true) {
      return $sellPriceResult;
    }

    $sellPriceFixedResult = $this->normalizeFlag($data, 'sell_price_fixed', '판매가 고정 여부를 올바르게 선택해 주세요.', 'sell_price_fixed');
    if (($sellPriceFixedResult['success'] ?? false) !== true) {
      return $sellPriceFixedResult;
    }

    $supplyPrice = (int) $supplyPriceResult['value'];
    $sellPrice = (int) $sellPriceResult['value'];
    if ($sellPriceFixedResult['value'] !== 'Y') {
      $sellPrice = $this->calculateDefaultSellPrice($supplyPrice, $settings);
    }
    if ($settings['block_under_supply_price'] === 'Y' && $sellPrice < $supplyPrice) {
      return $this->fail('판매가는 공급가보다 작을 수 없습니다.', 'sell_price');
    }

    $pricePolicyResult = $this->normalizeEnum($data, 'price_policy', self::PRICE_POLICIES, '가격 정책을 올바르게 선택해 주세요.', 'price_policy');
    if (($pricePolicyResult['success'] ?? false) !== true) {
      return $pricePolicyResult;
    }

    $compliancePrice = 0;
    if ($pricePolicyResult['value'] === 'COMPLY') {
      $compliancePriceResult = $this->normalizeRequiredInt($data, 'compliance_price', '준수가격은 0 이상의 숫자로 입력해 주세요.', 'compliance_price');
      if (($compliancePriceResult['success'] ?? false) !== true) {
        return $compliancePriceResult;
      }
      if ((int) $compliancePriceResult['value'] < 1) {
        return $this->fail('가격준수 선택 시 준수가격은 1 이상 입력해 주세요.', 'compliance_price');
      }
      $compliancePrice = (int) $compliancePriceResult['value'];
      if ($settings['block_under_supply_price'] === 'Y' && $sellPrice < $compliancePrice) {
        return $this->fail('가격준수 선택 시 판매가는 준수가격보다 작을 수 없습니다.', 'sell_price');
      }
    }

    $stockResult = $this->normalizeRequiredInt($data, 'stock', '재고 수량은 0 이상의 숫자로 입력해 주세요.', 'stock');
    if (($stockResult['success'] ?? false) !== true) {
      return $stockResult;
    }

    $stockLinkResult = $this->normalizeFlag($data, 'stock_link', '재고 연동 여부를 올바르게 선택해 주세요.', 'stock_link');
    if (($stockLinkResult['success'] ?? false) !== true) {
      return $stockLinkResult;
    }

    $soldoutResult = $this->normalizeEnum($data, 'soldout', self::SOLDOUT_VALUES, '품절 여부를 올바르게 선택해 주세요.', 'soldout');
    if (($soldoutResult['success'] ?? false) !== true) {
      return $soldoutResult;
    }

    $isDisplayResult = $this->normalizeFlag($data, 'is_display', '상품 노출 값을 올바르게 선택해 주세요.', 'is_display');
    if (($isDisplayResult['success'] ?? false) !== true) {
      return $isDisplayResult;
    }

    $isExportableResult = $this->normalizeFlag($data, 'is_exportable', 'API/다운로드 허용 값을 올바르게 선택해 주세요.', 'is_exportable');
    if (($isExportableResult['success'] ?? false) !== true) {
      return $isExportableResult;
    }

    $hasOptionResult = $this->normalizeFlag($data, 'has_option', '옵션 사용 여부를 올바르게 선택해 주세요.', 'has_option');
    if (($hasOptionResult['success'] ?? false) !== true) {
      return $hasOptionResult;
    }

    $hasTextOptionResult = $this->normalizeFlag($data, 'has_text_option', '텍스트 옵션 사용 여부를 올바르게 선택해 주세요.', 'has_text_option');
    if (($hasTextOptionResult['success'] ?? false) !== true) {
      return $hasTextOptionResult;
    }

    $optionsResult = $this->normalizeOptions($data, $hasOptionResult['value'] === 'Y', (int) $settings['max_option_count']);
    if (($optionsResult['success'] ?? false) !== true) {
      return $optionsResult;
    }

    $textOptionsResult = $this->normalizeTextOptions($data, $hasTextOptionResult['value'] === 'Y', (int) $settings['max_text_option_count']);
    if (($textOptionsResult['success'] ?? false) !== true) {
      return $textOptionsResult;
    }

    $shippingResult = $this->normalizeShipping($data);
    if (($shippingResult['success'] ?? false) !== true) {
      return $shippingResult;
    }

    $imagesResult = $this->normalizeImages($data, (int) $settings['max_image_count']);
    if (($imagesResult['success'] ?? false) !== true) {
      return $imagesResult;
    }
    $images = $imagesResult['images'];
    $content = (string) ($data['content'] ?? '');
    if ($this->hasDetailContent($content) !== true) {
      return $this->fail('상품 상세 설명을 입력해 주세요.', 'content');
    }

    $goodsData = array_merge([
      'vendor_code' => $vendorCode,
      'vendor_goods_code' => $vendorGoodsCode === '' ? null : $vendorGoodsCode,
      'category_id' => $categoryId,
      'category_path' => (string) ($category['path'] ?? ''),
      'origin' => $origin,
      'manufacturer' => $manufacturer === '' ? null : mb_substr($manufacturer, 0, 100),
      'brand' => $this->nullableString($data, 'brand', 100),
      'name' => $name,
      'invoice_name' => $this->nullableString($data, 'invoice_name', 255),
      'goods_type' => $goodsType,
      'goods_status' => $goodsStatusResult['value'],
      'adult_only' => $adultOnlyResult['value'],
      'search_keywords' => $keywordsResult['value'],
      'search_text' => trim($name . ' ' . (string) $keywordsResult['value']),
      'tax_type' => $taxTypeResult['value'],
      'supply_price' => $supplyPrice,
      'sell_price' => $sellPrice,
      'sell_price_fixed' => $sellPriceFixedResult['value'],
      'price_policy' => $pricePolicyResult['value'],
      'compliance_price' => $compliancePrice,
      'stock' => (int) $stockResult['value'],
      'stock_link' => $stockLinkResult['value'],
      'soldout' => (int) $soldoutResult['value'],
      'is_display' => $isDisplayResult['value'],
      'is_exportable' => $isExportableResult['value'],
      'has_option' => $hasOptionResult['value'],
      'has_text_option' => $hasTextOptionResult['value'],
      'option_title1' => $this->nullableString($data, 'option_title1', 100),
      'option_title2' => $this->nullableString($data, 'option_title2', 100),
      'option_title3' => $this->nullableString($data, 'option_title3', 100),
      'has_soldout_option' => $optionsResult['has_soldout_option'],
      'thumbnail_url' => $images[0]['file_path'] ?? null,
      'content' => $content,
    ], $shippingResult['data']);

    return [
      'success' => true,
      'goods' => $goodsData,
      'options' => $optionsResult['options'],
      'text_options' => $textOptionsResult['text_options'],
      'images' => $images,
    ];
  }

  /**
   * 설정된 판매가 산정식으로 기본 판매가를 계산합니다.
   *
   * @param int $supplyPrice 공급가
   * @param array $settings 상품 등록설정
   *
   * @return int
   */
  private function calculateDefaultSellPrice(int $supplyPrice, array $settings): int
  {
    $marginRate = max(0, (int) ($settings['margin_rate'] ?? 0)) / 100;
    if (($settings['pricing_method'] ?? 'SUPPLY_PRICE') === 'MARGIN_RATE') {
      $rawPrice = $marginRate >= 1 ? $supplyPrice : $supplyPrice / (1 - $marginRate);
    } else {
      $rawPrice = $supplyPrice * (1 + $marginRate);
    }

    return max(0, $this->roundCalculatedPrice($rawPrice, $settings));
  }

  /**
   * 설정된 반올림 단위와 처리 방식으로 금액을 보정합니다.
   *
   * @param float $price 원본 금액
   * @param array $settings 상품 등록설정
   *
   * @return int
   */
  private function roundCalculatedPrice(float $price, array $settings): int
  {
    $unit = max(1, (int) ($settings['rounding_unit'] ?? 1));
    $scaledPrice = $price / $unit;
    $roundingType = (string) ($settings['rounding_type'] ?? 'ROUND');

    if ($roundingType === 'CEIL') {
      return (int) (ceil($scaledPrice) * $unit);
    }

    if ($roundingType === 'FLOOR') {
      return (int) (floor($scaledPrice) * $unit);
    }

    return (int) (round($scaledPrice) * $unit);
  }
  /**
   * 필수 정수값을 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param string $field 필드명
   * @param string $message 오류 문구
   * @param string $focusField 포커스 필드
   * @param int $min 최소값
   *
   * @return array
   */
  private function normalizeRequiredInt(array $data, string $field, string $message, string $focusField, int $min = 0): array
  {
    $value = trim((string) ($data[$field] ?? ''));
    if ($value === '' || preg_match('/^\d+$/', $value) !== 1 || (int) $value < $min) {
      return $this->fail($message, $focusField);
    }

    return ['success' => true, 'value' => (int) $value];
  }

  /**
   * 허용값 기반 문자열을 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param string $field 필드명
   * @param array $allowed 허용값
   * @param string $message 오류 문구
   * @param string $focusField 포커스 필드
   *
   * @return array
   */
  private function normalizeEnum(array $data, string $field, array $allowed, string $message, string $focusField): array
  {
    $value = (string) ($data[$field] ?? '');
    if (!in_array($value, $allowed, true)) {
      return $this->fail($message, $focusField);
    }

    return ['success' => true, 'value' => $value];
  }

  /**
   * Y/N 플래그를 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param string $field 필드명
   * @param string $message 오류 문구
   * @param string $focusField 포커스 필드
   *
   * @return array
   */
  private function normalizeFlag(array $data, string $field, string $message, string $focusField): array
  {
    return $this->normalizeEnum($data, $field, self::YES_NO_VALUES, $message, $focusField);
  }

  /**
   * 검색 키워드를 검증합니다.
   *
   * @param array $data 입력 데이터
   *
   * @return array
   */
  private function normalizeKeywords(array $data): array
  {
    $keywords = trim((string) ($data['search_keywords'] ?? ''));
    if ($keywords === '') {
      return $this->fail('상품키워드는 5개 이상 입력해 주세요.', 'keywordTagInput');
    }

    $items = array_values(array_filter(array_map('trim', explode(',', $keywords)), static fn (string $value): bool => $value !== ''));
    if (count($items) < 5) {
      return $this->fail('상품키워드는 5개 이상 입력해 주세요.', 'keywordTagInput');
    }

    foreach ($items as $item) {
      if (preg_match('/^[0-9A-Za-z가-힣]+$/u', $item) !== 1) {
        return $this->fail('키워드는 한글, 영문, 숫자만 입력해 주세요. 공백과 특수문자는 사용할 수 없습니다.', 'keywordTagInput');
      }
    }

    return ['success' => true, 'value' => implode(',', array_unique($items))];
  }

  /**
   * 상품명, 제조사, 검색 키워드의 금지단어 포함 여부를 검증합니다.
   *
   * @param string $name 상품명
   * @param string $manufacturer 제조사
   * @param string $keywords 검색 키워드
   *
   * @return array
   */
  private function validateRestrictedWords(string $name, string $manufacturer, string $keywords): array
  {
    $repository = $this->container->get(RestrictedWordRepository::class);
    $goodsLib = $this->container->get(GoodsLib::class);
    $violation = $goodsLib->findRestrictedWordViolation($name, $manufacturer, $keywords, $repository->getActiveRestrictedWords());
    if ($violation !== null) {
      $violation['status'] = 400;

      return $violation;
    }

    return ['success' => true];
  }

  /**
   * 원산지 단계 선택값을 검증하고 저장 문자열을 생성합니다.
   *
   * @param array $data 입력 데이터
   *
   * @return array
   */
  private function normalizeOrigin(array $data): array
  {
    $depth1Id = $this->normalizeOriginId($data, 'origin_depth1');
    if ($depth1Id === null) {
      return $this->fail('원산지를 선택해 주세요.', 'originDepth1');
    }

    $depth1 = $this->resolveOriginRoot($depth1Id);
    if ($depth1 === null) {
      return $this->fail('원산지를 올바르게 선택해 주세요.', 'originDepth1');
    }

    $rootLabel = $this->normalizeOriginRootName((string) ($depth1['nm'] ?? ''));
    $rootType = $this->getOriginRootType($rootLabel);
    if ($rootType === 'overseas') {
      return $this->normalizeOverseasOrigin($data, (int) ($depth1['id'] ?? 0), $rootLabel);
    }

    if ($rootType === 'domestic') {
      return $this->normalizeDomesticOrigin($data, (int) ($depth1['id'] ?? 0), $rootLabel);
    }

    return ['success' => true, 'value' => $rootLabel];
  }

  /**
   * 원산지 1차 루트 정보를 조회합니다.
   *
   * @param int $depth1Id 원산지 1차 ID 또는 루트 코드
   *
   * @return array|null
   */
  private function resolveOriginRoot(int $depth1Id): ?array
  {
    $origin = $this->repo?->getGoodsOriginById($depth1Id);
    if ($origin !== null && (int) ($origin['level'] ?? -1) === 0) {
      return $origin;
    }

    // 원산지 데이터가 0단계 루트 행 없이 cd0/pathnm0만 가진 경우를 지원합니다.
    return $this->repo?->getGoodsOriginRootByCode($depth1Id);
  }

  /**
   * 해외 원산지의 대륙/나라 선택값을 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param int $rootId 원산지 1차 ID
   * @param string $rootLabel 원산지 1차 라벨
   *
   * @return array
   */
  private function normalizeOverseasOrigin(array $data, int $rootId, string $rootLabel): array
  {
    $depth2Id = $this->normalizeOriginId($data, 'origin_depth2');
    if ($depth2Id === null) {
      return $this->fail('대륙을 선택해 주세요.', 'originDepth2');
    }

    $depth2 = $this->repo?->getGoodsOriginById($depth2Id);
    if (!$this->isOriginChild($depth2, $rootId, null, 1)) {
      return $this->fail('대륙을 올바르게 선택해 주세요.', 'originDepth2');
    }

    $depth3Id = $this->normalizeOriginId($data, 'origin_depth3');
    if ($depth3Id === null) {
      return $this->fail('나라를 선택해 주세요.', 'originDepth3');
    }

    $depth3 = $this->repo?->getGoodsOriginById($depth3Id);
    if (!$this->isOriginChild($depth3, $rootId, (int) ($depth2['id'] ?? 0), 2)) {
      return $this->fail('나라를 올바르게 선택해 주세요.', 'originDepth3');
    }

    return [
      'success' => true,
      'value' => $rootLabel . '|' . (string) ($depth2['nm'] ?? '') . '|' . (string) ($depth3['nm'] ?? ''),
    ];
  }

  /**
   * 국내 원산지의 지역/시군구 선택값을 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param int $rootId 원산지 1차 ID
   * @param string $rootLabel 원산지 1차 라벨
   *
   * @return array
   */
  private function normalizeDomesticOrigin(array $data, int $rootId, string $rootLabel): array
  {
    $depth2Id = $this->normalizeOriginId($data, 'origin_depth2');
    if ($depth2Id === null) {
      return ['success' => true, 'value' => $rootLabel];
    }

    $depth2 = $this->repo?->getGoodsOriginById($depth2Id);
    if (!$this->isOriginChild($depth2, $rootId, null, 1)) {
      return $this->fail('지역을 올바르게 선택해 주세요.', 'originDepth2');
    }

    $depth3Id = $this->normalizeOriginId($data, 'origin_depth3');
    if ($depth3Id === null) {
      return $this->fail('시군구를 선택해 주세요.', 'originDepth3');
    }

    $depth3 = $this->repo?->getGoodsOriginById($depth3Id);
    if (!$this->isOriginChild($depth3, $rootId, (int) ($depth2['id'] ?? 0), 2)) {
      return $this->fail('시군구를 올바르게 선택해 주세요.', 'originDepth3');
    }

    return [
      'success' => true,
      'value' => $rootLabel . '|' . (string) ($depth2['nm'] ?? '') . '|' . (string) ($depth3['nm'] ?? ''),
    ];
  }

  /**
   * 원산지 ID 입력값을 정수로 정규화합니다.
   *
   * @param array $data 입력 데이터
   * @param string $field 필드명
   *
   * @return int|null
   */
  private function normalizeOriginId(array $data, string $field): ?int
  {
    $value = trim((string) ($data[$field] ?? ''));
    if ($value === '') {
      return null;
    }

    if (preg_match('/^[0-9]+$/', $value) !== 1 || (int) $value < 1) {
      return null;
    }

    return (int) $value;
  }

  /**
   * 원산지 루트명을 화면 라벨과 동일하게 정규화합니다.
   *
   * @param string $name 원산지명
   *
   * @return string
   */
  private function normalizeOriginRootName(string $name): string
  {
    $trimmedName = trim($name);
    if (str_contains($trimmedName, '국내')) {
      return '국내';
    }

    if (str_contains($trimmedName, '해외')) {
      return '해외';
    }

    return $trimmedName;
  }

  /**
   * 원산지 루트 타입을 판별합니다.
   *
   * @param string $label 원산지 루트 라벨
   *
   * @return string
   */
  private function getOriginRootType(string $label): string
  {
    if (str_contains($label, '국내')) {
      return 'domestic';
    }

    if (str_contains($label, '해외')) {
      return 'overseas';
    }

    return $label === '' ? '' : 'other';
  }

  /**
   * 원산지 하위 단계 관계를 검증합니다.
   *
   * @param array|null $origin 원산지 행
   * @param int $rootId 원산지 1차 ID
   * @param int|null $parentId 원산지 2차 ID
   * @param int $level 기대 단계
   *
   * @return bool
   */
  private function isOriginChild(?array $origin, int $rootId, ?int $parentId, int $level): bool
  {
    if ($origin === null || (int) ($origin['level'] ?? -1) !== $level) {
      return false;
    }

    if ((int) ($origin['cd0'] ?? 0) !== $rootId) {
      return false;
    }

    if ($parentId !== null && (int) ($origin['cd1'] ?? 0) !== $parentId) {
      return false;
    }

    return true;
  }

  /**
   * 상세 설명에 텍스트 또는 이미지/미디어 내용이 있는지 확인합니다.
   *
   * @param string $content 상품 상세 설명 HTML
   *
   * @return bool
   */
  private function hasDetailContent(string $content): bool
  {
    $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xc2\xa0", ' ', $text);
    $normalizedText = preg_replace('/\s+/u', '', $text);

    if (is_string($normalizedText) && $normalizedText !== '') {
      return true;
    }

    return preg_match('/<(img|iframe|video|source)\b[^>]*(src|data-src)=["\'][^"\']+["\']/i', $content) === 1;
  }

  /**
   * 배송비 데이터를 검증합니다.
   *
   * @param array $data 입력 데이터
   *
   * @return array
   */
  private function normalizeShipping(array $data): array
  {
    $shippingTypeResult = $this->normalizeEnum($data, 'shipping_type', self::SHIPPING_TYPES, '배송 정책을 올바르게 선택해 주세요.', 'shipping_type');
    if (($shippingTypeResult['success'] ?? false) !== true) {
      return $shippingTypeResult;
    }

    $fields = [
      'shipping_fee' => '노출배송비는 0 이상의 숫자로 입력해 주세요.',
      'actual_shipping_fee' => '실제배송비는 0 이상의 숫자로 입력해 주세요.',
      'extra_shipping_jeju' => '제주 추가 배송비는 0 이상의 숫자로 입력해 주세요.',
      'extra_shipping_island' => '도서산간 추가 배송비는 0 이상의 숫자로 입력해 주세요.',
      'return_shipping_fee' => '반품 배송비는 0 이상의 숫자로 입력해 주세요.',
      'exchange_shipping_fee' => '교환 배송비는 0 이상의 숫자로 입력해 주세요.',
    ];

    $values = [];
    foreach ($fields as $field => $message) {
      $result = $this->normalizeRequiredInt($data, $field, $message, $field);
      if (($result['success'] ?? false) !== true) {
        return $result;
      }
      $values[$field] = (int) $result['value'];
    }

    $values['shipping_qty_limit'] = 0;
    if ($shippingTypeResult['value'] === 'QUANTITY') {
      $qtyLimitResult = $this->normalizeRequiredInt($data, 'shipping_qty_limit', '합포장 기준 수량은 1 이상의 숫자로 입력해 주세요.', 'shipping_qty_limit', 1);
      if (($qtyLimitResult['success'] ?? false) !== true) {
        return $qtyLimitResult;
      }
      $values['shipping_qty_limit'] = (int) $qtyLimitResult['value'];
    }

    $hasExtraShippingResult = $this->normalizeFlag($data, 'has_extra_shipping', '추가 배송비 사용 여부를 올바르게 선택해 주세요.', 'has_extra_shipping');
    if (($hasExtraShippingResult['success'] ?? false) !== true) {
      return $hasExtraShippingResult;
    }

    return [
      'success' => true,
      'data' => array_merge([
        'shipping_type' => $shippingTypeResult['value'],
        'has_extra_shipping' => $hasExtraShippingResult['value'],
      ], $values),
    ];
  }

  /**
   * 조합 옵션 데이터를 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param bool $enabled 옵션 사용 여부
   * @param int $maxOptionCount 최대 옵션 등록 수
   *
   * @return array
   */
  private function normalizeOptions(array $data, bool $enabled, int $maxOptionCount): array
  {
    $rows = is_array($data['options'] ?? null) ? $data['options'] : [];
    if ($enabled && count($rows) === 0) {
      return $this->fail('옵션 분류명 및 옵션 항목을 먼저 적용해 주세요.', 'addOptionBtn');
    }
    if ($enabled && count($rows) > $maxOptionCount) {
      return $this->fail('옵션은 최대 ' . $maxOptionCount . '개까지 등록할 수 있습니다.', 'addOptionBtn');
    }

    $options = [];
    $hasSoldout = 0;
    foreach ($rows as $index => $row) {
      if (!is_array($row)) {
        continue;
      }

      $optionVal1 = trim((string) ($row['option_val1'] ?? ''));
      if ($optionVal1 === '') {
        return $this->fail('옵션값 1을 입력해 주세요.', 'addOptionBtn');
      }
      if (mb_strlen($optionVal1) > self::MAX_OPTION_VALUE_LENGTH) {
        return $this->fail('옵션값은 100자 이하로 입력해 주세요.', 'addOptionBtn');
      }

      $optionVal2 = trim((string) ($row['option_val2'] ?? ''));
      if (mb_strlen($optionVal2) > self::MAX_OPTION_VALUE_LENGTH) {
        return $this->fail('옵션값은 100자 이하로 입력해 주세요.', 'addOptionBtn');
      }

      $soldout = (string) ($row['soldout'] ?? '');
      if (!in_array($soldout, self::SOLDOUT_VALUES, true)) {
        return $this->fail('옵션 품절 값을 올바르게 선택해 주세요.', 'addOptionBtn');
      }
      if ($soldout === '1') {
        $hasSoldout = 1;
      }

      $isDisplay = (string) ($row['is_display'] ?? '');
      if (!in_array($isDisplay, self::YES_NO_VALUES, true)) {
        return $this->fail('옵션 노출 값을 올바르게 선택해 주세요.', 'addOptionBtn');
      }

      $numbers = [];
      foreach (['option_supply_price', 'option_sell_price', 'option_compliance_price', 'stock'] as $field) {
        $value = trim((string) ($row[$field] ?? ''));
        if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
          return $this->fail('옵션 가격과 재고는 0 이상의 숫자로 입력해 주세요.', 'addOptionBtn');
        }
        $numbers[$field] = (int) $value;
      }

      $options[] = array_merge([
        'option_val1' => $optionVal1,
        'option_val2' => $optionVal2 === '' ? null : $optionVal2,
        'option_val3' => null,
        'soldout' => (int) $soldout,
        'is_display' => $isDisplay,
      ], $numbers);
    }

    return ['success' => true, 'options' => $enabled ? $options : [], 'has_soldout_option' => $hasSoldout];
  }

  /**
   * 텍스트 입력 옵션 데이터를 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param bool $enabled 텍스트 옵션 사용 여부
   * @param int $maxTextOptionCount 최대 텍스트 옵션 등록 수
   *
   * @return array
   */
  private function normalizeTextOptions(array $data, bool $enabled, int $maxTextOptionCount): array
  {
    $rows = is_array($data['text_options'] ?? null) ? $data['text_options'] : [];
    if ($enabled && count($rows) === 0) {
      return $this->fail('입력옵션을 추가해 주세요.', 'addTextOptionBtn');
    }
    if ($enabled && count($rows) > $maxTextOptionCount) {
      return $this->fail('텍스트 옵션은 최대 ' . $maxTextOptionCount . '개까지 등록할 수 있습니다.', 'addTextOptionBtn');
    }

    $textOptions = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      $title = trim((string) ($row['title'] ?? ''));
      if ($title === '') {
        return $this->fail('입력옵션 질문을 입력해 주세요.', 'addTextOptionBtn');
      }
      if (mb_strlen($title) > self::MAX_TEXT_OPTION_TITLE_LENGTH) {
        return $this->fail('입력옵션 질문은 100자 이하로 입력해 주세요.', 'addTextOptionBtn');
      }

      $isRequired = (string) ($row['is_required'] ?? '');
      if (!in_array($isRequired, self::YES_NO_VALUES, true)) {
        return $this->fail('입력옵션 필수 여부를 올바르게 선택해 주세요.', 'addTextOptionBtn');
      }

      $isDisplay = (string) ($row['is_display'] ?? '');
      if (!in_array($isDisplay, self::YES_NO_VALUES, true)) {
        return $this->fail('입력옵션 상태를 올바르게 선택해 주세요.', 'addTextOptionBtn');
      }

      $maxLength = trim((string) ($row['max_length'] ?? ''));
      if (
        $maxLength === ''
        || preg_match('/^\d+$/', $maxLength) !== 1
        || (int) $maxLength < 1
        || (int) $maxLength > self::MAX_TEXT_OPTION_INPUT_LENGTH
      ) {
        return $this->fail('입력옵션 글자수 제한은 1~1000자로 입력해 주세요.', 'addTextOptionBtn');
      }

      $textOptions[] = [
        'title' => $title,
        'is_required' => $isRequired,
        'max_length' => (int) $maxLength,
        'is_display' => $isDisplay,
      ];
    }

    return ['success' => true, 'text_options' => $enabled ? $textOptions : []];
  }

  /**
   * 이미지 입력값을 정렬 순서에 맞게 구성합니다.
   *
   * @param array $data 입력 데이터
   * @param int $maxImageCount 최대 이미지 등록 수
   *
   * @return array
   */
  private function normalizeImages(array $data, int $maxImageCount): array
  {
    $paths = [];
    $thumbnail = trim((string) ($data['thumbnail_url'] ?? ''));
    if ($thumbnail !== '') {
      $paths[] = $thumbnail;
    }

    $extraImages = is_array($data['goods_images'] ?? null) ? $data['goods_images'] : [];
    foreach ($extraImages as $image) {
      $path = trim((string) $image);
      if ($path !== '' && !in_array($path, $paths, true)) {
        $paths[] = $path;
      }
    }

    if (count($paths) === 0) {
      return $this->fail('대표 이미지를 업로드해 주세요.', 'imageUploadInput');
    }
    if (count($paths) > $maxImageCount) {
      return $this->fail('이미지는 최대 ' . $maxImageCount . '장까지 등록할 수 있습니다.', 'imageUploadInput');
    }

    $images = [];
    foreach ($paths as $index => $path) {
      if (mb_strlen($path) > self::MAX_IMAGE_PATH_LENGTH) {
        return $this->fail('이미지 경로가 올바르지 않습니다.', 'imageUploadInput');
      }
      if ($this->isValidGoodsUploadFile($path) !== true) {
        return $this->fail('상품 이미지로 업로드한 파일만 등록할 수 있습니다.', 'imageUploadInput');
      }

      $images[] = [
        'file_path' => $path,
        'image_type' => 'LIST',
        'is_main' => $index === 0 ? 'Y' : 'N',
        'sort_order' => $index,
      ];
    }

    return ['success' => true, 'images' => $images];
  }

  /**
   * 업로드 파일을 사용 확정 처리합니다.
   *
   * @param array $images 이미지 목록
   * @param string $content 상품 상세 설명
   *
   * @return void
   */
  private function syncFileUsage(array $images, string $content): void
  {
    $uploadRepo = $this->container->get(UploadRepository::class);
    foreach ($images as $image) {
      $path = (string) ($image['file_path'] ?? '');
      if ($path !== '') {
        $uploadRepo->markAsUsedByPathAndCategory($path, 'goods');
      }
    }

    foreach ($this->extractImagePaths($content) as $path) {
      if ($uploadRepo->existsByPathAndCategory($path, 'goods')) {
        $uploadRepo->markAsUsedByPathAndCategory($path, 'goods');
      }
    }
  }

  /**
   * 상품 이미지 업로드 장부에 등록된 미사용 파일인지 확인합니다.
   *
   * @param string $filePath 업로드 파일 경로
   *
   * @return bool
   */
  private function isValidGoodsUploadFile(string $filePath): bool
  {
    if ($filePath === '') {
      return false;
    }

    return $this->container
      ->get(UploadRepository::class)
      ->existsByPathAndCategory($filePath, 'goods');
  }
  /**
   * HTML 본문에서 업로드 이미지 경로를 추출합니다.
   *
   * @param string $content 상품 상세 설명
   *
   * @return array
   */
  private function extractImagePaths(string $content): array
  {
    if ($content === '') {
      return [];
    }

    preg_match_all('/(?:src=["\'])([^"\']+)(?:["\'])/i', $content, $matches);

    return array_values(array_unique(array_filter(array_map(
      static function (string $src): string {
        $path = parse_url($src, PHP_URL_PATH);
        return is_string($path) ? $path : '';
      },
      $matches[1] ?? []
    ))));
  }

  /**
   * 빈 문자열을 null로 정규화하고 최대 길이를 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param string $field 필드명
   * @param int $maxLength 최대 길이
   *
   * @return string|null
   */
  private function nullableString(array $data, string $field, int $maxLength): ?string
  {
    $value = trim((string) ($data[$field] ?? ''));
    if ($value === '') {
      return null;
    }

    return mb_substr($value, 0, $maxLength);
  }

  /**
   * 관리자 URL을 생성합니다.
   *
   * @param string $path 관리자 하위 경로
   *
   * @return string
   */
  private function adminUrl(string $path): string
  {
    $settings = $this->container->get('settings');
    $adminDir = (string) ($settings['site']['admin_directory'] ?? 'dmmt');

    return '/' . trim($adminDir, '/') . $path;
  }

  /**
   * 실패 응답 배열을 생성합니다.
   *
   * @param string $message 오류 안내 문구
   * @param string $field 오류 필드명
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
