<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Setting\Goods\Origin\TemplateDownload;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Post extends Admin
{
  /**
   * 상품 원산지 업로드 양식 CSV 파일을 직접 스트리밍합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $result = $this->service->execute();
    $filename = (string) $result['filename'];

    while (ob_get_level() > 0) {
      ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"goods_origin_template.csv\"; filename*=UTF-8''" . rawurlencode($filename));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
      exit;
    }

    fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $result['headers']);
    foreach ($result['rows'] as $row) {
      fputcsv($output, $row);
    }

    fclose($output);
    exit;
  }
}