<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\Memo;

use App\Repositories\Admin\MemberAuditLogRepository;
use App\Services\BaseService;
use Throwable;

class PostService extends BaseService
{
  /**
   * 관리자 메모를 등록합니다.
   *
   * @param array $params 요청 데이터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $memberId = (int) ($params['member_id'] ?? 0);
    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $adminName = trim((string) ($params['admin_name'] ?? '관리자')) ?: '관리자';
    $ipAddress = isset($params['ip_address']) ? (string) $params['ip_address'] : null;
    $memoContent = trim((string) ($data['memo_content'] ?? ''));

    if ($memberId <= 0) {
      return $this->fail('메모를 등록할 회원 정보가 올바르지 않습니다.', 'member_id');
    }

    if ($this->repo?->getMemberById($memberId) === null) {
      return $this->fail('회원 정보를 찾을 수 없습니다. 목록에서 다시 선택해 주세요.', 'member_id', 404);
    }

    if ($memoContent === '') {
      return $this->fail('관리자 메모 내용을 입력해 주세요.', 'memo_content');
    }

    if (mb_strlen($memoContent) > 5000) {
      return $this->fail('관리자 메모는 5,000자 이하로 입력해 주세요.', 'memo_content');
    }

    $this->db->beginTransaction();
    try {
      $memoId = $this->repo?->insertAdminMemo($memberId, $adminName, $memoContent) ?? 0;
      $auditRepo = $this->container->get(MemberAuditLogRepository::class);
      $auditRepo->insertLog($memberId, $adminName, 'MEMO_CREATE', [
        'admin_memo' => [
          'old' => null,
          'new' => $memoContent,
        ],
        'admin_name' => [
          'old' => null,
          'new' => $adminName,
        ],
      ], $ipAddress);
      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => '관리자 메모가 등록되었습니다.',
      'data' => [
        'id' => $memoId,
        'member_id' => $memberId,
      ],
    ];
  }

  /**
   * 실패 응답을 생성합니다.
   *
   * @param string $message 오류 메시지
   * @param string $field 오류 필드
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