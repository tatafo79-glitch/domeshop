<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\ProhibitedWord\Delete;

use App\Repositories\Admin\RestrictedWordRepository;
use App\Services\BaseService;

class PostService extends BaseService
{
  /**
   * 금지단어를 삭제 처리합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $id = (int) ($params['id'] ?? 0);
    if ($id < 1) {
      return $this->fail('삭제할 금지단어를 선택해 주세요.', 400);
    }

    $repository = $this->container->get(RestrictedWordRepository::class);
    if ($repository->getRestrictedWordById($id) === null) {
      return $this->fail('삭제할 금지단어를 찾을 수 없습니다. 목록을 새로고침해 주세요.', 404);
    }

    $repository->softDeleteRestrictedWord($id);

    return [
      'success' => true,
      'message' => '금지단어가 삭제되었습니다.',
    ];
  }

  /**
   * 실패 응답 배열을 생성합니다.
   *
   * @param string $message 오류 안내 문구
   * @param int $status HTTP 상태 코드
   *
   * @return array
   */
  private function fail(string $message, int $status): array
  {
    return [
      'success' => false,
      'message' => $message,
      'status' => $status,
    ];
  }
}
