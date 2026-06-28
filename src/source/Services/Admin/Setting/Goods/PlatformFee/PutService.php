<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\PlatformFee;

use App\Repositories\Admin\PlatformFeeRepository;
use App\Services\BaseService;
use Throwable;

class PutService extends BaseService
{
  use PlatformFeeInput;

  /**
   * 플랫폼 수수료 설정을 수정합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $id = (int) ($params['id'] ?? 0);
    if ($id <= 0) {
      return $this->fail('수정할 플랫폼 정보가 올바르지 않습니다.', 'id');
    }

    $repository = $this->container->get(PlatformFeeRepository::class);
    $current = $repository->getPlatformFeeById($id);
    if ($current === null) {
      return $this->fail('수정할 플랫폼을 찾을 수 없습니다.', 'id', 404);
    }

    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $normalized = $this->normalizePlatformFeeData($data);
    if (($normalized['success'] ?? false) !== true) {
      return $normalized;
    }

    $payload = $normalized['data'];
    if ($repository->existsPlatformCode((string) $payload['platform_code'], $id)) {
      return $this->fail('이미 등록된 플랫폼 코드입니다.', 'platform_code');
    }


    $this->db->beginTransaction();
    try {
      if ($payload['is_default'] === 'Y') {
        $repository->clearDefaultPlatformFee();
      }
      $repository->updatePlatformFee($id, $payload);
      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => '플랫폼 수수료가 수정되었습니다.',
      'data' => ['id' => $id],
    ];
  }
}
