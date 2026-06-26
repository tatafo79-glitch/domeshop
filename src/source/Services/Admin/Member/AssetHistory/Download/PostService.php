<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\AssetHistory\Download;

use App\Lib\GusLib;
use App\Repositories\Admin\MemberRepository;
use App\Services\BaseService;
use Generator;

class PostService extends BaseService
{
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
   * 전체 회원 자산 이력 다운로드 스트림 데이터를 생성합니다.
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
      return $this->fail('다운로드할 자산 이력 구분이 올바르지 않습니다.', 'asset');
    }

    $form = is_array($params['form'] ?? null) ? $params['form'] : [];
    $normalized = $this->normalizeFilters($form, (string) $asset['type']);
    if (($normalized['success'] ?? false) !== true) {
      return $normalized;
    }

    $filters = $normalized['filters'];

    return [
      'filename' => $asset['label'] . '내역_' . date('Ymd_His') . '.csv',
      'headers' => [
        'NO',
        '회원타입',
        '상호명',
        '아이디',
        '대표자명',
        '사유',
        '주문번호',
        '변동 수량',
        '변동 후 잔액',
        '처리자',
        '일시',
      ],
      'rows' => $this->buildRows($filters, (string) $asset['unit']),
    ];
  }

  /**
   * 다운로드 검색 파라미터를 허용 목록 기준으로 정규화합니다.
   *
   * @param array $params 검색 파라미터
   * @param string $assetType 자산 구분(DEPOSIT/POINT)
   *
   * @return array
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
        $errors[] = '날짜 형식을 확인한 뒤 다시 다운로드해 주세요.';
      }
    }

    if ($errors !== []) {
      return $this->fail(implode(' ', array_values(array_unique($errors))), 'date_start');
    }

    return ['success' => true, 'filters' => $filters];
  }

  /**
   * 전체 회원 자산 이력 다운로드 행을 생성합니다.
   *
   * @param array $filters 검색 조건
   * @param string $unit 표시 단위
   *
   * @return Generator<array>
   */
  private function buildRows(array $filters, string $unit): Generator
  {
    /** @var MemberRepository $repo */
    $repo = $this->repo;
    /** @var GusLib $lib */
    $lib = $this->container->get(GusLib::class);

    $index = 1;
    foreach ($repo->getMemberAssetHistoryDownloadListGenerator($filters) as $history) {
      $role = (string) ($history['role'] ?? '');
      $changeAmount = (int) ($history['change_amount'] ?? 0);
      $balanceAfter = (int) ($history['balance_after'] ?? 0);
      $createdAt = (string) ($history['created_at'] ?? '');

      yield array_map(
        fn (mixed $value): string => $lib->escapeCsvValue($value),
        [
          (string) $index,
          self::ROLE_LABELS[$role] ?? $role,
          (string) (($history['company_name'] ?? '') !== '' ? $history['company_name'] : ($history['name'] ?? '')),
          (string) ($history['user_id'] ?? ''),
          (string) ($history['name'] ?? ''),
          (string) ($history['reason'] ?? ''),
          (string) (($history['order_no'] ?? '') !== '' ? $history['order_no'] : '-'),
          ($changeAmount > 0 ? '+' : '') . number_format($changeAmount) . $unit,
          number_format($balanceAfter) . $unit,
          (string) (($history['actor_name'] ?? '') !== '' ? $history['actor_name'] : '-'),
          $createdAt !== '' ? date('Y-m-d H:i:s', strtotime($createdAt)) : '',
        ]
      );
      $index++;
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
