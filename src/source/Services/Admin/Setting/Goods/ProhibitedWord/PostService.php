<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\ProhibitedWord;

use App\Lib\GoodsLib;
use App\Repositories\Admin\RestrictedWordRepository;
use App\Services\BaseService;
use Throwable;

class PostService extends BaseService
{
  private const WORD_TYPES = ['PROHIBITED', 'ADULT'];
  private const TARGET_SCOPES = ['NAME', 'MANUFACTURER', 'KEYWORD', 'NAME_KEYWORD', 'BOTH'];
  private const MATCH_TYPES = ['CONTAINS', 'EXACT', 'WORD'];
  private const YES_NO_VALUES = ['Y', 'N'];
  private const BATCH_LIMIT = 1000;

  /**
   * 금지단어 등록 데이터를 검증하고 저장합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $normalized = $this->normalizeMany($data);
    if (($normalized['success'] ?? false) !== true) {
      return $normalized;
    }

    $repository = $this->container->get(RestrictedWordRepository::class);
    $createdIds = [];
    $skippedCount = 0;

    $this->db->beginTransaction();
    try {
      foreach ($normalized['items'] as $item) {
        if ($repository->existsRestrictedWord($item['word_type'], $item['normalized_word'])) {
          $skippedCount++;
          continue;
        }

        $createdIds[] = $repository->insertRestrictedWord($item);
      }
      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    $createdCount = count($createdIds);
    if ($createdCount < 1) {
      return $this->fail('입력한 단어가 모두 이미 등록된 금지단어입니다.', 'word', 409);
    }

    return [
      'success' => true,
      'message' => $skippedCount > 0
        ? sprintf('금지단어 %d개가 등록되었습니다. 중복 %d개는 제외되었습니다.', $createdCount, $skippedCount)
        : sprintf('금지단어 %d개가 등록되었습니다.', $createdCount),
      'data' => [
        'ids' => $createdIds,
        'created_count' => $createdCount,
        'skipped_count' => $skippedCount,
      ],
    ];
  }

  /**
   * 여러 금지단어 입력값을 저장 가능한 구조로 정규화합니다.
   *
   * @param array $data 입력 데이터
   *
   * @return array
   */
  protected function normalizeMany(array $data): array
  {
    $base = $this->normalizeBase($data);
    if (($base['success'] ?? false) !== true) {
      return $base;
    }

    $rawWord = trim((string) ($data['word'] ?? ''));
    if ($rawWord === '') {
      return $this->fail('단어를 입력해 주세요.', 'word');
    }

    $words = $this->splitWords($rawWord);
    if ($words === []) {
      return $this->fail('검사 가능한 단어를 입력해 주세요.', 'word');
    }
    if (count($words) > self::BATCH_LIMIT) {
      return $this->fail(sprintf('한 번에 등록할 단어는 %d개 이하로 입력해 주세요.', self::BATCH_LIMIT), 'word');
    }

    $goodsLib = $this->container->get(GoodsLib::class);
    $items = [];
    $seen = [];

    foreach ($words as $word) {
      if (mb_strlen($word) > 100) {
        return $this->fail('각 단어는 100자 이하로 입력해 주세요.', 'word');
      }

      $normalizedWord = $goodsLib->normalizeRestrictedWordText($word);
      if ($normalizedWord === '' || isset($seen[$normalizedWord])) {
        continue;
      }

      $seen[$normalizedWord] = true;
      $items[] = $this->makeRestrictedWordData($word, $normalizedWord, $base['data']);
    }

    if ($items === []) {
      return $this->fail('검사 가능한 단어를 입력해 주세요.', 'word');
    }

    return [
      'success' => true,
      'items' => $items,
    ];
  }

