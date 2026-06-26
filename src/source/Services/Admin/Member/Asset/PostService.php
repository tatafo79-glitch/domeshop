<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\Asset;

use App\Services\BaseService;
use Throwable;

class PostService extends BaseService
{
  private const ASSET_MAP = [
    'deposit' => ['type' => 'DEPOSIT', 'column' => 'deposit', 'label' => '적립금'],
    'point' => ['type' => 'POINT', 'column' => 'mileage', 'label' => '포인트'],
  ];
  private const ACTIONS = ['plus', 'minus'];

  /**
   * 회원 자산을 수동 적립 또는 차감합니다.
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
    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $actorName = trim((string) ($params['actor_name'] ?? '관리자')) ?: '관리자';

    if ($memberId <= 0 || $asset === null) {
      return $this->fail('회원 자산 정보가 올바르지 않습니다.', 'member_id');
    }

    $action = (string) ($data['action_type'] ?? '');
    if (!in_array($action, self::ACTIONS, true)) {
      return $this->fail($asset['label'] . ' 처리 구분을 선택해 주세요.', 'action_type');
    }

    $rawAmount = trim((string) ($data['amount'] ?? ''));
    if ($rawAmount === '' || preg_match('/^[0-9]+$/', $rawAmount) !== 1) {
      return $this->fail($asset['label'] . ' 금액은 1 이상의 숫자로 입력해 주세요.', 'amount');
    }

    $amount = (int) $rawAmount;
    if ($amount <= 0) {
      return $this->fail($asset['label'] . ' 금액은 1 이상 입력해 주세요.', 'amount');
    }

    $reason = trim((string) ($data['reason'] ?? ''));
    if ($reason === '') {
      return $this->fail('변동 사유를 입력해 주세요.', 'reason');
    }

    if (mb_strlen($reason) > 255) {
      return $this->fail('변동 사유는 255자 이하로 입력해 주세요.', 'reason');
    }

    $orderNo = trim((string) ($data['order_no'] ?? ''));
    if ($orderNo !== '' && mb_strlen($orderNo) > 50) {
      return $this->fail('주문번호는 50자 이하로 입력해 주세요.', 'order_no');
    }

    $changeAmount = $action === 'minus' ? -$amount : $amount;

    $this->db->beginTransaction();
    try {
      $member = $this->repo?->getMemberAssetForUpdate($memberId);
      if ($member === null) {
        $this->db->rollBack();
        return $this->fail('회원을 찾을 수 없습니다. 목록에서 다시 선택해 주세요.', 'member_id', 404);
      }

      if ((string) ($member['role'] ?? '') === 'ADMIN') {
        $this->db->rollBack();
        return $this->fail('관리자 계정은 적립금/포인트를 관리하지 않습니다.', 'member_id');
      }

      $currentBalance = (int) ($member[$asset['column']] ?? 0);
      $balanceAfter = $currentBalance + $changeAmount;
      if ($balanceAfter < 0) {
        $this->db->rollBack();
        return $this->fail('차감 후 잔액이 음수가 될 수 없습니다. 현재 잔액을 확인해 주세요.', 'amount');
      }

      $this->repo?->updateMemberAssetBalance($memberId, $asset['column'], $balanceAfter);
      $historyId = $this->repo?->insertMemberAssetHistory([
        'member_id' => $memberId,
        'asset_type' => $asset['type'],
        'reason' => $reason,
        'order_no' => $orderNo === '' ? null : $orderNo,
        'change_amount' => $changeAmount,
        'balance_after' => $balanceAfter,
        'actor_name' => $actorName,
      ]) ?? 0;

      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => $asset['label'] . ' 처리가 완료되었습니다.',
      'data' => [
        'id' => $historyId,
        'member_id' => $memberId,
        'balance_after' => $balanceAfter,
        'formatted_balance_after' => number_format($balanceAfter),
      ],
    ];
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
