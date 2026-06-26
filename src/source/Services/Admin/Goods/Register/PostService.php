<?php

declare(strict_types=1);

namespace App\Services\Admin\Goods\Register;

use App\Repositories\Common\UploadRepository;
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

    $origin = trim((string) ($data['origin'] ?? ''));
    if ($origin === '') {
      return $this->fail('원산지를 선택해 주세요.', 'originDepth1');
    }

    $goodsType = trim((string) ($data['goods_type'] ?? ''));
    if ($goodsType === '') {
      $goodsType = 'NORMAL';
    }
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
    if ((int) $sellPriceResult['value'] < (int) $supplyPriceResult['value']) {
      return $this->fail('판매가는 공급가보다 작을 수 없습니다.', 'sell_price');
    }

    $sellPriceFixedResult = $this->normalizeFlag($data, 'sell_price_fixed', '판매가 고정 여부를 올바르게 선택해 주세요.', 'sell_price_fixed');
    if (($sellPriceFixedResult['success'] ?? false) !== true) {
      return $sellPriceFixedResult;
    }

    $pricePolicyResult = $this->normalizeEnum($data, 'price_policy', self::PRICE_POLICIES, '가격 정책을 올바르게 선택해 주세요.', 'price_policy');
    if (($pricePolicyResult['success'] ?? false) !== true) {
      return $pricePolicyResult;
    }

    $compliancePriceResult = $this->normalizeRequiredInt($data, 'compliance_price', '준수가격은 0 이상의 숫자로 입력해 주세요.', 'compliance_price');
    if (($compliancePriceResult['success'] ?? false) !== true) {
      return $compliancePriceResult;
    }
    if ($pricePolicyResult['value'] === 'COMPLY' && (int) $compliancePriceResult['value'] < 1) {
      return $this->fail('가격준수 선택 시 준수가격은 1 이상 입력해 주세요.', 'compliance_price');
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

    $optionsResult = $this->normalizeOptions($data, $hasOptionResult['value'] === 'Y');
    if (($optionsResult['success'] ?? false) !== true) {
      return $optionsResult;
    }

    $textOptionsResult = $this->normalizeTextOptions($data, $hasTextOptionResult['value'] === 'Y');
    if (($textOptionsResult['success'] ?? false) !== true) {
      return $textOptionsResult;
    }

    $shippingResult = $this->normalizeShipping($data);
    if (($shippingResult['success'] ?? false) !== true) {
      return $shippingResult;
    }

    $images = $this->normalizeImages($data);
    $content = (string) ($data['content'] ?? '');
    $goodsData = array_merge([
      'vendor_code' => $vendorCode,
      'vendor_goods_code' => $vendorGoodsCode === '' ? null : $vendorGoodsCode,
      'category_id' => $categoryId,
      'category_path' => (string) ($category['path'] ?? ''),
      'origin' => $origin,
      'manufacturer' => $this->nullableString($data, 'manufacturer', 100),
      'brand' => $this->nullableString($data, 'brand', 100),
      'name' => $name,
      'invoice_name' => $this->nullableString($data, 'invoice_name', 255),
      'goods_type' => $goodsType,
      'goods_status' => $goodsStatusResult['value'],
      'adult_only' => $adultOnlyResult['value'],
      'search_keywords' => $keywordsResult['value'],
      'search_text' => trim($name . ' ' . (string) $keywordsResult['value']),
      'tax_type' => $taxTypeResult['value'],
      'supply_price' => (int) $supplyPriceResult['value'],
      'sell_price' => (int) $sellPriceResult['value'],
      'sell_price_fixed' => $sellPriceFixedResult['value'],
      'price_policy' => $pricePolicyResult['value'],
      'compliance_price' => (int) $compliancePriceResult['value'],
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
      'shipping_fee' => '기본 배송비는 0 이상의 숫자로 입력해 주세요.',
      'shipping_qty_limit' => '합포장 기준 수량은 1 이상의 숫자로 입력해 주세요.',
      'extra_shipping_jeju' => '제주 추가 배송비는 0 이상의 숫자로 입력해 주세요.',
      'extra_shipping_island' => '도서산간 추가 배송비는 0 이상의 숫자로 입력해 주세요.',
      'return_shipping_fee' => '반품 배송비는 0 이상의 숫자로 입력해 주세요.',
      'exchange_shipping_fee' => '교환 배송비는 0 이상의 숫자로 입력해 주세요.',
    ];

    $values = [];
    foreach ($fields as $field => $message) {
      $min = $field === 'shipping_qty_limit' ? 1 : 0;
      $result = $this->normalizeRequiredInt($data, $field, $message, $field, $min);
      if (($result['success'] ?? false) !== true) {
        return $result;
      }
      $values[$field] = (int) $result['value'];
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
   *
   * @return array
   */
  private function normalizeOptions(array $data, bool $enabled): array
  {
    $rows = is_array($data['options'] ?? null) ? $data['options'] : [];
    if ($enabled && count($rows) === 0) {
      return $this->fail('옵션 분류명 및 옵션 항목을 먼저 적용해 주세요.', 'addOptionBtn');
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
        'option_val2' => trim((string) ($row['option_val2'] ?? '')) ?: null,
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
   *
   * @return array
   */
  private function normalizeTextOptions(array $data, bool $enabled): array
  {
    $rows = is_array($data['text_options'] ?? null) ? $data['text_options'] : [];
    if ($enabled && count($rows) === 0) {
      return $this->fail('입력옵션을 추가해 주세요.', 'addTextOptionBtn');
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

      $isRequired = (string) ($row['is_required'] ?? '');
      if (!in_array($isRequired, self::YES_NO_VALUES, true)) {
        return $this->fail('입력옵션 필수 여부를 올바르게 선택해 주세요.', 'addTextOptionBtn');
      }

      $isDisplay = (string) ($row['is_display'] ?? '');
      if (!in_array($isDisplay, self::YES_NO_VALUES, true)) {
        return $this->fail('입력옵션 상태를 올바르게 선택해 주세요.', 'addTextOptionBtn');
      }

      $maxLength = trim((string) ($row['max_length'] ?? ''));
      if ($maxLength === '' || preg_match('/^\d+$/', $maxLength) !== 1 || (int) $maxLength < 1) {
        return $this->fail('입력옵션 글자수 제한은 1 이상 입력해 주세요.', 'addTextOptionBtn');
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
   *
   * @return array
   */
  private function normalizeImages(array $data): array
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

    $images = [];
    foreach ($paths as $index => $path) {
      $images[] = [
        'file_path' => $path,
        'image_type' => 'LIST',
        'is_main' => $index === 0 ? 'Y' : 'N',
        'sort_order' => $index,
      ];
    }

    return $images;
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
        $uploadRepo->markAsUsed($path);
      }
    }

    foreach ($this->extractImagePaths($content) as $path) {
      $uploadRepo->markAsUsed($path);
    }
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
