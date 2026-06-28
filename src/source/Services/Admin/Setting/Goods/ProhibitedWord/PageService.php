<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\ProhibitedWord;

use App\Repositories\Admin\RestrictedWordRepository;
use App\Services\BaseService;

class PageService extends BaseService
{
  private const DEFAULT_LIMIT = 10;

  /**
   * 금지단어 관리 화면 데이터를 반환합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $query = is_array($params['query'] ?? null) ? $params['query'] : [];
    $keyword = trim((string) ($query['keyword'] ?? ''));
    $page = $this->normalizePage($query['page'] ?? null);
    $limit = self::DEFAULT_LIMIT;
    $repository = $this->container->get(RestrictedWordRepository::class);
    $filters = ['keyword' => $keyword];
    $total = $repository->countRestrictedWords($filters);
    $totalPages = max(1, (int) ceil($total / $limit));

    if ($page > $totalPages) {
      $page = $totalPages;
    }

    $offset = ($page - 1) * $limit;

    return [
      'restricted_words' => array_map([$this, 'formatRestrictedWord'], $repository->getRestrictedWords($filters, $limit, $offset)),
      'search' => [
        'keyword' => $keyword,
      ],
      'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => $totalPages,
        'from' => $total === 0 ? 0 : $offset + 1,
        'to' => min($offset + $limit, $total),
        'pages' => $this->makePageNumbers($page, $totalPages),
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages,
        'prev_page' => max(1, $page - 1),
        'next_page' => min($totalPages, $page + 1),
      ],
    ];
  }

  /**
   * 페이지 번호를 보정합니다.
   *
   * @param mixed $value 요청 페이지 값
   *
   * @return int
   */
  private function normalizePage(mixed $value): int
  {
    $page = is_scalar($value) && preg_match('/^[0-9]+$/', (string) $value) === 1 ? (int) $value : 1;

    return max(1, $page);
  }

  /**
   * 화면에 노출할 페이지 번호 배열을 생성합니다.
   *
   * @param int $page 현재 페이지
   * @param int $totalPages 전체 페이지 수
   *
   * @return array
   */
  private function makePageNumbers(int $page, int $totalPages): array
  {
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);

    if ($end - $start < 4) {
      $start = max(1, $end - 4);
      $end = min($totalPages, $start + 4);
    }

    return range($start, $end);
  }

  /**
   * 화면 출력용 금지단어 값을 보정합니다.
   *
   * @param array $word 원본 데이터
   *
   * @return array
   */
  private function formatRestrictedWord(array $word): array
  {
    $word['memo'] = (string) ($word['memo'] ?? '');

    return $word;
  }
}
