<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Repositories\BaseRepository;

class MemberAuditLogRepository extends BaseRepository
{
  /**
   * 회원별 수정 이력 목록을 최신순으로 조회합니다.
   *
   * @param int $memberId 조회할 회원 ID
   * @param int $limit 조회 개수
   *
   * @return array
   */
  public function getLogsByMemberId(int $memberId, int $limit = 20): array
  {
    return $this->db->fetchAll(
      'SELECT id, member_id, modifier_name, action_type, changed_data, ip_address, created_at
        FROM member_audit_logs
        WHERE member_id = ?
        ORDER BY id DESC
        LIMIT ?',
      [$memberId, $limit]
    );
  }

  /**
   * 회원 정보 변경 이력을 기록합니다.
   *
   * @param int $memberId 대상 회원 ID
   * @param string $modifierName 수정자 이름
   * @param string $actionType 액션 유형
   * @param array $changedData 변경 전후 데이터
   * @param string|null $ipAddress 요청 IP
   *
   * @return bool
   */
  public function insertLog(
    int $memberId,
    string $modifierName,
    string $actionType,
    array $changedData,
    ?string $ipAddress
  ): bool {
    return $this->db->execute(
      'INSERT INTO member_audit_logs (member_id, modifier_name, action_type, changed_data, ip_address)
        VALUES (?, ?, ?, ?, ?)',
      [
        $memberId,
        $modifierName,
        $actionType,
        json_encode($changedData, JSON_UNESCAPED_UNICODE),
        $ipAddress,
      ]
    );
  }
}