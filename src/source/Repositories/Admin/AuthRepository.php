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
   * 로그인 실패 횟수를 1 증가시킨다.
   *
   * @param int $adminId 관리자 ID
   *
   * @return bool
   */
  public function increaseLoginFailCount(int $adminId): bool
  {
    return $this->db->execute(
      'UPDATE members SET login_fail_count = login_fail_count + 1 WHERE id = ?',
      [$adminId]
    );
  }

  /**
   * 로그인 실패 횟수를 저장하고 계정을 잠근다.
   *
   * @param int $adminId 관리자 ID
   * @param int $failCount 실패 횟수
   * @param string $lockedUntil 잠금 해제 시간
   *
   * @return bool
   */
  public function lockAdmin(int $adminId, int $failCount, string $lockedUntil): bool
  {
    return $this->db->execute(
      'UPDATE members SET login_fail_count = ?, locked_until = ? WHERE id = ?',
      [$failCount, $lockedUntil, $adminId]
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