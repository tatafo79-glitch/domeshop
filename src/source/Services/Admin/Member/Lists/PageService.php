<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\Lists;

use App\Repositories\Admin\MemberRepository;
use App\Services\BaseService;

class PageService extends BaseService
{
  /** @var int[] 페이지당 표시 가능한 목록 개수 */
  private const LIMIT_OPTIONS = [20, 50, 100];

  /** @var array<string,string> 회원 유형 표시명 */
  private const ROLE_LABELS = [
    'ADMIN' => '관리자',
    'SELLER' => '판매사',
    'VENDOR' => '공급사',
  ];

  /**
   * 공급사 코드 표시명을 반환합니다.
   *
   * @param string $role 회원 유형 코드
   * @param string $vendorCode 공급사 코드
   *
   * @return string
   */
  private function vendorCodeLabel(string $role, string $vendorCode): string
  {
    if ($role !== 'VENDOR' || $vendorCode === '') {
      return '-';
    }

    return $vendorCode;
  }


  /**
   * 회원 목록 화면에 필요한 검색 결과와 통계를 반환합니다.
   *
   * @param array $params 검색 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $normalized = $this->normalizeFilters($params);
    $filters = $normalized['filters'];
    $errors = $normalized['errors'];
    $page = $this->normalizePage($params['page'] ?? null);
    $limit = $this->normalizeLimit($params['limit'] ?? null);
    $offset = ($page - 1) * $limit;

    /** @var MemberRepository $repo */
    $repo = $this->repo;
    $total = $repo->countMemberList($filters);
    $members = $repo->getMemberList($filters, $limit, $offset);
    $totalPages = max(1, (int) ceil($total / $limit));

    if ($page > $totalPages) {
      $page = $totalPages;
      $offset = ($page - 1) * $limit;
      $members = $repo->getMemberList($filters, $limit, $offset);
    }

