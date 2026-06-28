<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\Origin\Delete;

use App\Repositories\Admin\GoodsOriginRepository;
use App\Services\BaseService;
use Throwable;

class PostService extends BaseService
{
  /**
   * 선택한 상품 원산지와 하위 원산지를 삭제합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $id = (int) ($params['id'] ?? 0);
    if ($id < 1) {
      return $this->fail('삭제할 원산지를 선택해 주세요.', 'origin_id');
    }

    $repository = $this->container->get(GoodsOriginRepository::class);
    $origin = $repository->getGoodsOriginById($id);
    if ($origin === null) {
      return $this->fail('삭제할 원산지를 찾을 수 없습니다. 목록에서 다시 선택해 주세요.', 'origin_id', 404);
    }

    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $level = (int) ($origin['level'] ?? 0);
    $hasChildren = $repository->hasChildren($id, $level);
    if ($hasChildren && ($data['cascade_confirm'] ?? '') !== '1') {
      return $this->failWithData(
        '해당 원산지를 삭제하시겠습니까?
삭제시 하위 원산지도 함께 삭제 됩니다.',
        'origin_id',
        ['needs_cascade_confirm' => true],
        409
      );
    }

    $this->db->beginTransaction();
    try {
      $deletedCount = $repository->deleteGoodsOriginBranch($id, $level);
      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => $deletedCount > 1 ? '원산지와 하위 원산지가 삭제되었습니다.' : '원산지가 삭제되었습니다.',
      'data' => ['deleted_count' => $deletedCount],
    ];
  }

  /**
   * 추가 데이터가 포함된 실패 응답 배열을 생성합니다.
   *
   * @param string $message 오류 메시지
   * @param string $field 오류 필드
   * @param array $data 추가 응답 데이터
   * @param int $status HTTP 상태 코드
   *
   * @return array
   */
  private function failWithData(string $message, string $field, array $data, int $status = 400): array
  {
    return [
      'success' => false,
      'message' => $message,
      'field' => $field,
      'data' => $data,
      'status' => $status,
    ];
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
