<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Member\AssetHistory;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Page extends Admin
{
  /**
   * 전체 회원 자산 이력 목록 화면을 렌더링합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $result = $this->service->execute([
      'asset' => (string) $this->resolveArg('asset'),
      'query' => $this->getQueryData(),
    ]);
    $result['current_url'] = $this->getCurrentUrl();

    return $this->render('member/asset_history', $result);
  }

  /**
   * 현재 목록 URL을 상세 화면 복귀 경로로 사용할 수 있게 생성합니다.
   *
   * @return string
   */
  private function getCurrentUrl(): string
  {
    $uri = $this->request->getUri();
    $path = $uri->getPath();
    $query = $uri->getQuery();

    return $query === '' ? $path : $path . '?' . $query;
  }
}