<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\AssetHistory;

use App\Repositories\Admin\MemberRepository;
use App\Services\BaseService;
use RuntimeException;

class PageService extends BaseService
{
  /** @var int[] 페이지당 표시 가능한 목록 개수 */
  private const LIMIT_OPTIONS = [20, 50, 100];

  /** @var array<string,array<string,string>> 자산 화면 설정 */
  private const ASSET_MAP = [
    'deposit' => ['type' => 'DEPOSIT', 'label' => '적립금', 'unit' => '원'],
    'point' => ['type' => 'POINT', 'label' => '포인트', 'unit' => 'P'],
  ];

  /** @var array<string,string> 회원 유형 표시명 */
  private const ROLE_LABELS = [
    'ADMIN' => '관리자',
    'SELLER' => '판매사',
    'VENDOR' => '공급사',
  ];

  /**
   * 전체 회원 자산 이력 목록 데이터를 조회합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $assetKey = (string) ($params['asset'] ?? '');
    $asset = self::ASSET_MAP[$assetKey] ?? null;
    if ($asset === null) {
      throw new RuntimeException('자산 이력 구분이 올바르지 않습니다.');
    }

    $query = is_array($params['query'] ?? null) ? $params['query'] : [];
    $normalized = $this->normalizeFilters($query, (string) $asset['type']);
    $filters = $normalized['filters'];
    $errors = $normalized['errors'];
    $page = $this->normalizePage($query['page'] ?? null);
    $limit = $this->normalizeLimit($query['limit'] ?? null);
    $offset = ($page - 1) * $limit;

    /** @var MemberRepository $repo */
    $repo = $this->repo;
    $total = $repo->countMemberAssetHistoryList($filters);
    $totalPages = max(1, (int) ceil($total / $limit));

    if ($page > $totalPages) {
      $page = $totalPages;
      $offset = ($page - 1) * $limit;
    }

    $histories = $repo->getMemberAssetHistoryList($filters, $limit, $offset);

