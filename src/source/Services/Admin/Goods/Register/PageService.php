<?php

declare(strict_types=1);

namespace App\Services\Admin\Goods\Register;

use App\Services\Admin\Setting\Goods\Register\GoodsRegisterSetting;
use App\Services\BaseService;

class PageService extends BaseService
{
  /**
   * 상품 등록 화면에 필요한 기본 데이터를 반환합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    return [
      'categories' => $this->repo?->getActiveCategories() ?? [],
      'vendors' => $this->repo?->getApprovedVendors() ?? [],
      'origins' => $this->repo?->getGoodsOrigins() ?? [],
      'register_setting' => $this->container->get(GoodsRegisterSetting::class)->getSettings(),
      'previous_url' => $this->adminUrl('/goods/lists'),
    ];
  }

  /**
   * 관리자 경로를 포함한 내부 URL을 생성합니다.
   *
   * @param string $path 관리자 하위 경로
   *
   * @return string
   */
  private function adminUrl(string $path): string
  {
    $settings = $this->container->get('settings');
    $adminDir = (string) ($settings['site']['admin_directory'] ?? 'dmmt');

    return '/' . trim($adminDir, '/') . $path;
  }
}
