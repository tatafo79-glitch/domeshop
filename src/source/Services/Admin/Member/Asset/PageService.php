<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\Asset;

use App\Services\BaseService;
use RuntimeException;

class PageService extends BaseService
{
  private const ASSET_MAP = [
    'deposit' => ['type' => 'DEPOSIT', 'column' => 'deposit', 'label' => '적립금', 'unit' => '원'],
    'point' => ['type' => 'POINT', 'column' => 'mileage', 'label' => '포인트', 'unit' => 'P'],
  ];

  private const ROLE_LABELS = [
    'ADMIN' => '관리자',
    'SELLER' => '판매사',
    'VENDOR' => '공급사',
  ];

  private const DEFAULT_LIMIT = 5;

  private const MAX_PAGE_LINKS = 5;

  /**
   * 회원 자산 관리 화면 데이터를 조회합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $memberId = (int) ($params['id'] ?? 0);
    $assetKey = (string) ($params['asset'] ?? '');
    $asset = self::ASSET_MAP[$assetKey] ?? null;

    if ($memberId <= 0 || $asset === null) {
      throw new RuntimeException('회원 자산 정보가 올바르지 않습니다.');
    }

    $member = $this->repo?->getMemberById($memberId);
    if ($member === null) {
      return $this->blockedAssetPage($memberId, $assetKey, $asset, '회원을 찾을 수 없습니다. 목록에서 다시 선택해 주세요.');
    }

    if ((string) ($member['role'] ?? '') === 'ADMIN') {
      return $this->blockedAssetPage($memberId, $assetKey, $asset, '관리자 계정은 적립금/포인트 관리 대상이 아닙니다.', $member);
    }

    $balance = (int) ($member[$asset['column']] ?? 0);
    $query = is_array($params['query'] ?? null) ? $params['query'] : [];
    $page = $this->normalizePage($query['page'] ?? null);
    $total = (int) ($this->repo?->countMemberAssetHistories($memberId, $asset['type']) ?? 0);
    $totalPages = max(1, (int) ceil($total / self::DEFAULT_LIMIT));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * self::DEFAULT_LIMIT;
    $histories = $this->repo?->getMemberAssetHistories($memberId, $asset['type'], self::DEFAULT_LIMIT, $offset) ?? [];

    return [
      'member' => [
        'id' => $memberId,
        'user_id' => (string) ($member['user_id'] ?? ''),
        'company_name' => (string) ($member['company_name'] ?? ''),
        'name' => (string) ($member['name'] ?? ''),
        'role' => (string) ($member['role'] ?? ''),
        'role_label' => self::ROLE_LABELS[(string) ($member['role'] ?? '')] ?? (string) ($member['role'] ?? ''),
      ],
      'asset' => [
        'key' => $assetKey,
        'type' => $asset['type'],
        'label' => $asset['label'],
        'unit' => $asset['unit'],
        'balance' => $balance,
        'formatted_balance' => number_format($balance),
      ],
      'histories' => $this->formatHistories($histories),
      'pagination' => $this->makePagination($page, self::DEFAULT_LIMIT, $total, $totalPages),
    ];
  }

  /**
   * 자산 관리 불가 안내 화면 데이터를 생성합니다.
   *
   * @param int $memberId 회원 ID
   * @param string $assetKey 자산 키
   * @param array $asset 자산 설정
   * @param string $message 안내 문구
   * @param array|null $member 회원 데이터
   *
   * @return array
   */
  private function blockedAssetPage(int $memberId, string $assetKey, array $asset, string $message, ?array $member = null): array
  {
    $role = (string) ($member['role'] ?? '');

    return [
      'is_blocked' => true,
      'blocked_message' => $message,
      'member' => [
        'id' => $memberId,
        'user_id' => (string) ($member['user_id'] ?? '-'),
        'company_name' => (string) ($member['company_name'] ?? ''),
        'name' => (string) ($member['name'] ?? ''),
        'role' => $role,
        'role_label' => $role !== '' ? (self::ROLE_LABELS[$role] ?? $role) : '회원 없음',
      ],
      'asset' => [
        'key' => $assetKey,
        'type' => $asset['type'],
        'label' => $asset['label'],
        'unit' => $asset['unit'],
        'balance' => 0,
        'formatted_balance' => '0',
      ],
      'histories' => [],
      'pagination' => $this->makePagination(1, self::DEFAULT_LIMIT, 0, 1),
    ];
  }

  /**
   * 자산 이력을 화면 표시용 데이터로 변환합니다.
   *
   * @param array $histories 자산 이력 목록
   *
   * @return array
   */
  private function formatHistories(array $histories): array
  {
    return array_map(static function (array $history): array {
      $changeAmount = (int) ($history['change_amount'] ?? 0);
      $balanceAfter = (int) ($history['balance_after'] ?? 0);

      return [
        'id' => (int) ($history['id'] ?? 0),
        'reason' => (string) ($history['reason'] ?? ''),
        'order_no' => (string) ($history['order_no'] ?? ''),
        'change_amount' => $changeAmount,
        'formatted_change_amount' => ($changeAmount > 0 ? '+' : '') . number_format($changeAmount),
        'balance_after' => $balanceAfter,
        'formatted_balance_after' => number_format($balanceAfter),
        'actor_name' => (string) ($history['actor_name'] ?? ''),
        'created_date' => ($history['created_at'] ?? '') !== '' ? date('Y-m-d', strtotime((string) $history['created_at'])) : '-',
        'created_time' => ($history['created_at'] ?? '') !== '' ? date('H:i', strtotime((string) $history['created_at'])) : '',
      ];
    }, $histories);
  }

  /**
   * 페이지 번호를 안전한 양수로 정리합니다.
   *
   * @param mixed $page 요청 페이지 값
   *
   * @return int
   */
  private function normalizePage(mixed $page): int
  {
    if (!is_scalar($page) || !preg_match('/^[0-9]+$/', (string) $page)) {
      return 1;
    }

    return max(1, (int) $page);
  }

  /**
   * 목록 페이징 데이터를 생성합니다.
   *
   * @param int $page 현재 페이지
   * @param int $limit 페이지당 조회 개수
   * @param int $total 전체 개수
   * @param int $totalPages 전체 페이지 수
   *
   * @return array
   */
  private function makePagination(int $page, int $limit, int $total, int $totalPages): array
  {
    $half = intdiv(self::MAX_PAGE_LINKS, 2);
    $startPage = max(1, $page - $half);
    $endPage = min($totalPages, $startPage + self::MAX_PAGE_LINKS - 1);
    $startPage = max(1, $endPage - self::MAX_PAGE_LINKS + 1);

    return [
      'page' => $page,
      'limit' => $limit,
      'offset' => ($page - 1) * $limit,
      'total' => $total,
      'total_pages' => $totalPages,
      'pages' => range($startPage, $endPage),
      'has_prev' => $page > 1,
      'has_next' => $page < $totalPages,
      'prev_page' => max(1, $page - 1),
      'next_page' => min($totalPages, $page + 1),
    ];
  }
}
