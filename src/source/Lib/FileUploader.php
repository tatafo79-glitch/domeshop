<?php

declare(strict_types=1);

namespace App\Lib;

use App\Settings\SettingInterface;
use Psr\Http\Message\UploadedFileInterface;

class FileUploader
{
  private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
  private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
  private const MAX_IMAGE_SIZE = 5242880;

  /**
   * Method __construct
   *
   * @param SettingInterface $setting 설정 객체
   * @param SftpTransfer $sftpTransfer SFTP 전송 객체
   *
   * @return void
   */
  public function __construct(
    private readonly SettingInterface $setting,
    private readonly SftpTransfer $sftpTransfer
  ) {
  }

  /**
   * 이미지 파일을 검증한 뒤 CDN에 업로드합니다.
   *
   * @param UploadedFileInterface $file 업로드 파일
   * @param string $category 업로드 분류
   *
   * @return array<string, mixed>
   */
  public function uploadImage(UploadedFileInterface $file, string $category): array
  {
    $this->assertValidUpload($file);

    $originalName = $this->sanitizeOriginalName($file->getClientFilename() ?? 'image');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $this->assertAllowedExtension($extension);

    $tempPath = $this->createTempPath($extension);
    $file->moveTo($tempPath);

    try {
      $mimeType = $this->detectMimeType($tempPath);
      $this->assertAllowedMimeType($mimeType);

      $storedName = hash('sha256', uniqid('', true) . random_bytes(16)) . '.' . $extension;
      $datePath = date('Y/m/d');
      $filePath = trim($category, '/') . '/' . $datePath . '/' . $storedName;
      $cdnConfig = $this->setting->get('cdn_info') ?? [];
      $remotePath = rtrim((string) ($cdnConfig['save_path'] ?? ''), '/') . '/' . $filePath;
      $this->sftpTransfer->upload($cdnConfig, $tempPath, $remotePath);

      return [
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'file_path' => $filePath,
        'file_size' => filesize($tempPath) ?: 0,
        'mime_type' => $mimeType,
        'url' => $this->buildImageUrl((string) ($cdnConfig['image_path'] ?? ''), $filePath),
      ];
    } finally {
      if (is_file($tempPath)) {
        @unlink($tempPath);
      }
    }
  }

  /**
   * 업로드 실패 보상 처리를 위해 CDN 파일을 삭제합니다.
   *
   * @param string $filePath DB 저장 파일 경로
   *
   * @return void
   */
  public function deleteUploadedImage(string $filePath): void
  {
    if ($filePath === '') {
      return;
    }

    $cdnConfig = $this->setting->get('cdn_info') ?? [];
    $remotePath = rtrim((string) ($cdnConfig['save_path'] ?? ''), '/') . '/' . ltrim($filePath, '/');
    $this->sftpTransfer->delete($cdnConfig, $remotePath);
  }

  /**
   * 업로드 오류와 파일 크기를 검증합니다.
   *
   * @param UploadedFileInterface $file 업로드 파일
   *
   * @return void
   */
  private function assertValidUpload(UploadedFileInterface $file): void
  {
    if ($file->getError() !== UPLOAD_ERR_OK) {
      throw new \InvalidArgumentException('이미지 업로드 중 오류가 발생했습니다. 다시 선택해 주세요.');
    }

    if ($file->getSize() === null || $file->getSize() <= 0) {
      throw new \InvalidArgumentException('업로드할 이미지 파일을 선택해 주세요.');
    }

    if ($file->getSize() > self::MAX_IMAGE_SIZE) {
      throw new \InvalidArgumentException('이미지는 5MB 이하 파일만 업로드할 수 있습니다.');
    }
  }

  /**
   * 허용된 이미지 확장자인지 검증합니다.
   *
   * @param string $extension 파일 확장자
   *
   * @return void
   */
  private function assertAllowedExtension(string $extension): void
  {
    if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
      throw new \InvalidArgumentException('jpg, png, gif, webp 형식의 이미지만 업로드할 수 있습니다.');
    }
  }

  /**
   * 실제 파일 MIME 타입을 검증합니다.
   *
   * @param string $mimeType MIME 타입
   *
   * @return void
   */
  private function assertAllowedMimeType(string $mimeType): void
  {
    if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
      throw new \InvalidArgumentException('이미지 파일 형식이 올바르지 않습니다.');
    }
  }

  /**
   * 원본 파일명을 표시 가능한 안전한 값으로 정리합니다.
   *
   * @param string $fileName 원본 파일명
   *
   * @return string
   */
  private function sanitizeOriginalName(string $fileName): string
  {
    $baseName = basename(str_replace('\\', '/', $fileName));
    $baseName = preg_replace('/[^A-Za-z0-9가-힣._-]/u', '_', $baseName) ?: 'image';

    return mb_substr($baseName, 0, 255);
  }

  /**
   * 임시 저장 경로를 생성합니다.
   *
   * @param string $extension 파일 확장자
   *
   * @return string
   */
  private function createTempPath(string $extension): string
  {
    $tempPath = tempnam(sys_get_temp_dir(), 'domemall_upload_');
    if ($tempPath === false) {
      throw new \RuntimeException('임시 업로드 파일을 생성하지 못했습니다.');
    }

    $targetPath = $tempPath . '.' . $extension;
    if (!rename($tempPath, $targetPath)) {
      @unlink($tempPath);
      throw new \RuntimeException('임시 업로드 파일을 준비하지 못했습니다.');
    }

    return $targetPath;
  }

  /**
   * 실제 파일 내용을 기준으로 MIME 타입을 확인합니다.
   *
   * @param string $filePath 로컬 파일 경로
   *
   * @return string
   */
  private function detectMimeType(string $filePath): string
  {
    $finfo = new \finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($filePath);

    return is_string($mimeType) ? $mimeType : '';
  }

  /**
   * CDN 이미지 URL을 생성합니다.
   *
   * @param string $baseUrl CDN 이미지 기본 URL
   * @param string $filePath DB 저장 파일 경로
   *
   * @return string
   */
  private function buildImageUrl(string $baseUrl, string $filePath): string
  {
    if ($baseUrl === '') {
      return '/' . ltrim($filePath, '/');
    }

    return rtrim($baseUrl, '/') . '/' . ltrim($filePath, '/');
  }
}