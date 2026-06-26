<?php

declare(strict_types=1);

namespace App\Lib;

use InvalidArgumentException;

class GoodsLib
{
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