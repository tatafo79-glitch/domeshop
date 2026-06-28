<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Repositories\BaseRepository;

class PlatformFeeRepository extends BaseRepository
{
  /**
   * 삭제되지 않은 플랫폼 수수료 설정 목록을 조회합니다.
   *
   * @return array
   */
  public function getPlatformFees(): array
  {
    return $this->db->fetchAll(
      'SELECT id, platform_name, platform_code, platform_fee_rate, shipping_fee_rate,
          instant_discount_rate, additional_discount_rate, additional_fixed_discount,
          is_default, memo, sort_order
        FROM platform_fee_settings
        WHERE deleted_at IS NULL
        ORDER BY is_default DESC, sort_order ASC, id ASC'
    );
  }

  /**
   * 플랫폼 수수료 설정 단건을 조회합니다.
   *
   * @param int $id 플랫폼 수수료 설정 ID
   *
   * @return array|null
   */
  public function getPlatformFeeById(int $id): ?array
  {
    return $this->db->fetchRow(
      'SELECT id, platform_name, platform_code, platform_fee_rate, shipping_fee_rate,
          instant_discount_rate, additional_discount_rate, additional_fixed_discount,
          is_default, memo, sort_order
        FROM platform_fee_settings
        WHERE id = ? AND deleted_at IS NULL',
      [$id]
    );
  }

  /**
   * 플랫폼 코드 중복 여부를 조회합니다.
   *
   * @param string $platformCode 플랫폼 코드
   * @param int|null $excludeId 수정 시 제외할 ID
   *
   * @return bool
   */
  public function existsPlatformCode(string $platformCode, ?int $excludeId = null): bool
  {
    if ($excludeId !== null && $excludeId > 0) {
      $count = $this->db->fetchRow(
        'SELECT COUNT(*) AS cnt
          FROM platform_fee_settings
          WHERE platform_code = ? AND id <> ? AND deleted_at IS NULL',
        [$platformCode, $excludeId]
      );
    } else {
      $count = $this->db->fetchRow(
        'SELECT COUNT(*) AS cnt
          FROM platform_fee_settings
          WHERE platform_code = ? AND deleted_at IS NULL',
        [$platformCode]
      );
    }

    return (int) ($count['cnt'] ?? 0) > 0;
  }

  /**
   * 플랫폼 수수료 설정을 등록합니다.
   *
   * @param array $data 저장할 데이터
   *
   * @return int
   */
  public function insertPlatformFee(array $data): int
  {
    $this->releaseDeletedPlatformCode((string) $data['platform_code']);

    $this->db->execute(
      'INSERT INTO platform_fee_settings (
          platform_name, platform_code, platform_fee_rate, shipping_fee_rate,
          instant_discount_rate, additional_discount_rate, additional_fixed_discount,
          is_default, memo, sort_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [
        $data['platform_name'],
        $data['platform_code'],
        $data['platform_fee_rate'],
        $data['shipping_fee_rate'],
        $data['instant_discount_rate'],
        $data['additional_discount_rate'],
        $data['additional_fixed_discount'],
        $data['is_default'],
        $data['memo'],
        $data['sort_order'],
      ]
    );

    return (int) $this->db->lastInsertId();
  }

  /**
   * 플랫폼 수수료 설정을 수정합니다.
   *
   * @param int $id 플랫폼 수수료 설정 ID
   * @param array $data 저장할 데이터
   *
   * @return bool
   */
  public function updatePlatformFee(int $id, array $data): bool
  {
    return $this->db->execute(
      'UPDATE platform_fee_settings
        SET platform_name = ?,
            platform_code = ?,
            platform_fee_rate = ?,
            shipping_fee_rate = ?,
            instant_discount_rate = ?,
            additional_discount_rate = ?,
            additional_fixed_discount = ?,
            is_default = ?,
            memo = ?,
            sort_order = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND deleted_at IS NULL',
      [
        $data['platform_name'],
        $data['platform_code'],
        $data['platform_fee_rate'],
        $data['shipping_fee_rate'],
        $data['instant_discount_rate'],
        $data['additional_discount_rate'],
        $data['additional_fixed_discount'],
        $data['is_default'],
        $data['memo'],
        $data['sort_order'],
        $id,
      ]
    );
  }

  /**
   * 모든 플랫폼의 기본 여부를 해제합니다.
   *
   * @return bool
   */
  public function clearDefaultPlatformFee(): bool
  {
    return $this->db->execute(
      "UPDATE platform_fee_settings
        SET is_default = 'N', updated_at = CURRENT_TIMESTAMP
        WHERE deleted_at IS NULL"
    );
  }

  /**
   * 플랫폼 수수료 설정을 소프트 삭제합니다.
   *
   * @param int $id 플랫폼 수수료 설정 ID
   *
   * @return bool
   */
  public function softDeletePlatformFee(int $id): bool
  {
    return $this->db->execute(
      'UPDATE platform_fee_settings
        SET deleted_at = CURRENT_TIMESTAMP,
            is_default = \'N\',
            platform_code = CONCAT(LEFT(platform_code, 30), \'__deleted_\', id),
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND deleted_at IS NULL',
      [$id]
    );
  }

  /**
   * 다음 정렬 순서를 조회합니다.
   *
   * @return int
   */
  public function getNextSortOrder(): int
  {
    $row = $this->db->fetchRow(
      'SELECT COALESCE(MAX(sort_order), 0) + 10 AS next_sort_order
        FROM platform_fee_settings
        WHERE deleted_at IS NULL'
    );

    return (int) ($row['next_sort_order'] ?? 10);
  }

  /**
   * 삭제된 플랫폼의 코드를 해제해 같은 코드 재등록이 가능하게 만듭니다.
   *
   * @param string $platformCode 재사용할 플랫폼 코드
   *
   * @return bool
   */
  public function releaseDeletedPlatformCode(string $platformCode): bool
  {
    return $this->db->execute(
      'UPDATE platform_fee_settings
        SET platform_code = CONCAT(LEFT(platform_code, 30), \'__deleted_\', id),
            updated_at = CURRENT_TIMESTAMP
        WHERE platform_code = ? AND deleted_at IS NOT NULL',
      [$platformCode]
    );
  }
}
