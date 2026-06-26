<?php

declare(strict_types=1);

namespace App\Repositories\Common;

use App\Repositories\BaseRepository;

class UploadRepository extends BaseRepository
{
  /**
   * 업로드 파일을 사용 중 상태로 표시합니다.
   *
   * @param string $filePath 파일 경로
   *
   * @return bool
   */
  public function markAsUsed(string $filePath): bool
  {
    return $this->db->execute(
      'UPDATE uploaded_files SET is_used = ? WHERE file_path = ?',
      [1, $filePath]
    );
  }

  /**
   * 업로드 파일을 미사용 상태로 표시합니다.
   *
   * @param string $filePath 파일 경로
   *
   * @return bool
   */
  public function markAsUnused(string $filePath): bool
  {
    return $this->db->execute(
      'UPDATE uploaded_files SET is_used = ? WHERE file_path = ?',
      [0, $filePath]
    );
  }
  /**
   * 업로드 파일 메타 정보를 저장합니다.
   *
   * @param array $data 업로드 파일 메타 데이터
   *
   * @return int
   */
  public function insertFile(array $data): int
  {
    $this->db->execute(
      'INSERT INTO uploaded_files (original_name, stored_name, file_path, file_size, mime_type, category, is_used, uploader_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
      [
        (string) ($data['original_name'] ?? ''),
        (string) ($data['stored_name'] ?? ''),
        (string) ($data['file_path'] ?? ''),
        (int) ($data['file_size'] ?? 0),
        (string) ($data['mime_type'] ?? ''),
        (string) ($data['category'] ?? ''),
        0,
        (string) ($data['uploader_name'] ?? 'SYSTEM'),
      ]
    );

    return (int) $this->db->lastInsertId();
  }
  /**
   * 사용 확정 전 업로드 파일이 장부에 존재하는지 확인합니다.
   *
   * @param string $filePath 파일 경로
   * @param string $category 업로드 분류
   *
   * @return bool
   */
  public function existsByPathAndCategory(string $filePath, string $category): bool
  {
    $row = $this->db->fetchRow(
      'SELECT id FROM uploaded_files WHERE file_path = ? AND category = ? AND is_used = ? LIMIT 1',
      [$filePath, $category, 0]
    );

    return $row !== null;
  }

}
