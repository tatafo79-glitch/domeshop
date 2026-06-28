<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\PlatformFee\Delete;

use App\Repositories\Admin\PlatformFeeRepository;
use App\Services\BaseService;
use Throwable;

class PostService extends BaseService
{
  /**
   * 플랫폼 수수료 설정을 삭제합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) {
      return $this->fail('삭제할 플랫폼 정보가 올바르지 않습니다.', 'id');
    }

    $repository = $this->container->get(PlatformFeeRepository::class);
    $current = $repository->getPlatformFeeById($id);
    if ($current === null) {
      return $this->fail('삭제할 플랫폼을 찾을 수 없습니다.', 'id', 404);
    }

    $this->db->beginTransaction();
    try {
      $deleted = $repository->softDeletePlatformFee($id);
      if ($deleted !== true) {
        $this->db->rollBack();

        return $this->fail('플랫폼 수수료를 삭제하지 못했습니다. 목록에서 다시 선택해 주세요.', 'id');
      }

      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => '플랫폼 수수료가 삭제되었습니다.',
      'data' => ['id' => $id],
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
