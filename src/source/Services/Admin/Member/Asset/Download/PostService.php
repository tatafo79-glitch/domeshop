<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\Asset\Download;

use App\Lib\GusLib;
use App\Repositories\Admin\MemberRepository;
use App\Services\BaseService;
use Generator;

class PostService extends BaseService
{
  private const ASSET_MAP = [
    'deposit' => ['type' => 'DEPOSIT', 'label' => '적립금', 'unit' => '원'],
    'point' => ['type' => 'POINT', 'label' => '포인트', 'unit' => 'P'],
  ];

  /**
   * 회원 자산 이력 다운로드 스트림 데이터를 생성합니다.
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
      return $this->fail('다운로드할 회원 자산 정보가 올바르지 않습니다.', 'member_id');
    }

    $member = $this->repo?->getMemberById($memberId);
    if ($member === null) {
      return $this->fail('회원을 찾을 수 없습니다. 목록에서 다시 선택해 주세요.', 'member_id', 404);
    }

    if ((string) ($member['role'] ?? '') === 'ADMIN') {
      return $this->fail('관리자 계정은 적립금/포인트 내역을 다운로드할 수 없습니다.', 'member_id');
    }

    return [
      'filename' => '개별회원_' . $asset['label'] . '내역_' . date('Ymd_His') . '.csv',
      'headers' => [
        'NO',
        '사유',
        '주문번호',
        '변동 수량',
        '변동 후 잔액',
        '일시',
      ],
      'rows' => $this->buildRows($memberId, (string) $asset['type'], (string) $asset['unit']),
    ];
  }

  /**
   * 회원 자산 다운로드 행을 생성합니다.
   *
   * @param int $memberId 회원 ID
   * @param string $assetType 자산 구분(DEPOSIT/POINT)
   * @param string $unit 표시 단위
   *
   * @return Generator<array>
   */
  private function buildRows(int $memberId, string $assetType, string $unit): Generator
  {
    /** @var MemberRepository $repo */
    $repo = $this->repo;
    /** @var GusLib $lib */
    $lib = $this->container->get(GusLib::class);

    $index = 1;
    foreach ($repo->getMemberAssetHistoryDownloadGenerator($memberId, $assetType) as $history) {
      $changeAmount = (int) ($history['change_amount'] ?? 0);
      $balanceAfter = (int) ($history['balance_after'] ?? 0);

      yield array_map(
        fn (mixed $value): string => $lib->escapeCsvValue($value),
        [
          (string) $index,
          (string) ($history['reason'] ?? ''),
          (string) (($history['order_no'] ?? '') !== '' ? $history['order_no'] : '-'),
          ($changeAmount > 0 ? '+' : '') . number_format($changeAmount) . $unit,
          number_format($balanceAfter) . $unit,
          (string) ($history['created_at'] ?? ''),
        ]
      );
      $index++;
    }
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
