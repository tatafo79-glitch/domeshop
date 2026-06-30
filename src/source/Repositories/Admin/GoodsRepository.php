<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Repositories\BaseRepository;
use Throwable;

class GoodsRepository extends BaseRepository
{
  /**
   * 상품 등록 카테고리 선택에 사용할 활성 카테고리 전체 목록을 조회합니다.
   *
   * @return array
   */
  public function getActiveCategories(): array
  {
    try {
      return $this->db->fetchAll(
        'SELECT id, parent_id, name, path, depth, is_leaf, sort_order
          FROM categories
          WHERE is_active = ?
          ORDER BY depth ASC, parent_id ASC, sort_order ASC, id ASC',
        ['Y']
      );
    } catch (Throwable) {
      // 상품 스키마 적용 전에도 등록 화면 자체는 열리도록 빈 목록을 반환합니다.
      return [];
    }
  }
  /**
   * 상품 등록에 사용할 활성 최하위 카테고리 목록을 조회합니다.
   *
   * @return array
   */
  public function getActiveLeafCategories(): array
  {
    try {
      $rows = $this->db->fetchAll(
        'SELECT id, name, path, depth
          FROM categories
          WHERE is_active = ? AND is_leaf = ?
          ORDER BY path ASC, sort_order ASC, id ASC',
        ['Y', 'Y']
      );
    } catch (Throwable) {
      // 상품 스키마 적용 전에도 등록 화면 자체는 열리도록 빈 목록을 반환합니다.
      return [];
    }

    return array_map(
      static function (array $row): array {
        $depth = max(1, (int) ($row['depth'] ?? 1));
        $row['depth_label'] = str_repeat('ㄴ ', max(0, $depth - 1)) . (string) ($row['name'] ?? '');

        return $row;
      },
      $rows
    );
  }

  /**
   * 상품 공급사로 선택 가능한 승인 공급사 목록을 조회합니다.
   *
   * @return array
   */
  public function getApprovedVendors(): array
  {
    try {
      return $this->db->fetchAll(
        'SELECT id, vendor_code, company_name, user_id, name
          FROM members
          WHERE role = ? AND status = ? AND approval_status = ?
          ORDER BY company_name ASC, id ASC',
        ['VENDOR', 'ACTIVE', 'APPROVED']
      );
    } catch (Throwable) {
      // 회원 스키마 적용 전 또는 개발 DB 초기 상태에서는 관리자 직영만 선택할 수 있게 둡니다.
      return [];
    }
  }

  /**
   * 상품 등록 원산지 선택에 사용할 원산지 목록을 조회합니다.
   *
   * @return array
   */
  public function getGoodsOrigins(): array
  {
    try {
      return $this->db->fetchAll(
        'SELECT id, nm, cd0, cd1, pathnm0, pathnm1, level, sort, last
          FROM goods_origins
          ORDER BY level ASC, cd0 ASC, cd1 ASC, sort ASC, id ASC',
        []
      );
    } catch (Throwable) {
      // 원산지 스키마 적용 전에도 상품 등록 화면 자체는 열리도록 빈 목록을 반환합니다.
      return [];
    }
  }

  /**
   * 상품 원산지를 ID로 조회합니다.
   *
   * @param int $id 원산지 ID
   *
   * @return array|null
   */
  public function getGoodsOriginById(int $id): ?array
  {
    return $this->db->fetchRow(
      'SELECT id, nm, cd0, cd1, pathnm0, pathnm1, level, sort, last
        FROM goods_origins
        WHERE id = ?
        LIMIT 1',
      [$id]
    );
  }

  /**
   * 0단계 루트 행이 없는 원산지 데이터에서 루트 정보를 조회합니다.
   *
   * @param int $rootCode 원산지 루트 코드
   *
   * @return array|null
   */
  public function getGoodsOriginRootByCode(int $rootCode): ?array
  {
    return $this->db->fetchRow(
      'SELECT cd0 AS id, pathnm0 AS nm, cd0, NULL AS cd1, pathnm0, NULL AS pathnm1, 0 AS level, MIN(sort) AS sort, ? AS last
        FROM goods_origins
        WHERE cd0 = ? AND pathnm0 <> ?
        GROUP BY cd0, pathnm0
        LIMIT 1',
      ['N', $rootCode, '']
    );
  }

  /**
   * 활성 카테고리를 ID로 조회합니다.
   *
   * @param int $id 카테고리 ID
   *
   * @return array|null
   */
  public function getActiveCategoryById(int $id): ?array
  {
    return $this->db->fetchRow(
      'SELECT id, path FROM categories WHERE id = ? AND is_active = ? LIMIT 1',
      [$id, 'Y']
    );
  }

