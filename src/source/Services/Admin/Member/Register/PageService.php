<?php

declare(strict_types=1);

namespace App\Services\Admin\Member\Register;

use App\Repositories\Common\BankRepository;
use App\Services\BaseService;

class PageService extends BaseService
{
  /**
   * 회원 등록 화면에 필요한 기본 데이터를 반환합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    return [
      'banks' => $this->container->get(BankRepository::class)->getActiveBanks(),
      'member' => [
        'role' => 'SELLER',
        'approval_status_code' => 'PENDING',
        'status_code' => 'ACTIVE',
        'is_email_agreed' => '1',
        'is_sms_agreed' => '1',
      ],
      'previous_url' => $this->adminUrl('/member/lists'),
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
