<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Repositories\BaseRepository;

class RestrictedWordRepository extends BaseRepository
{
  /**
   * 삭제되지 않은 금지단어 목록을 조회합니다.
   *
   * @param array $filters 검색 조건
   * @param int $limit 조회 개수
   * @param int $offset 시작 위치
   *
   * @return array
   */
  public function getRestrictedWords(array $filters = [], int $limit = 10, int $offset = 0): array
  {
    [$whereSql, $params] = $this->buildRestrictedWordSearchCondition($filters);
    $params[] = $limit;
    $params[] = $offset;

    return $this->db->fetchAll(
      'SELECT id, word, normalized_word, word_type, target_scope, match_type, is_active, memo
        FROM goods_restricted_words
        WHERE ' . $whereSql . '
        ORDER BY id DESC
        LIMIT ? OFFSET ?',
      $params
    );
  }

  /**
   * 삭제되지 않은 금지단어 개수를 조회합니다.
   *
   * @param array $filters 검색 조건
   *
   * @return int
   */
  public function countRestrictedWords(array $filters = []): int
  {
    [$whereSql, $params] = $this->buildRestrictedWordSearchCondition($filters);
    $row = $this->db->fetchRow(
      'SELECT COUNT(*) AS cnt
        FROM goods_restricted_words
        WHERE ' . $whereSql,
      $params
    );

    return (int) ($row['cnt'] ?? 0);
  }

  /**
   * 상품 등록 검사용 활성 단어 목록을 조회합니다.
   *
   * @return array
   */
  public function getActiveRestrictedWords(): array
  {
    return $this->db->fetchAll(
      'SELECT id, word, normalized_word, word_type, target_scope, match_type
        FROM goods_restricted_words
        WHERE is_active = ? AND deleted_at IS NULL
        ORDER BY word_type ASC, id ASC',
      ['Y']
    );
  }

  /**
   * 금지단어 단건을 조회합니다.
   *
   * @param int $id 금지단어 ID
   *
   * @return array|null
   */
  public function getRestrictedWordById(int $id): ?array
  {
    return $this->db->fetchRow(
      'SELECT id, word, normalized_word, word_type, target_scope, match_type, is_active, memo
        FROM goods_restricted_words
        WHERE id = ? AND deleted_at IS NULL
        LIMIT 1',
      [$id]
    );
  }

  /**
   * 동일 유형의 정규화 단어 중복 여부를 조회합니다.
   *
   * @param string $wordType 단어 유형
   * @param string $normalizedWord 정규화 단어
   * @param int|null $excludeId 수정 시 제외할 ID
   *
   * @return bool
   */
  public function existsRestrictedWord(string $wordType, string $normalizedWord, ?int $excludeId = null): bool
  {
    if ($excludeId !== null && $excludeId > 0) {
      $row = $this->db->fetchRow(
        'SELECT COUNT(*) AS cnt
          FROM goods_restricted_words
          WHERE word_type = ? AND normalized_word = ? AND id <> ? AND deleted_at IS NULL',
        [$wordType, $normalizedWord, $excludeId]
      );
    } else {
      $row = $this->db->fetchRow(
        'SELECT COUNT(*) AS cnt
          FROM goods_restricted_words
          WHERE word_type = ? AND normalized_word = ? AND deleted_at IS NULL',
        [$wordType, $normalizedWord]
      );
    }

    return (int) ($row['cnt'] ?? 0) > 0;
  }

  /**
   * 금지단어를 등록합니다.
   *
   * @param array $data 저장할 데이터
   *
   * @return int
   */
  public function insertRestrictedWord(array $data): int
  {
    $this->db->execute(
      'INSERT INTO goods_restricted_words (
          word, normalized_word, word_type, target_scope, match_type, is_active, memo
        ) VALUES (?, ?, ?, ?, ?, ?, ?)',
      [
        $data['word'],
        $data['normalized_word'],
        $data['word_type'],
        $data['target_scope'],
        $data['match_type'],
        $data['is_active'],
        $data['memo'],
      ]
    );

    return (int) $this->db->lastInsertId();
  }

  /**
   * 금지단어를 수정합니다.
   *
   * @param int $id 금지단어 ID
   * @param array $data 저장할 데이터
   *
   * @return bool
   */
  public function updateRestrictedWord(int $id, array $data): bool
  {
    return $this->db->execute(
      'UPDATE goods_restricted_words
        SET word = ?,
            normalized_word = ?,
            word_type = ?,
            target_scope = ?,
            match_type = ?,
            is_active = ?,
            memo = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND deleted_at IS NULL',
      [
        $data['word'],
        $data['normalized_word'],
        $data['word_type'],
        $data['target_scope'],
        $data['match_type'],
        $data['is_active'],
        $data['memo'],
        $id,
      ]
    );
  }

  /**
   * 금지단어를 소프트 삭제합니다.
   *
   * @param int $id 금지단어 ID
   *
   * @return bool
   */
  public function softDeleteRestrictedWord(int $id): bool
  {
    return $this->db->execute(
      'UPDATE goods_restricted_words
        SET deleted_at = CURRENT_TIMESTAMP,
            normalized_word = CONCAT(LEFT(normalized_word, 70), \'__deleted_\', id),
            is_active = ?,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = ? AND deleted_at IS NULL',
      ['N', $id]
    );
  }

  /**
   * 금지단어 검색 조건 SQL과 바인딩 값을 생성합니다.
   *
   * @param array $filters 검색 조건
   *
   * @return array{0:string,1:array}
   */
  private function buildRestrictedWordSearchCondition(array $filters): array
  {
    $conditions = ['deleted_at IS NULL'];
    $params = [];
    $keyword = trim((string) ($filters['keyword'] ?? ''));

    if ($keyword !== '') {
      $likeKeyword = '%' . $keyword . '%';
      $conditions[] = '(word LIKE ? OR normalized_word LIKE ? OR memo LIKE ?)';
      $params[] = $likeKeyword;
      $params[] = $likeKeyword;
      $params[] = $likeKeyword;
    }

    return [implode(' AND ', $conditions), $params];
  }
}
