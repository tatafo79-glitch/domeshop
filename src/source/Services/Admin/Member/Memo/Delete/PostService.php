<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\Memo\Delete;

use App\Repositories\Admin\MemberAuditLogRepository;
use App\Services\BaseService;
use Throwable;

class PostService extends BaseService
{
  /**
   * 관리자 메모를 삭제합니다.
   *
   * @param array $params 요청 데이터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $memoId = (int) ($params['memo_id'] ?? 0);
    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $memberId = (int) ($data['member_id'] ?? 0);
    $adminName = trim((string) ($params['admin_name'] ?? '관리자')) ?: '관리자';
    $ipAddress = isset($params['ip_address']) ? (string) $params['ip_address'] : null;

    if ($memoId <= 0) {
      return $this->fail('삭제할 메모 정보가 올바르지 않습니다.', 'id');
    }

    if ($memberId <= 0) {
      return $this->fail('회원 정보가 올바르지 않습니다.', 'member_id');
    }

    $memo = $this->repo?->getAdminMemoById($memoId);
    if ($memo === null || (int) ($memo['member_id'] ?? 0) !== $memberId) {
      return $this->fail('삭제할 메모를 찾을 수 없습니다.', 'id', 404);
    }

    $memoContent = (string) ($memo['memo_content'] ?? '');

    $this->db->beginTransaction();
    try {
      $this->repo?->deleteAdminMemo($memoId, $memberId);
      $auditRepo = $this->container->get(MemberAuditLogRepository::class);
      $auditRepo->insertLog($memberId, $adminName, 'MEMO_DELETE', [
        'admin_memo' => [
          'old' => $memoContent,
          'new' => null,
        ],
        'memo_id' => [
          'old' => $memoId,
          'new' => null,
        ],
      ], $ipAddress);
      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => '관리자 메모가 삭제되었습니다.',
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