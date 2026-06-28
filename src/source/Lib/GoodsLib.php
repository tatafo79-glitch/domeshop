<?php

declare(strict_types=1);

namespace App\Lib;

use InvalidArgumentException;

class GoodsLib
{
  private const WORD_TYPE_LABELS = [
    'PROHIBITED' => '금지어',
    'ADULT' => '성인단어',
  ];

  private const FIELD_LABELS = [
    'name' => '상품명',
    'manufacturer' => '제조사',
    'keywordTagInput' => '상품키워드',
  ];

  /**
   * 상품명과 키워드에서 FULLTEXT 검색용 suffix 토큰을 생성합니다.
   *
   * @param string $text 인덱싱할 문자열
   * @param int $minLength 검색 대응 최소 글자 수
   *
   * @return string
   */
  public function createSearchIndex(string $text, int $minLength = 2): string
  {
    $normalized = $this->normalizeSearchText($text);
    if ($normalized === '') {
      return '';
    }

    $length = mb_strlen($normalized, 'UTF-8');
    if ($length < $minLength) {
      return '';
    }

    $tokens = [];
    $tokenLength = $minLength;
    for ($i = $minLength; $i <= $length; $i++) {
      $tokens[] = mb_substr($normalized, $length - $i, $tokenLength, 'UTF-8');
      $tokenLength++;
    }

    return implode(' ', array_values(array_unique($tokens)));
  }

  /**
   * 사용자 검색어를 BOOLEAN MODE 검색 문자열로 변환합니다.
   *
   * @param string $keyword 검색어
   * @param int $minLength 검색 허용 최소 글자 수
   *
   * @return string
   */
  public function createBooleanSearchKeyword(string $keyword, int $minLength = 2): string
  {
    $parts = preg_split('@(<[^<>]+>|&[a-z\d]+;|\pC|\pM|\pP|\pS|\pZ)+@u', $keyword) ?: [];
    $tokens = [];

    foreach ($parts as $index => $part) {
      $normalized = $this->normalizeSearchText($part);
      if ($normalized === '') {
        continue;
      }

      $next = $parts[$index + 1] ?? '';
      $normalizedNext = $this->normalizeSearchText($next);
      if ($normalizedNext !== '' && mb_strlen($normalizedNext, 'UTF-8') === 1) {
        $normalized .= $normalizedNext;
      }

      if (mb_strlen($normalized, 'UTF-8') >= $minLength) {
        $tokens[] = '+' . $normalized . '*';
      }
    }

    return implode(' ', array_values(array_unique($tokens)));
  }

  /**
   * 검색 컬럼 allowlist를 검증하고 MATCH AGAINST 조건과 바인딩 값을 반환합니다.
   *
   * @param string $keyword 검색어
   * @param string $columnName FULLTEXT 검색 컬럼명
   *
   * @return array{sql:string, params:array<int,string>}
   */
  public function getSearchMatchCondition(string $keyword, string $columnName = 'search_text'): array
  {
    $allowedColumns = ['search_text'];
    if (!in_array($columnName, $allowedColumns, true)) {
      throw new InvalidArgumentException('허용되지 않은 상품 검색 컬럼입니다.');
    }

    $booleanKeyword = $this->createBooleanSearchKeyword($keyword);
    if ($booleanKeyword === '') {
      return [
        'sql' => '',
        'params' => [],
      ];
    }

    return [
      'sql' => 'MATCH(' . $columnName . ') AGAINST(? IN BOOLEAN MODE)',
      'params' => [$booleanKeyword],
    ];
  }

  /**
   * 금지단어 비교에 사용할 문자열을 정규화합니다.
   *
   * @param string $text 원문
   *
   * @return string
   */
  public function normalizeRestrictedWordText(string $text): string
  {
    $decoded = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $decoded = str_replace("\xc2\xa0", ' ', $decoded);
    $normalized = preg_replace('/\s+/u', ' ', mb_strtolower(trim($decoded), 'UTF-8'));

    return is_string($normalized) ? $normalized : '';
  }

