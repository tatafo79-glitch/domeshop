<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Setting\Goods\Origin;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Put extends Admin
{
  /**
   * 상품 원산지 수정 요청을 처리합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $result = $this->service->execute([
      'id' => (int) $this->resolveArg('id'),
      'data' => $this->getFormData(),
    ]);

    if (($result['success'] ?? false) !== true) {
      $status = (int) ($result['status'] ?? 400);
      unset($result['status']);

      return $this->respondWithData($result, $status);
    }

    return $this->respondWithData($result);
  }
}
