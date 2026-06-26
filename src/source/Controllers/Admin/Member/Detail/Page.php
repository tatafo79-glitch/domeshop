<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Member\Detail;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Page extends Admin
{
  /**
   * 회원 상세 화면을 렌더링합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $id = (int) $this->resolveArg('id');
    $result = $this->service->execute(['id' => $id]);
    $result['previous_url'] = $this->resolvePreviousUrl();

    return $this->render('member/detail', $result);
  }
  /**
   * 상세 화면 진입 전 요청 URL을 안전한 내부 경로로 정규화합니다.
   *
   * @return string
   */
  private function resolvePreviousUrl(): string
  {
    $adminDir = (string) ($this->container->get('settings')['site']['admin_directory'] ?? 'dmmt');
    $fallbackUrl = '/' . trim($adminDir, '/') . '/member/lists';
    $query = $this->getQueryData();
    $returnUrl = $this->normalizeInternalUrl((string) ($query['return_url'] ?? ''));

    if ($returnUrl !== '') {
      return $returnUrl;
    }

    $refererUrl = $this->normalizeInternalUrl($this->request->getHeaderLine('Referer'));
    if ($refererUrl !== '' && !$this->isCurrentDetailUrl($refererUrl)) {
      return $refererUrl;
    }

    return $fallbackUrl;
  }

  /**
   * 외부 URL 또는 비정상 경로를 제거하고 내부 경로만 반환합니다.
   *
   * @param string $url 검사할 URL
   *
   * @return string
   */
  private function normalizeInternalUrl(string $url): string
  {
    $url = trim($url);
    if ($url === '') {
      return '';
    }

    $parts = parse_url($url);
    if ($parts === false) {
      return '';
    }

    $host = (string) ($parts['host'] ?? '');
    if ($host !== '' && strcasecmp($host, $this->request->getUri()->getHost()) !== 0) {
      return '';
    }

    $path = (string) ($parts['path'] ?? '');
    if ($path === '' || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
      return '';
    }

    $query = (string) ($parts['query'] ?? '');

    return $query === '' ? $path : $path . '?' . $query;
  }

  /**
   * URL이 현재 회원 상세 경로인지 확인합니다.
   *
   * @param string $url 검사할 내부 URL
   *
   * @return bool
   */
  private function isCurrentDetailUrl(string $url): bool
  {
    $currentPath = $this->request->getUri()->getPath();
    $targetPath = (string) (parse_url($url, PHP_URL_PATH) ?: '');

    return $targetPath === $currentPath;
  }
}
