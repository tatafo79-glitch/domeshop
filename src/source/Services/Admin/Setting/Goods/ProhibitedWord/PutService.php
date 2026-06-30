<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\ProhibitedWord;

use App\Repositories\Admin\RestrictedWordRepository;

class PutService extends PostService
{
  /**
   * 금지단어 수정 데이터를 검증하고 저장합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $id = (int) ($params['id'] ?? 0);
    if ($id < 1) {
      return $this->fail('수정할 금지단어를 선택해 주세요.', 'word');
    }

    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $normalized = $this->normalize($data);
    if (($normalized['success'] ?? false) !== true) {
      return $normalized;
    }

    $repository = $this->container->get(RestrictedWordRepository::class);
    if ($repository->getRestrictedWordById($id) === null) {
      return $this->fail('수정할 금지단어를 찾을 수 없습니다. 목록을 새로고침해 주세요.', 'word', 404);
    }

    if ($repository->existsRestrictedWord($normalized['data']['word_type'], $normalized['data']['normalized_word'], $id)) {
      return $this->fail('이미 등록된 금지단어입니다.', 'word', 409);
    }

    $repository->updateRestrictedWord($id, $normalized['data']);

    return [
      'success' => true,
      'message' => '금지단어가 수정되었습니다.',
      'data' => ['id' => $id],
    ];
  }
}
