<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Member\AssetHistory\Download;

use App\Controllers\Actions\Admin;
use Generator;
use Psr\Http\Message\ResponseInterface as Response;

class Post extends Admin
{
  /**
   * 전체 회원 자산 이력 CSV 파일을 직접 스트리밍합니다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    set_time_limit(0);

    $result = $this->service->execute([
      'asset' => (string) $this->resolveArg('asset'),
      'form' => $this->getFormData(),
    ]);
    if (($result['success'] ?? true) === false) {
      return $this->errorResponse(
        (string) ($result['message'] ?? '자산 이력 다운로드 조건을 확인해 주세요.'),
        (int) ($result['status'] ?? 400),
        ['field' => $result['field'] ?? 'filters']
      );
    }

    $filename = (string) $result['filename'];
    /** @var Generator<array> $rows */
    $rows = $result['rows'];

    while (ob_get_level() > 0) {
      ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header("Content-Disposition: attachment; filename=\"member_asset_history.csv\"; filename*=UTF-8''" . rawurlencode($filename));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $output = fopen('php://output', 'wb');
    if ($output === false) {
      exit;
    }

    fwrite($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, $result['headers']);

    $rowCount = 0;
    foreach ($rows as $row) {
      fputcsv($output, $row);
      $rowCount++;

      if ($rowCount % 100 === 0) {
        ob_flush();
        flush();
      }
    }

    fclose($output);
    exit;
  }
}