  /**
   * 상품명, 제조사, 키워드에서 금지단어 위반 항목을 찾습니다.
   *
   * @param string $goodsName 상품명
   * @param string $manufacturer 제조사
   * @param string $searchKeywords 상품 검색 키워드
   * @param array $restrictedWords 활성 금지단어 목록
   *
   * @return array|null
   */
  public function findRestrictedWordViolation(string $goodsName, string $manufacturer, string $searchKeywords, array $restrictedWords): ?array
  {
    $fields = [
      'NAME' => [
        'field' => 'name',
        'value' => $this->normalizeRestrictedWordText($goodsName),
        'tokens' => $this->createRestrictedWordTokens($goodsName),
      ],
      'MANUFACTURER' => [
        'field' => 'manufacturer',
        'value' => $this->normalizeRestrictedWordText($manufacturer),
        'tokens' => $this->createRestrictedWordTokens($manufacturer),
      ],
      'KEYWORD' => [
        'field' => 'keywordTagInput',
        'value' => $this->normalizeRestrictedWordText($searchKeywords),
        'tokens' => $this->createRestrictedKeywordTokens($searchKeywords),
      ],
    ];

    foreach ($restrictedWords as $word) {
      $targetScope = (string) ($word['target_scope'] ?? 'BOTH');
      $targets = match ($targetScope) {
        'BOTH' => ['NAME', 'MANUFACTURER', 'KEYWORD'],
        'NAME_KEYWORD' => ['NAME', 'KEYWORD'],
        default => [$targetScope],
      };
      $normalizedWord = $this->normalizeRestrictedWordText((string) ($word['normalized_word'] ?? $word['word'] ?? ''));
      if ($normalizedWord === '') {
        continue;
      }

      foreach ($targets as $target) {
        if (!isset($fields[$target])) {
          continue;
        }

        $field = $fields[$target];
        if ($this->matchesRestrictedWord($field['value'], $field['tokens'], $normalizedWord, (string) ($word['match_type'] ?? 'CONTAINS'))) {
          $fieldName = (string) $field['field'];
          $typeLabel = self::WORD_TYPE_LABELS[(string) ($word['word_type'] ?? '')] ?? '금지단어';
          $fieldLabel = self::FIELD_LABELS[$fieldName] ?? '입력값';

          return [
            'success' => false,
            'message' => $fieldLabel . '에 ' . $typeLabel . ' "' . (string) ($word['word'] ?? '') . '"이 포함되어 있습니다.',
            'field' => $fieldName,
            'word' => (string) ($word['word'] ?? ''),
            'word_type' => (string) ($word['word_type'] ?? ''),
          ];
        }
      }
    }

    return null;
  }

  /**
   * 금지단어 매칭 방식을 적용합니다.
   *
   * @param string $value 검사 대상 문자열
   * @param array $tokens 단어 단위 토큰
   * @param string $word 금지단어
   * @param string $matchType 매칭 방식
   *
   * @return bool
   */
  private function matchesRestrictedWord(string $value, array $tokens, string $word, string $matchType): bool
  {
    if ($value === '') {
      return false;
    }

    if ($matchType === 'EXACT') {
      return $value === $word;
    }

    if ($matchType === 'WORD') {
      return in_array($word, $tokens, true);
    }

    return mb_strpos($value, $word, 0, 'UTF-8') !== false;
  }

  /**
   * 상품명에서 단어 일치 검사용 토큰을 생성합니다.
   *
   * @param string $text 원문
   *
   * @return array
   */
  private function createRestrictedWordTokens(string $text): array
  {
    $parts = preg_split('@(<[^<>]+>|&[a-z\d]+;|\pC|\pM|\pP|\pS|\pZ)+@u', $text) ?: [];

    return array_values(array_unique(array_filter(array_map(
      fn (string $part): string => $this->normalizeRestrictedWordText($part),
      $parts
    ), static fn (string $part): bool => $part !== '')));
  }

  /**
   * 검색 키워드에서 단어 일치 검사용 토큰을 생성합니다.
   *
   * @param string $keywords 콤마 구분 키워드
   *
   * @return array
   */
  private function createRestrictedKeywordTokens(string $keywords): array
  {
    $parts = preg_split('/[,]+/u', $keywords) ?: [];

    return array_values(array_unique(array_filter(array_map(
      fn (string $part): string => $this->normalizeRestrictedWordText($part),
      $parts
    ), static fn (string $part): bool => $part !== '')));
  }

  /**
   * 검색 인덱싱과 검색어 비교에 동일한 정규화 규칙을 적용합니다.
   *
   * @param string $text 원문
   *
   * @return string
   */
  private function normalizeSearchText(string $text): string
  {
    $parts = preg_split('@(<[^<>]+>|&[a-z\d]+;|\pC|\pM|\pP|\pS|\pZ)+@u', mb_strtolower($text, 'UTF-8')) ?: [];

    return implode('', array_filter($parts, static fn (string $part): bool => $part !== ''));
  }
}
