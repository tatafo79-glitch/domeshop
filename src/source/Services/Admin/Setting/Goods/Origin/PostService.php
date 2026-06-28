<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\Origin;

use App\Repositories\Admin\GoodsOriginRepository;
use App\Services\BaseService;

class PostService extends BaseService
{
  /**
   * 상품 원산지를 등록합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $repository = $this->container->get(GoodsOriginRepository::class);
    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $normalized = $this->normalizeOriginData($data, $repository);
    if (($normalized['success'] ?? false) !== true) {
      return $normalized;
    }

    $payload = $normalized['data'];
    if ($repository->existsOriginName((string) $payload['nm'], (int) $payload['level'], $payload['cd0'], $payload['cd1'])) {
      return $this->fail('같은 단계에 이미 등록된 원산지명입니다.', 'origin_name', 409);
    }

    $id = $repository->insertGoodsOrigin($payload);

    return [
      'success' => true,
      'message' => '상품 원산지가 등록되었습니다.',
      'data' => ['id' => $id],
    ];
  }

  /**
   * 원산지 입력값을 저장 가능한 데이터로 정규화합니다.
   *
   * @param array $data 입력 데이터
   * @param GoodsOriginRepository $repository 원산지 저장소
   *
   * @return array
   */
  protected function normalizeOriginData(array $data, GoodsOriginRepository $repository): array
  {
    $levelValue = trim((string) ($data['level'] ?? ''));
    if (preg_match('/^[0-2]$/', $levelValue) !== 1) {
      return $this->fail('원산지 단계를 올바르게 선택해 주세요.', 'level');
    }
    $level = (int) $levelValue;

    $name = trim((string) ($data['origin_name'] ?? ''));
    if ($name === '' || mb_strlen($name) > 50) {
      return $this->fail('원산지명은 50자 이하로 입력해 주세요.', 'origin_name');
    }

    $sortValue = str_replace(',', '', trim((string) ($data['sort'] ?? '0')));
    if ($sortValue === '' || preg_match('/^\d+$/', $sortValue) !== 1) {
      return $this->fail('정렬 순서는 0 이상의 숫자로 입력해 주세요.', 'sort');
    }
    $sort = (int) $sortValue;
    if ($sort > 999999) {
      return $this->fail('정렬 순서는 999999 이하로 입력해 주세요.', 'sort');
    }

    $parentDepth1 = $this->normalizeOptionalId($data, 'parent_depth1');
    $parentDepth2 = $this->normalizeOptionalId($data, 'parent_depth2');
    if (($parentDepth1['success'] ?? true) !== true) {
      return $parentDepth1;
    }
    if (($parentDepth2['success'] ?? true) !== true) {
      return $parentDepth2;
    }

    $cd0 = null;
    $cd1 = null;
    $pathnm0 = null;
    $pathnm1 = null;
    $last = 'N';

    if ($level === 0) {
      return [
        'success' => true,
        'data' => [
          'nm' => $name,
          'cd0' => null,
          'cd1' => null,
          'pathnm0' => null,
          'pathnm1' => null,
          'level' => 0,
          'sort' => $sort,
          'last' => 'N',
        ],
      ];
    }

    $rootId = (int) ($parentDepth1['value'] ?? 0);
    if ($rootId < 1) {
      return $this->fail('1차 원산지를 선택해 주세요.', 'parent_depth1');
    }
    $root = $repository->getGoodsOriginById($rootId);
    if ($root === null || (int) ($root['level'] ?? -1) !== 0) {
      return $this->fail('유효한 1차 원산지를 선택해 주세요.', 'parent_depth1');
    }

    $cd0 = (int) $root['id'];
    $pathnm0 = (string) $root['nm'];

    if ($level === 1) {
      $last = 'N';
    }

    if ($level === 2) {
      $parentId = (int) ($parentDepth2['value'] ?? 0);
      if ($parentId < 1) {
        return $this->fail('2차 원산지를 선택해 주세요.', 'parent_depth2');
      }
      $parent = $repository->getGoodsOriginById($parentId);
      if ($parent === null || (int) ($parent['level'] ?? -1) !== 1 || (int) ($parent['cd0'] ?? 0) !== $cd0) {
        return $this->fail('유효한 2차 원산지를 선택해 주세요.', 'parent_depth2');
      }
      $cd1 = (int) $parent['id'];
      $pathnm1 = (string) $parent['nm'];
      $last = 'Y';
    }

    return [
      'success' => true,
      'data' => [
        'nm' => $name,
        'cd0' => $cd0,
        'cd1' => $cd1,
        'pathnm0' => $pathnm0,
        'pathnm1' => $pathnm1,
        'level' => $level,
        'sort' => $sort,
        'last' => $last,
      ],
    ];
  }

  /**
   * 선택형 ID 값을 정수로 검증합니다.
   *
   * @param array $data 입력 데이터
   * @param string $field 필드명
   *
   * @return array
   */
  private function normalizeOptionalId(array $data, string $field): array
  {
    $value = trim((string) ($data[$field] ?? ''));
    if ($value === '') {
      return ['success' => true, 'value' => null];
    }
    if (preg_match('/^\d+$/', $value) !== 1) {
      return $this->fail('상위 원산지 선택값이 올바르지 않습니다.', $field);
    }

    return ['success' => true, 'value' => (int) $value];
  }

  /**
   * 실패 응답 배열을 생성합니다.
   *
   * @param string $message 오류 메시지
   * @param string $field 오류 필드
   * @param int $status HTTP 상태 코드
   *
   * @return array
   */
  protected function fail(string $message, string $field, int $status = 400): array
  {
    return [
      'success' => false,
      'message' => $message,
      'field' => $field,
      'status' => $status,
    ];
  }
}