<?php

declare(strict_types=1);

namespace App\Services\Admin\Auth;

use App\Services\BaseService;

class PostService extends BaseService
{
  private const MAX_LOGIN_FAIL_COUNT = 5;
  private const LOCK_MINUTES = 10;

  /**
   * 관리자 로그인 정보를 검증하고 세션을 생성한다.
   *
   * @param array $params 로그인 요청 데이터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $userId = trim((string) ($params['admin_id'] ?? ''));
    $password = (string) ($params['password'] ?? '');
    $rememberId = isset($params['remember_me']);

    if ($userId === '') {
      return $this->fail('아이디를 입력해 주세요.', 'admin_id');
    }

    if ($password === '') {
      return $this->fail('비밀번호를 입력해 주세요.', 'password');
    }

    $admin = $this->repo?->findAdminByUserId($userId);
    if ($admin === null) {
      return $this->fail('아이디 또는 비밀번호가 올바르지 않습니다.', 'admin_id');
    }

    if ((string) $admin['status'] !== 'ACTIVE') {
      return $this->fail('사용할 수 없는 관리자 계정입니다.', 'admin_id');
    }

    if ($this->isLocked($admin)) {
      return $this->fail('로그인 실패 횟수가 초과되어 계정이 잠시 잠겼습니다. 잠시 후 다시 시도해 주세요.', 'password', 423);
    }

    if (!password_verify($password, (string) $admin['password'])) {
      $this->handleLoginFailure($admin);

      return $this->fail('아이디 또는 비밀번호가 올바르지 않습니다.', 'password');
    }

    $this->repo?->resetLoginFailState((int) $admin['id']);
    $this->repo?->updateLastLoginAt((int) $admin['id']);
    $this->setAdminSession($admin);

    return [
      'success' => true,
      'saved_id' => $userId,
      'remember_id' => $rememberId,
    ];
  }

  /**
   * 실패 응답 배열을 만든다.
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

  /**
   * 계정 잠금 상태를 확인한다.
   *
   * @param array $admin 관리자 데이터
   *
   * @return bool
   */
  private function isLocked(array $admin): bool
  {
    $lockedUntil = (string) ($admin['locked_until'] ?? '');
    if ($lockedUntil === '') {
      return false;
    }

    $lockedTimestamp = strtotime($lockedUntil);

    return $lockedTimestamp !== false && $lockedTimestamp > time();
  }

  /**
   * 로그인 실패 횟수를 누적하고 기준 초과 시 계정을 잠근다.
   *
   * @param array $admin 관리자 데이터
   *
   * @return void
   */
  private function handleLoginFailure(array $admin): void
  {
    $adminId = (int) $admin['id'];
    $nextFailCount = (int) ($admin['login_fail_count'] ?? 0) + 1;

    if ($nextFailCount >= self::MAX_LOGIN_FAIL_COUNT) {
      $lockedUntil = date('Y-m-d H:i:s', time() + self::LOCK_MINUTES * 60);
      $this->repo?->lockAdmin($adminId, $nextFailCount, $lockedUntil);

      return;
    }

    $this->repo?->increaseLoginFailCount($adminId);
  }

  /**
   * 관리자 세션 배열을 저장한다.
   *
   * @param array $admin 관리자 데이터
   *
   * @return void
   */
  private function setAdminSession(array $admin): void
  {
    $this->container->get('session')->set('admin', [
      'id' => (int) $admin['id'],
      'user_id' => (string) $admin['user_id'],
      'name' => (string) $admin['name'],
      'role' => 'ADMIN',
      'level_id' => isset($admin['level_id']) ? (int) $admin['level_id'] : null,
      'permissions' => [],
      'login_at' => date('Y-m-d H:i:s'),
    ]);
  }
}