  /**
   * 승인된 공급사 코드가 존재하는지 확인합니다.
   *
   * @param string $vendorCode 공급사 코드
   *
   * @return bool
   */
  public function approvedVendorCodeExists(string $vendorCode): bool
  {
    $row = $this->db->fetchRow(
      'SELECT id FROM members WHERE vendor_code = ? AND role = ? AND status = ? AND approval_status = ? LIMIT 1',
      [$vendorCode, 'VENDOR', 'ACTIVE', 'APPROVED']
    );

    return $row !== null;
  }

  /**
   * 상품 기본 정보를 등록합니다.
   *
   * @param array $data 상품 데이터
   *
   * @return int
   */
  public function insertGoods(array $data): int
  {
    $columns = [
      'vendor_code',
      'vendor_goods_code',
      'category_id',
      'category_path',
      'origin',
      'manufacturer',
      'brand',
      'name',
      'invoice_name',
      'goods_type',
      'goods_status',
      'adult_only',
      'search_keywords',
      'search_text',
      'tax_type',
      'supply_price',
      'sell_price',
      'sell_price_fixed',
      'price_policy',
      'compliance_price',
      'shipping_type',
      'shipping_fee',
      'actual_shipping_fee',
      'shipping_qty_limit',
      'has_extra_shipping',
      'extra_shipping_jeju',
      'extra_shipping_island',
      'return_shipping_fee',
      'exchange_shipping_fee',
      'stock',
      'stock_link',
      'soldout',
      'is_display',
      'is_exportable',
      'has_option',
      'has_text_option',
      'option_title1',
      'option_title2',
      'option_title3',
      'has_soldout_option',
      'thumbnail_url',
      'content',
    ];

    $placeholders = [];
    $params = [];
    foreach ($columns as $column) {
      $placeholders[] = '?';
      $params[] = $data[$column] ?? null;
    }

    $this->db->execute(
      'INSERT INTO goods (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
      $params
    );

    return (int) $this->db->lastInsertId();
  }

  /**
   * 상품 이미지를 등록합니다.
   *
   * @param array $data 이미지 데이터
   *
   * @return int
   */
  public function insertGoodsImage(array $data): int
  {
    $this->db->execute(
      'INSERT INTO goods_images (goods_id, file_path, image_type, is_main, sort_order) VALUES (?, ?, ?, ?, ?)',
      [
        (int) ($data['goods_id'] ?? 0),
        (string) ($data['file_path'] ?? ''),
        (string) ($data['image_type'] ?? 'LIST'),
        (string) ($data['is_main'] ?? 'N'),
        (int) ($data['sort_order'] ?? 0),
      ]
    );

    return (int) $this->db->lastInsertId();
  }

  /**
   * 상품 조합 옵션을 등록합니다.
   *
   * @param array $data 옵션 데이터
   *
   * @return int
   */
  public function insertGoodsOption(array $data): int
  {
    $this->db->execute(
      'INSERT INTO goods_options (goods_id, option_val1, option_val2, option_val3, option_supply_price, option_sell_price, option_compliance_price, stock, soldout, is_display) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [
        (int) ($data['goods_id'] ?? 0),
        (string) ($data['option_val1'] ?? ''),
        $data['option_val2'] ?? null,
        $data['option_val3'] ?? null,
        (int) ($data['option_supply_price'] ?? 0),
        (int) ($data['option_sell_price'] ?? 0),
        (int) ($data['option_compliance_price'] ?? 0),
        (int) ($data['stock'] ?? 0),
        (int) ($data['soldout'] ?? 0),
        (string) ($data['is_display'] ?? 'Y'),
      ]
    );

    return (int) $this->db->lastInsertId();
  }

  /**
   * 상품 텍스트 입력 옵션을 등록합니다.
   *
   * @param array $data 텍스트 옵션 데이터
   *
   * @return int
   */
  public function insertGoodsTextOption(array $data): int
  {
    $this->db->execute(
      'INSERT INTO goods_text_options (goods_id, title, is_required, max_length, is_display) VALUES (?, ?, ?, ?, ?)',
      [
        (int) ($data['goods_id'] ?? 0),
        (string) ($data['title'] ?? ''),
        (string) ($data['is_required'] ?? 'N'),
        (int) ($data['max_length'] ?? 50),
        (string) ($data['is_display'] ?? 'Y'),
      ]
    );

    return (int) $this->db->lastInsertId();
  }
}