  /**
   * 금지단어 단건 입력값을 저장 가능한 구조로 정규화합니다.
   *
   * @param array $data 입력 데이터
   *
   * @return array
   */
  protected function normalize(array $data): array
  {
    $base = $this->normalizeBase($data);
    if (($base['success'] ?? false) !== true) {
      return $base;
    }

    $words = $this->splitWords((string) ($data['word'] ?? ''));
    if (count($words) !== 1) {
      return $this->fail('수정할 단어는 하나만 입력해 주세요.', 'word');
    }

    $word = $words[0];
    if (mb_strlen($word) > 100) {
      return $this->fail('단어는 100자 이하로 입력해 주세요.', 'word');
    }

    $goodsLib = $this->container->get(GoodsLib::class);
    $normalizedWord = $goodsLib->normalizeRestrictedWordText($word);
    if ($normalizedWord === '') {
      return $this->fail('검사 가능한 단어를 입력해 주세요.', 'word');
    }

    return [
      'success' => true,
      'data' => $this->makeRestrictedWordData($word, $normalizedWord, $base['data']),
    ];
  }

  /**
   * 단어 외 공통 입력값을 검증합니다.
   *
   * @param array $data 입력 데이터
   *
   * @return array
   */
  private function normalizeBase(array $data): array
  {
    $wordType = (string) ($data['word_type'] ?? '');
    if (!in_array($wordType, self::WORD_TYPES, true)) {
      return $this->fail('단어 유형을 올바르게 선택해 주세요.', 'word_type');
    }

    $targetScope = (string) ($data['target_scope'] ?? '');
    if (!in_array($targetScope, self::TARGET_SCOPES, true)) {
      return $this->fail('검사 대상을 올바르게 선택해 주세요.', 'target_scope');
    }

    $matchType = (string) ($data['match_type'] ?? '');
    if (!in_array($matchType, self::MATCH_TYPES, true)) {
      return $this->fail('매칭 방식을 올바르게 선택해 주세요.', 'match_type');
    }

    $isActive = (string) ($data['is_active'] ?? '');
    if (!in_array($isActive, self::YES_NO_VALUES, true)) {
      return $this->fail('사용 여부를 올바르게 선택해 주세요.', 'is_active');
    }

    $memo = trim((string) ($data['memo'] ?? ''));
    if (mb_strlen($memo) > 255) {
      return $this->fail('메모는 255자 이하로 입력해 주세요.', 'memo');
    }

    return [
      'success' => true,
      'data' => [
        'word_type' => $wordType,
        'target_scope' => $targetScope,
        'match_type' => $matchType,
        'is_active' => $isActive,
        'memo' => $memo === '' ? null : $memo,
      ],
    ];
  }

  /**
   * 쉼표와 개행 기준으로 단어를 분리합니다.
   *
   * @param string $wordText 원본 단어 입력값
   *
   * @return array
   */
  private function splitWords(string $wordText): array
  {
    return array_values(array_filter(
      array_map('trim', preg_split('/[,，\r\n]+/u', $wordText) ?: []),
      static fn (string $word): bool => $word !== ''
    ));
  }

  /**
   * 저장할 금지단어 데이터를 생성합니다.
   *
   * @param string $word 원본 단어
   * @param string $normalizedWord 정규화 단어
   * @param array $baseData 공통 저장 데이터
   *
   * @return array
   */
  private function makeRestrictedWordData(string $word, string $normalizedWord, array $baseData): array
  {
    return [
      'word' => $word,
      'normalized_word' => $normalizedWord,
      'word_type' => $baseData['word_type'],
      'target_scope' => $baseData['target_scope'],
      'match_type' => $baseData['match_type'],
      'is_active' => $baseData['is_active'],
      'memo' => $baseData['memo'],
    ];
  }

  /**
   * 실패 응답 배열을 생성합니다.
   *
   * @param string $message 오류 안내 문구
   * @param string $field 오류 필드명
   * @param int $status HTTP 상태 코드
   *
   * @return array
   */
  protected function fail(string $message, string $field, int $status = 400): array
  {
    return [
      'success' => false,
      'message' => $message,
      'field' => $field,
      'status' => $status,
    ];
  }
}