    return [
      'summary' => $repo->getMemberSummary(),
      'members' => array_map(fn (array $member): array => $this->formatMember($member), $members),
      'filters' => $filters,
      'limit_options' => self::LIMIT_OPTIONS,
      'pagination' => $this->buildPagination($params, $page, $limit, $total, $totalPages),
      'error_message' => $errors === [] ? '' : implode(' ', $errors),
    ];
  }

  /**
   * 검색 파라미터를 허용 목록 기준으로 정규화합니다.
   *
   * @param array $params 검색 파라미터
   *
   * @return array{filters:array,errors:array}
   */
  private function normalizeFilters(array $params): array
  {
    $errors = [];
    $filters = [
      'role' => $this->allow((string) ($params['role'] ?? ''), ['ADMIN', 'SELLER', 'VENDOR']),
      'approval_status' => $this->allow((string) ($params['approval_status'] ?? ''), ['APPROVED', 'PENDING', 'REJECTED']),
      'status' => $this->allow((string) ($params['status'] ?? ''), ['ACTIVE', 'SUSPENDED', 'WITHDRAWN']),
      'keyword_type' => $this->allow((string) ($params['keyword_type'] ?? ''), ['', 'company_name', 'user_id', 'name', 'mobile']),
      'keyword' => mb_substr(trim((string) ($params['keyword'] ?? '')), 0, 100),
      'date_type' => $this->allowOrDefault((string) ($params['date_type'] ?? 'created_at'), ['created_at', 'last_login_at'], 'created_at'),
      'date_start' => trim((string) ($params['date_start'] ?? '')),
      'date_end' => trim((string) ($params['date_end'] ?? '')),
      'amount_type' => $this->allowOrDefault((string) ($params['amount_type'] ?? 'deposit'), ['deposit', 'mileage'], 'deposit'),
      'amount_min' => trim((string) ($params['amount_min'] ?? '')),
      'amount_max' => trim((string) ($params['amount_max'] ?? '')),
    ];

    foreach (['date_start', 'date_end'] as $key) {
      if ($filters[$key] !== '' && !$this->isValidDate($filters[$key])) {
        $filters[$key] = '';
        $errors[] = '날짜 형식을 확인해주세요.';
      }
    }

    foreach (['amount_min', 'amount_max'] as $key) {
      if ($filters[$key] !== '' && !ctype_digit($filters[$key])) {
        $filters[$key] = '';
        $errors[] = '금액은 숫자만 입력해주세요.';
      }
    }

    if ($filters['amount_min'] !== '' && $filters['amount_max'] !== '' && (int) $filters['amount_min'] > (int) $filters['amount_max']) {
      $errors[] = '금액 범위의 최소값이 최대값보다 큽니다.';
      $filters['amount_min'] = '';
      $filters['amount_max'] = '';
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
   * 허용된 값이 아니면 기본값을 반환합니다.
   *
   * @param string $value 입력값
   * @param array $allowed 허용 목록
   * @param string $default 기본값
   *
   * @return string
   */
  private function allowOrDefault(string $value, array $allowed, string $default): string
  {
    return in_array($value, $allowed, true) ? $value : $default;
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
   * 목록에 표시할 회원 데이터를 변환합니다.
   *
   * @param array $member 회원 원본 데이터
   *
   * @return array
   */
  private function formatMember(array $member): array
  {
    $role = (string) ($member['role'] ?? '');
    $createdAt = (string) ($member['created_at'] ?? '');
    $lastLoginAt = (string) ($member['last_login_at'] ?? '');

    return [
      'id' => (int) ($member['id'] ?? 0),
      'role' => $role,
      'role_label' => self::ROLE_LABELS[$role] ?? $role,
      'company_name' => (string) (($member['company_name'] ?? '') !== '' ? $member['company_name'] : ($member['name'] ?? '-')),
      'user_id' => (string) ($member['user_id'] ?? ''),
      'vendor_code' => $this->vendorCodeLabel($role, (string) ($member['vendor_code'] ?? '')),
      'name' => (string) ($member['name'] ?? ''),
      'mobile' => (string) (($member['mobile'] ?? '') !== '' ? $member['mobile'] : '-'),
      'deposit' => $role === 'ADMIN' ? '-' : number_format((int) ($member['deposit'] ?? 0)),
      'point' => $role === 'ADMIN' ? '-' : number_format((int) ($member['mileage'] ?? 0)),
      'approval_status' => $this->approvalStatusLabel((string) ($member['approval_status'] ?? '')),
      'status' => $this->statusLabel((string) ($member['status'] ?? '')),
      'joined_date' => $createdAt !== '' ? date('Y-m-d', strtotime($createdAt)) : '-',
      'joined_time' => $createdAt !== '' ? date('H:i', strtotime($createdAt)) : '',
      'last_login_date' => $lastLoginAt !== '' ? date('Y-m-d', strtotime($lastLoginAt)) : '-',
      'last_login_time' => $lastLoginAt !== '' ? date('H:i', strtotime($lastLoginAt)) : '',
    ];
  }

  /**
   * 승인 상태 표시명을 반환합니다.
   *
   * @param string $status 승인 상태 코드
   *
   * @return string
   */
  private function approvalStatusLabel(string $status): string
  {
    return [
      'APPROVED' => '승인',
      'PENDING' => '대기',
      'REJECTED' => '반려',
    ][$status] ?? '-';
  }

  /**
   * 계정 상태 표시명을 반환합니다.
   *
   * @param string $status 계정 상태 코드
   *
   * @return string
   */
  private function statusLabel(string $status): string
  {
    return [
      'ACTIVE' => '정상',
      'SUSPENDED' => '정지',
      'WITHDRAWN' => '탈퇴',
    ][$status] ?? '-';
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
    $query = http_build_query(array_filter($params, fn ($value): bool => $value !== '' && $value !== null));

    return $query === '' ? '?' : '?' . $query;
  }
}
