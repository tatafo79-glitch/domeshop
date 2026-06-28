<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Setting\Goods\Origin\Upload;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Post extends Admin
{
  /**
   * 상품 원산지 CSV 업로드 요청을 처리합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $uploadedFiles = $this->request->getUploadedFiles();
    $result = $this->service->execute([
      'file' => $uploadedFiles['origin_file'] ?? null,
    ]);

    if (($result['success'] ?? false) !== true) {
      $status = (int) ($result['status'] ?? 400);
      unset($result['status']);

      return $this->respondWithData($result, $status);
    }

    return $this->respondWithData($result);
  }
}