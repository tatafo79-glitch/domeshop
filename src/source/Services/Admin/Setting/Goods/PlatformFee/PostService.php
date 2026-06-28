<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\PlatformFee;

use App\Repositories\Admin\PlatformFeeRepository;
use App\Services\BaseService;
use Throwable;

class PostService extends BaseService
{
  use PlatformFeeInput;

  /**
   * 플랫폼 수수료 설정을 등록합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $normalized = $this->normalizePlatformFeeData($data);
    if (($normalized['success'] ?? false) !== true) {
      return $normalized;
    }

    $repository = $this->container->get(PlatformFeeRepository::class);
    $payload = $normalized['data'];

    if ($repository->existsPlatformCode((string) $payload['platform_code'])) {
      return $this->fail('이미 등록된 플랫폼 코드입니다.', 'platform_code');
    }


    $this->db->beginTransaction();
    try {
      if ($payload['is_default'] === 'Y') {
        $repository->clearDefaultPlatformFee();
      }
      $id = $repository->insertPlatformFee($payload);
      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => '플랫폼 수수료가 등록되었습니다.',
      'data' => ['id' => $id],
    ];
  }
}
