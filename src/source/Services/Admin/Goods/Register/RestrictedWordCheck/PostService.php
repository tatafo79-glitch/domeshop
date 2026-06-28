<?php

declare(strict_types=1);

namespace App\Services\Admin\Goods\Register\RestrictedWordCheck;

use App\Lib\GoodsLib;
use App\Repositories\Admin\RestrictedWordRepository;
use App\Services\BaseService;

class PostService extends BaseService
{
  /**
   * 상품명, 제조사, 검색 키워드의 금지단어 포함 여부를 검사합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $data = is_array($params['data'] ?? null) ? $params['data'] : [];
    $name = trim((string) ($data['name'] ?? ''));
    $manufacturer = trim((string) ($data['manufacturer'] ?? ''));
    $keywords = trim((string) ($data['search_keywords'] ?? ''));

    $repository = $this->container->get(RestrictedWordRepository::class);
    $goodsLib = $this->container->get(GoodsLib::class);
    $violation = $goodsLib->findRestrictedWordViolation($name, $manufacturer, $keywords, $repository->getActiveRestrictedWords());
    if ($violation !== null) {
      $violation['status'] = 400;

      return $violation;
    }

    return [
      'success' => true,
      'message' => '등록 가능한 상품명, 제조사와 키워드입니다.',
    ];
  }
}
