<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\Register;

use App\Services\BaseService;
use Throwable;

class PostService extends BaseService
{
  /**
   * 상품 등록설정을 검증하고 저장합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $settingService = $this->container->get(GoodsRegisterSetting::class);
    $normalizedResult = $settingService->normalizeForSave($data);
    if (($normalizedResult['success'] ?? false) !== true) {
      return $normalizedResult;
    }

    $this->db->beginTransaction();
    try {
      $settingService->save($normalizedResult['settings']);
      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => '상품 등록설정이 저장되었습니다.',
      'data' => ['settings' => $settingService->getSettings()],
    ];
  }
}
