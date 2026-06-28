<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\Origin\TemplateDownload;

use App\Services\BaseService;

class PostService extends BaseService
{
  /**
   * 상품 원산지 업로드용 CSV 양식을 생성합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    return [
      'filename' => '상품원산지_업로드양식.csv',
      'headers' => ['ID', '1단 원산지명', '2단 원산지명', '3단 원산지명', '정렬순서'],
      'rows' => [
        ['', '국내', '', '', '0'],
        ['', '국내', '경기도', '', '0'],
        ['', '국내', '경기도', '수원시', '0'],
      ],
    ];
  }
}