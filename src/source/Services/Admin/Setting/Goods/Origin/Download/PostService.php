<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\Origin\Download;

use App\Lib\GusLib;
use App\Repositories\Admin\GoodsOriginRepository;
use App\Services\BaseService;
use Generator;

class PostService extends BaseService
{
  /**
   * 상품 원산지 다운로드 데이터를 생성합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    return [
      'filename' => '상품원산지_' . date('Ymd_His') . '.csv',
      'headers' => ['ID', '1단 원산지명', '2단 원산지명', '3단 원산지명', '정렬순서'],
      'rows' => $this->buildRows(),
    ];
  }

  /**
   * 상품 원산지 CSV 행을 생성합니다.
   *
   * @return Generator<array>
   */
  private function buildRows(): Generator
  {
    $repository = $this->container->get(GoodsOriginRepository::class);
    $lib = $this->container->get(GusLib::class);

    foreach ($repository->getGoodsOrigins() as $origin) {
      $level = (int) ($origin['level'] ?? 0);
      $depth1 = $level === 0 ? (string) ($origin['nm'] ?? '') : (string) ($origin['pathnm0'] ?? '');
      $depth2 = $level === 1 ? (string) ($origin['nm'] ?? '') : (string) ($origin['pathnm1'] ?? '');
      $depth3 = $level === 2 ? (string) ($origin['nm'] ?? '') : '';

      yield array_map(
        fn (mixed $value): string => $lib->escapeCsvValue($value),
        [
          (string) ($origin['id'] ?? ''),
          $depth1,
          $depth2,
          $depth3,
          (string) ((int) ($origin['sort'] ?? 0)),
        ]
      );
    }
  }
}