    return [
      'asset' => [
        'key' => $assetKey,
        'type' => $asset['type'],
        'label' => $asset['label'],
        'unit' => $asset['unit'],
      ],
      'filters' => $filters,
      'histories' => $this->formatHistories($histories, (string) $asset['unit'], $offset),
      'limit_options' => self::LIMIT_OPTIONS,
      'pagination' => $this->buildPagination($query, $page, $limit, $total, $totalPages),
      'error_message' => $errors === [] ? '' : implode(' ', $errors),
    ];
  }

  /**
   * 검색 파라미터를 허용 목록 기준으로 정규화합니다.
   *
   * @param array $params 검색 파라미터
   * @param string $assetType 자산 구분(DEPOSIT/POINT)
   *
   * @return array{filters:array,errors:array}
   */
  private function normalizeFilters(array $params, string $assetType): array
  {
    $errors = [];
    $defaultDateStart = date('Y-m-d', strtotime('-1 month'));
    $defaultDateEnd = date('Y-m-d');
    $filters = [
      'asset_type' => $assetType,
      'role' => $this->allow((string) ($params['role'] ?? ''), ['', 'SELLER', 'VENDOR']),
      'change_type' => $this->allow((string) ($params['change_type'] ?? ''), ['', 'PLUS', 'MINUS']),
      'keyword_type' => $this->allow((string) ($params['keyword_type'] ?? ''), ['', 'company_name', 'user_id', 'name', 'reason', 'order_no', 'actor_name']),
      'keyword' => mb_substr(trim((string) ($params['keyword'] ?? '')), 0, 100),
      'date_start' => array_key_exists('date_start', $params) ? trim((string) $params['date_start']) : $defaultDateStart,
      'date_end' => array_key_exists('date_end', $params) ? trim((string) $params['date_end']) : $defaultDateEnd,
    ];

    foreach (['date_start', 'date_end'] as $key) {
      if ($filters[$key] !== '' && !$this->isValidDate($filters[$key])) {
        $filters[$key] = '';
        $errors[] = '날짜 형식을 확인해주세요.';
      }
    }

    return ['filters' => $filters, 'errors' => array_values(array_unique($errors))];
  }

  /**
   * 허용된 값만 반환합니다.
   *
   * @param string $value 입력값
   * @param array $allowed 허용 목록
   *
   * @return string
   */
  private function allow(string $value, array $allowed): string
  {
    return in_array($value, $allowed, true) ? $value : '';
  }

  /**
   * 페이지 번호를 정규화합니다.
   *
   * @param mixed $page 입력 페이지
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
   * 페이지당 표시 개수를 정규화합니다.
   *
   * @param mixed $limit 입력 표시 개수
   *
   * @return int
   */
  private function normalizeLimit(mixed $limit): int
  {
    if (!is_scalar($limit) || !preg_match('/^[0-9]+$/', (string) $limit)) {
      return 20;
    }

    $limit = (int) $limit;

    return in_array($limit, self::LIMIT_OPTIONS, true) ? $limit : 20;
  }

  /**
   * 날짜 문자열이 YYYY-MM-DD 형식인지 검증합니다.
   *
   * @param string $date 날짜 문자열
   *
   * @return bool
   */
  private function isValidDate(string $date): bool
  {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      return false;
    }

    [$year, $month, $day] = array_map('intval', explode('-', $date));

    return checkdate($month, $day, $year);
  }

  /**
   * 목록에 표시할 이력 목록을 변환합니다.
   *
   * @param array $histories 자산 이력 원본 목록
   * @param string $unit 표시 단위
   * @param int $offset 조회 시작 위치
   *
   * @return array
   */
  private function formatHistories(array $histories, string $unit, int $offset): array
  {
    $formatted = [];

    foreach ($histories as $index => $history) {
      $row = $this->formatHistory($history, $unit);
      $row['no'] = $offset + $index + 1;
      $formatted[] = $row;
    }

    return $formatted;
  }
  /**
   * 목록에 표시할 이력 데이터를 변환합니다.
   *
   * @param array $history 자산 이력 원본 데이터
   * @param string $unit 표시 단위
   *
   * @return array
   */
  private function formatHistory(array $history, string $unit): array
  {
    $role = (string) ($history['role'] ?? '');
    $changeAmount = (int) ($history['change_amount'] ?? 0);
    $balanceAfter = (int) ($history['balance_after'] ?? 0);
    $createdAt = (string) ($history['created_at'] ?? '');

    return [
      'id' => (int) ($history['id'] ?? 0),
      'member_id' => (int) ($history['member_id'] ?? 0),
      'role' => $role,
      'role_label' => self::ROLE_LABELS[$role] ?? $role,
      'company_name' => (string) (($history['company_name'] ?? '') !== '' ? $history['company_name'] : ($history['name'] ?? '-')),
      'user_id' => (string) ($history['user_id'] ?? ''),
      'reason' => (string) ($history['reason'] ?? ''),
      'order_no' => (string) (($history['order_no'] ?? '') !== '' ? $history['order_no'] : '-'),
      'change_amount' => $changeAmount,
      'formatted_change_amount' => ($changeAmount > 0 ? '+' : '') . number_format($changeAmount) . $unit,
      'balance_after' => $balanceAfter,
      'formatted_balance_after' => number_format($balanceAfter) . $unit,
      'actor_name' => (string) (($history['actor_name'] ?? '') !== '' ? $history['actor_name'] : '-'),
      'created_date' => $createdAt !== '' ? date('Y-m-d', strtotime($createdAt)) : '-',
      'created_time' => $createdAt !== '' ? date('H:i', strtotime($createdAt)) : '',
    ];
  }

  /**
   * 페이지네이션 표시 데이터를 생성합니다.
   *
   * @param array $params 현재 검색 파라미터
   * @param int $page 현재 페이지
   * @param int $limit 페이지당 표시 개수
   * @param int $total 전체 개수
   * @param int $totalPages 전체 페이지 수
   *
   * @return array
   */
  private function buildPagination(array $params, int $page, int $limit, int $total, int $totalPages): array
  {
    $params['limit'] = $limit;
    $start = max(1, $page - 2);
    $end = min($totalPages, $start + 4);
    $start = max(1, $end - 4);
    $pages = [];

    for ($i = $start; $i <= $end; $i++) {
      $pages[] = [
        'number' => $i,
        'url' => $this->buildPageUrl($params, $i),
        'active' => $i === $page,
        'desktop_only' => count($pages) >= 3,
      ];
    }

    return [
      'page' => $page,
      'limit' => $limit,
      'total' => $total,
      'total_pages' => $totalPages,
      'has_prev' => $page > 1,
      'has_next' => $page < $totalPages,
      'prev_url' => $this->buildPageUrl($params, max(1, $page - 1)),
      'next_url' => $this->buildPageUrl($params, min($totalPages, $page + 1)),
      'pages' => $pages,
    ];
  }

  /**
   * 페이지 이동 URL을 생성합니다.
   *
   * @param array $params 현재 검색 파라미터
   * @param int $page 이동할 페이지
   *
   * @return string
   */
  private function buildPageUrl(array $params, int $page): string
  {
    $params['page'] = $page;
    $query = http_build_query(array_filter($params, fn (mixed $value): bool => $value !== '' && $value !== null));

    return $query === '' ? '?' : '?' . $query;
  }
}