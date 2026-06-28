<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Repositories\BaseRepository;
use Throwable;

class SettingRepository extends BaseRepository
{
  /**
   * 설정 그룹의 키-값 목록을 조회합니다.
   *
   * @param string $group 설정 그룹명
   *
   * @return array
   */
  public function getGroupSettings(string $group): array
  {
    try {
      $rows = $this->db->fetchAll(
        'SELECT setting_key, setting_value, value_type
          FROM settings
          WHERE setting_group = ?',
        [$group]
      );
    } catch (Throwable) {
      // 설정 테이블 적용 전 초기 환경에서는 서비스 기본값을 사용합니다.
      return [];
    }

    $settings = [];
    foreach ($rows as $row) {
      $key = (string) ($row['setting_key'] ?? '');
      if ($key === '') {
        continue;
      }

      $settings[$key] = $this->castValue((string) ($row['setting_value'] ?? ''), (string) ($row['value_type'] ?? 'string'));
    }

    return $settings;
  }

  /**
   * 설정 그룹 값을 upsert로 저장합니다.
   *
   * @param string $group 설정 그룹명
   * @param array $settings 저장할 설정 목록
   * @param array $meta 설정 메타 정보
   *
   * @return void
   */
  public function upsertGroupSettings(string $group, array $settings, array $meta = []): void
  {
    foreach ($settings as $key => $value) {
      $info = is_array($meta[$key] ?? null) ? $meta[$key] : [];
      $valueType = (string) ($info['value_type'] ?? 'string');
      $description = isset($info['description']) ? (string) $info['description'] : null;

      $this->db->execute(
        'INSERT INTO settings (setting_group, setting_key, setting_value, value_type, description)
          VALUES (?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            description = VALUES(description),
            updated_at = CURRENT_TIMESTAMP',
        [$group, (string) $key, (string) $value, $valueType, $description]
      );
    }
  }

  /**
   * DB 문자열 값을 타입에 맞춰 변환합니다.
   *
   * @param string $value 원본 값
   * @param string $valueType 값 타입
   *
   * @return mixed
   */
  private function castValue(string $value, string $valueType): mixed
  {
    return match ($valueType) {
      'int' => (int) $value,
      'float' => (float) $value,
      'bool' => in_array($value, ['1', 'Y', 'true'], true),
      'json' => json_decode($value, true) ?? [],
      default => $value,
    };
  }
}
