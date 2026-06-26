<?php

declare(strict_types=1);

namespace App\Controllers\Common\Upload\Image;

use App\Controllers\Actions\Action;
use Psr\Http\Message\ResponseInterface as Response;

class Post extends Action
{
  /**
   * 이미지 업로드 요청을 처리합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $formData = $this->getFormData();
    $uploadedFiles = $this->request->getUploadedFiles();
    $session = $this->container->has('session') ? $this->container->get('session') : null;
    $adminName = is_object($session) ? (string) ($session->get('admin_name') ?? $session->get('admin_id') ?? '관리자') : '관리자';

    $result = $this->service->execute([
      'file' => $uploadedFiles['file'] ?? null,
      'category' => $formData['category'] ?? '',
      'uploader_name' => $adminName,
    ]);

    if (($result['success'] ?? false) !== true) {
      return $this->errorResponse(
        (string) ($result['message'] ?? '이미지 업로드에 실패했습니다.'),
        (int) ($result['status'] ?? 400),
        ['field' => $result['field'] ?? 'file']
      );
    }

    return $this->successResponse(
      $result['data'] ?? [],
      (string) ($result['message'] ?? '이미지를 업로드했습니다.')
    );
  }
}