<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\Detail;

use App\Repositories\Common\BankRepository;
use App\Repositories\Admin\MemberAuditLogRepository;
use App\Services\BaseService;

class PageService extends BaseService
{
  private const ROLE_LABELS = [
    'ADMIN' => '관리자',
    'SELLER' => '판매사',
    'VENDOR' => '공급사',
  ];

  private const APPROVAL_STATUS_LABELS = [
    'APPROVED' => '승인',
    'PENDING' => '대기',
    'REJECTED' => '반려',
  ];

  private const STATUS_LABELS = [
    'ACTIVE' => '정상',
    'SUSPENDED' => '잠금',
    'WITHDRAWN' => '탈퇴',
  ];

  /**
   * 회원 상세 UI에 필요한 회원 데이터를 조회합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) {
      return $this->notFoundPage($id);
    }

    $member = $this->repo?->getMemberById($id);
    if ($member === null) {
      return $this->notFoundPage($id);
    }

    $auditRepo = $this->container->get(MemberAuditLogRepository::class);

    return [
      'member' => $this->formatMember($member),
      'banks' => $this->container->get(BankRepository::class)->getActiveBanks(),
      'admin_memos' => $this->formatAdminMemos($this->repo?->getAdminMemosByMemberId($id) ?? []),
      'audit_logs' => $this->formatAuditLogs($auditRepo->getLogsByMemberId($id)),
      'recent_activities' => [],
    ];
  }

  /**
   * 회원을 찾을 수 없을 때 상세 화면 안내 데이터를 생성합니다.
   *
   * @param int $id 요청 회원 ID
   *
   * @return array
   */
  private function notFoundPage(int $id): array
  {
    return [
      'member_not_found' => true,
      'not_found_message' => '회원을 찾을 수 없습니다. 목록에서 다시 선택해 주세요.',
      'member' => [
        'id' => $id,
        'mobile' => '',
        'company_phone' => '',
        'fax' => '',
        'business_number' => '',
      ],
      'banks' => [],
      'admin_memos' => [],
      'audit_logs' => [],
      'recent_activities' => [],
    ];
  }

  /**
   * 템플릿에서 사용하는 표시용 회원 데이터로 변환합니다.
   *
   * @param array $member 회원 데이터
   *
   * @return array
   */
  private function formatMember(array $member): array
  {
    $role = (string) ($member['role'] ?? '');
    $approvalStatus = (string) ($member['approval_status'] ?? '');
    $status = (string) ($member['status'] ?? '');
    $deposit = (int) ($member['deposit'] ?? 0);
    $mileage = (int) ($member['mileage'] ?? 0);
    $businessNumber = (string) ($member['business_number'] ?? '');
    $mobile = (string) ($member['mobile'] ?? '');
    $summaryContacts = array_values(array_filter([$businessNumber, $mobile], static fn (string $value): bool => $value !== ''));

    $member['role_label'] = self::ROLE_LABELS[$role] ?? $role;
    $member['approval_status_code'] = $approvalStatus;
    $member['approval_status'] = self::APPROVAL_STATUS_LABELS[$approvalStatus] ?? $approvalStatus;
    $member['status_code'] = $status;
    $member['status'] = self::STATUS_LABELS[$status] ?? $status;
    $member['zip_code'] = (string) ($member['zipcode'] ?? '');
    $member['deposit'] = number_format($deposit);
    $member['point'] = number_format($mileage);
    $member['summary_contact'] = $summaryContacts === [] ? '-' : implode(' / ', $summaryContacts);
    $member['joined_at'] = (string) ($member['created_at'] ?? '');
    $member['last_login_at'] = (string) ($member['last_login_at'] ?? '');
    $member['joined_at_display'] = $member['joined_at'] !== '' ? $member['joined_at'] : '-';
    $member['last_login_at_display'] = $member['last_login_at'] !== '' ? $member['last_login_at'] : '-';

    return $member;
  }
  /**
   * 관리자 메모 목록을 템플릿 출력용 데이터로 변환합니다.
   *
   * @param array $memos 관리자 메모 목록
   *
   * @return array
   */
  private function formatAdminMemos(array $memos): array
  {
    return array_map(static function (array $memo): array {
      return [
        'id' => (int) ($memo['id'] ?? 0),
        'member_id' => (int) ($memo['member_id'] ?? 0),
        'admin_name' => (string) ($memo['admin_name'] ?? ''),
        'memo_content' => (string) ($memo['memo_content'] ?? ''),
        'created_at' => (string) ($memo['created_at'] ?? ''),
      ];
    }, $memos);
  }

  /**
   * 수정 이력 로그를 템플릿 출력용 데이터로 변환합니다.
   *
   * @param array $logs 수정 이력 목록
   *
   * @return array
   */
  private function formatAuditLogs(array $logs): array
  {
    $formattedLogs = [];

    foreach ($logs as $log) {
      $changedData = json_decode((string) ($log['changed_data'] ?? ''), true);
      $changes = [];

      if (is_array($changedData)) {
        foreach ($changedData as $field => $change) {
          if (!is_array($change)) {
            continue;
          }

          $changes[] = [
            'field' => (string) $field,
            'old' => $this->formatAuditValue($change['old'] ?? null),
            'new' => $this->formatAuditValue($change['new'] ?? null),
          ];
        }
      }

      $formattedLogs[] = [
        'id' => (int) ($log['id'] ?? 0),
        'modifier_name' => (string) ($log['modifier_name'] ?? ''),
        'action_type' => (string) ($log['action_type'] ?? ''),
        'ip_address' => (string) ($log['ip_address'] ?? '-'),
        'created_at' => (string) ($log['created_at'] ?? ''),
        'changes' => $changes,
      ];
    }

    return $formattedLogs;
  }

  /**
   * 수정 이력 값을 화면에 표시할 문자열로 변환합니다.
   *
   * @param mixed $value 변경 전후 값
   *
   * @return string
   */
  private function formatAuditValue(mixed $value): string
  {
    if ($value === null || $value === '') {
      return '(없음)';
    }

    if (is_bool($value)) {
      return $value ? '1' : '0';
    }

    if (is_scalar($value)) {
      return (string) $value;
    }

    $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    return $encoded === false ? '' : $encoded;
  }
}