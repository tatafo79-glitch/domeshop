<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Goods\Register\RestrictedWordCheck;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Post extends Admin
{
  /**
   * 상품명/키워드 금지단어 사전검사 요청을 처리합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $result = $this->service->execute([
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
