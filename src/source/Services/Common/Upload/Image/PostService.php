<?php

declare(strict_types=1);

namespace App\Services\Common\Upload\Image;

use App\Lib\FileUploader;
use App\Repositories\Common\UploadRepository;
use App\Services\BaseService;
use Psr\Http\Message\UploadedFileInterface;

class PostService extends BaseService
{
  /**
   * 이미지 업로드를 처리하고 메타 정보를 저장합니다.
   *
   * @param array $params 업로드 요청 데이터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $file = $params['file'] ?? null;
    $category = trim((string) ($params['category'] ?? ''));
    $uploaderName = trim((string) ($params['uploader_name'] ?? 'SYSTEM')) ?: 'SYSTEM';

    if (!$file instanceof UploadedFileInterface) {
      return $this->fail('업로드할 이미지 파일을 선택해 주세요.', 'file');
    }

    if (!preg_match('/^[a-z0-9_-]{1,50}$/', $category)) {
      return $this->fail('이미지 업로드 분류가 올바르지 않습니다.', 'category');
    }

    $uploaded = null;

    try {
      $uploader = $this->container->get(FileUploader::class);
      $uploaded = $uploader->uploadImage($file, $category);
      $uploadRepo = $this->container->get(UploadRepository::class);
      $fileId = $uploadRepo->insertFile([
        'original_name' => $uploaded['original_name'],
        'stored_name' => $uploaded['stored_name'],
        'file_path' => $uploaded['file_path'],
        'file_size' => $uploaded['file_size'],
        'mime_type' => $uploaded['mime_type'],
        'category' => $category,
        'uploader_name' => $uploaderName,
      ]);

      return [
        'success' => true,
        'message' => '이미지를 업로드했습니다.',
        'data' => [
          'id' => $fileId,
          'original_name' => $uploaded['original_name'],
          'file_path' => $uploaded['file_path'],
          'file_url' => $uploaded['url'],
          'mime_type' => $uploaded['mime_type'],
          'file_size' => $uploaded['file_size'],
        ],
      ];
    } catch (\InvalidArgumentException $e) {
      return $this->fail($e->getMessage(), 'file');
    } catch (\Throwable) {
      if (is_array($uploaded) && !empty($uploaded['file_path'])) {
        try {
          $this->container->get(FileUploader::class)->deleteUploadedImage((string) $uploaded['file_path']);
        } catch (\Throwable) {
          // 보상 삭제 실패는 원래 업로드 실패 응답을 가리지 않습니다.
        }
      }

      return $this->fail('이미지 업로드에 실패했습니다. 잠시 후 다시 시도해 주세요.', 'file', 500);
    }
  }

  /**
   * 실패 응답 데이터를 생성합니다.
   *
   * @param string $message 오류 안내 문구
   * @param string $field 오류 필드명
   * @param int $status HTTP 상태 코드
   *
   * @return array
   */
  private function fail(string $message, string $field, int $status = 400): array
  {
    return [
      'success' => false,
      'message' => $message,
      'field' => $field,
      'status' => $status,
    ];
  }
}