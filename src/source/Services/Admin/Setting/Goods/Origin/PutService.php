<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\Origin;

use App\Repositories\Admin\GoodsOriginRepository;
use Throwable;

class PutService extends PostService
{
  /**
   * 상품 원산지를 수정합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $id = (int) ($params['id'] ?? 0);
    if ($id < 1) {
      return $this->fail('수정할 원산지를 선택해 주세요.', 'origin_name');
    }

    $repository = $this->container->get(GoodsOriginRepository::class);
    $current = $repository->getGoodsOriginById($id);
    if ($current === null) {
      return $this->fail('수정할 원산지를 찾을 수 없습니다. 목록에서 다시 선택해 주세요.', 'origin_name', 404);
    }

    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $normalized = $this->normalizeOriginData($data, $repository);
    if (($normalized['success'] ?? false) !== true) {
      return $normalized;
    }

    $payload = $normalized['data'];
    $currentLevel = (int) ($current['level'] ?? 0);
    $nextLevel = (int) $payload['level'];
    if ($currentLevel !== $nextLevel && $repository->hasChildren($id, $currentLevel)) {
      return $this->fail('하위 원산지가 있는 항목은 단계를 변경할 수 없습니다.', 'level');
    }
    if ($nextLevel > 0 && (int) ($payload['cd0'] ?? 0) === $id) {
      return $this->fail('자기 자신을 상위 원산지로 선택할 수 없습니다.', 'parent_depth1');
    }
    if ($nextLevel > 1 && (int) ($payload['cd1'] ?? 0) === $id) {
      return $this->fail('자기 자신을 상위 원산지로 선택할 수 없습니다.', 'parent_depth2');
    }

    if ($repository->existsOriginName((string) $payload['nm'], $nextLevel, $payload['cd0'], $payload['cd1'], $id)) {
      return $this->fail('같은 단계에 이미 등록된 원산지명입니다.', 'origin_name', 409);
    }

    $this->db->beginTransaction();
    try {
      $repository->updateGoodsOrigin($id, $payload);
      if ($nextLevel === 0) {
        $repository->syncRootChildren($id, (string) $payload['nm']);
      }
      if ($nextLevel === 1) {
        $repository->syncSecondDepthChildren($id, (int) $payload['cd0'], (string) $payload['pathnm0'], (string) $payload['nm']);
      }
      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => '상품 원산지가 수정되었습니다.',
      'data' => ['id' => $id],
    ];
  }
}