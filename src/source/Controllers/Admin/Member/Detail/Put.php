<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Member\Detail;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Put extends Admin
{
  /**
   * 회원 상세 수정 요청을 처리합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $admin = $this->container->get('session')->get('admin') ?? [];
    $result = $this->service->execute([
      'id' => (int) $this->resolveArg('id'),
      'data' => $this->getFormData(),
      'actor_name' => (string) ($admin['name'] ?? '관리자'),
      'ip_address' => $this->request->getServerParams()['REMOTE_ADDR'] ?? null,
    ]);

    if (($result['success'] ?? false) !== true) {
      $status = (int) ($result['status'] ?? 400);
      unset($result['status']);

      return $this->respondWithData($result, $status);
    }

    return $this->respondWithData($result);
  }
}