<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\Lists\Download;

use App\Lib\GusLib;
use App\Repositories\Admin\MemberRepository;
use App\Services\BaseService;
use Generator;

class PostService extends BaseService
{
  /** @var array<string,string> 회원 유형 표시명 */
  private const ROLE_LABELS = [
    'ADMIN' => '관리자',
    'SELLER' => '판매사',
    'VENDOR' => '공급사',
  ];

  /** @var array<string,string> 승인 상태 표시명 */
  private const APPROVAL_STATUS_LABELS = [
    'APPROVED' => '승인',
    'PENDING' => '대기',
    'REJECTED' => '반려',
  ];

  /** @var array<string,string> 계정 상태 표시명 */
  private const STATUS_LABELS = [
    'ACTIVE' => '정상',
    'SUSPENDED' => '정지',
    'WITHDRAWN' => '탈퇴',
  ];

  /**
   * 회원 목록 다운로드 스트림 데이터를 생성합니다.
   *
   * @param array $params 검색 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $normalized = $this->normalizeFilters($params);
    if (($normalized['success'] ?? false) !== true) {
      return $normalized;
    }

    $filters = $normalized['filters'];

    return [
      'filename' => '회원목록_' . date('Ymd_His') . '.csv',
      'headers' => [
        '회원번호',
        '회원유형',
        '공급사코드',
        '상호명',
        '아이디',
        '대표자명',
        '이메일',
        '휴대폰',
        '업체전화',
        '사업자등록번호',
        '적립금',
        '포인트',
        '승인상태',
        '계정상태',
        '가입일',
        '최근접속일',
      ],
      'rows' => $this->buildRows($filters),
    ];
  }

  /**
   * 다운로드 검색 파라미터를 허용 목록 기준으로 정규화합니다.
   *
   * @param array $params 검색 파라미터
   *
   * @return array
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
        $errors[] = '날짜 형식을 확인한 뒤 다시 다운로드해 주세요.';
      }
    }

    foreach (['amount_min', 'amount_max'] as $key) {
      if ($filters[$key] !== '' && !ctype_digit($filters[$key])) {
        $filters[$key] = '';
        $errors[] = '금액은 숫자만 입력한 뒤 다시 다운로드해 주세요.';
      }
    }

    if ($filters['amount_min'] !== '' && $filters['amount_max'] !== '' && (int) $filters['amount_min'] > (int) $filters['amount_max']) {
      $filters['amount_min'] = '';
      $filters['amount_max'] = '';
      $errors[] = '금액 범위의 최소값은 최대값보다 클 수 없습니다.';
    }

    if ($errors !== []) {
      return $this->fail(implode(' ', array_values(array_unique($errors))), 'filters');
    }

    return ['success' => true, 'filters' => $filters];
  }

  /**
   * 회원 다운로드 행을 생성합니다.
   *
   * @param array $filters 검색 조건
   *
   * @return Generator<array>
   */
  private function buildRows(array $filters): Generator
  {
    /** @var MemberRepository $repo */
    $repo = $this->repo;
    /** @var GusLib $lib */
    $lib = $this->container->get(GusLib::class);

    foreach ($repo->getMemberDownloadGenerator($filters) as $member) {
      $role = (string) ($member['role'] ?? '');
      $createdAt = (string) ($member['created_at'] ?? '');
      $lastLoginAt = (string) ($member['last_login_at'] ?? '');

      yield array_map(
        fn (mixed $value): string => $lib->escapeCsvValue($value),
        [
          (string) ($member['id'] ?? ''),
          self::ROLE_LABELS[$role] ?? $role,
          $role === 'VENDOR' ? (string) ($member['vendor_code'] ?? '') : '',
          (string) (($member['company_name'] ?? '') !== '' ? $member['company_name'] : ($member['name'] ?? '')),
          (string) ($member['user_id'] ?? ''),
          (string) ($member['name'] ?? ''),
          (string) ($member['email'] ?? ''),
          (string) ($member['mobile'] ?? ''),
          (string) ($member['company_phone'] ?? ''),
          (string) ($member['business_number'] ?? ''),
          $role === 'ADMIN' ? '' : (string) ((int) ($member['deposit'] ?? 0)),
          $role === 'ADMIN' ? '' : (string) ((int) ($member['mileage'] ?? 0)),
          self::APPROVAL_STATUS_LABELS[(string) ($member['approval_status'] ?? '')] ?? '',
          self::STATUS_LABELS[(string) ($member['status'] ?? '')] ?? '',
          $createdAt !== '' ? date('Y-m-d H:i:s', strtotime($createdAt)) : '',
          $lastLoginAt !== '' ? date('Y-m-d H:i:s', strtotime($lastLoginAt)) : '',
        ]
      );
    }
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
   * 실패 응답 배열을 생성합니다.
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
}
