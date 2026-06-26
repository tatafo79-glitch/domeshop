<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Repositories\BaseRepository;

class AuthRepository extends BaseRepository
{
  /**
   * 로그인 아이디로 관리자 후보를 조회한다.
   *
   * @param string $userId 로그인 아이디
   *
   * @return array|null
   */
  public function findAdminByUserId(string $userId): ?array
  {
    return $this->db->fetchRow(
      'SELECT id, user_id, password, role, name, status, level_id, login_fail_count, locked_until FROM members WHERE user_id = ? AND role = ? AND status = ? LIMIT 1',
      [$userId, 'ADMIN', 'ACTIVE']
    );
  }

  /**
   * 로그인 실패 횟수를 원자적으로 누적하고 기준 초과 시 계정을 잠근다.
   *
   * @param int $adminId 관리자 ID
   * @param int $maxFailCount 잠금 기준 실패 횟수
   * @param string $lockedUntil 잠금 해제 시간
   *
   * @return bool
   */
  public function recordLoginFailure(int $adminId, int $maxFailCount, string $lockedUntil): bool
  {
    return $this->db->execute(
      'UPDATE members
       SET locked_until = CASE
             WHEN login_fail_count + 1 >= ? THEN ?
             ELSE locked_until
           END,
           login_fail_count = LEAST(login_fail_count + 1, ?)
       WHERE id = ?',
      [$maxFailCount, $lockedUntil, 255, $adminId]
    );
  }

  /**
   * 로그인 실패 상태를 초기화한다.
   *
   * @param int $adminId 관리자 ID
   *
   * @return bool
   */
  public function resetLoginFailState(int $adminId): bool
  {
    return $this->db->execute(
      'UPDATE members SET login_fail_count = 0, locked_until = NULL WHERE id = ?',
      [$adminId]
    );
  }

  /**
   * 마지막 로그인 시각을 갱신한다.
   *
   * @param int $adminId 관리자 ID
   *
   * @return bool
   */
  public function updateLastLoginAt(int $adminId): bool
  {
    return $this->db->execute(
      'UPDATE members SET last_login_at = NOW() WHERE id = ?',
      [$adminId]
    );
  }
}