<?php

declare(strict_types=1);

namespace App\Services\Admin\Setting\Goods\Origin\Upload;

use App\Repositories\Admin\GoodsOriginRepository;
use App\Services\BaseService;
use Psr\Http\Message\UploadedFileInterface;
use Throwable;

class PostService extends BaseService
{
  private const MAX_FILE_SIZE = 2097152;
  private const MAX_ROWS = 5000;

  /**
   * 상품 원산지 CSV 파일을 읽어 등록 및 수정합니다.
   *
   * @param array $params 요청 파라미터
   *
   * @return array
   */
  public function execute(array $params = []): array
  {
    $file = $params['file'] ?? null;
    $validated = $this->validateFile($file);
    if (($validated['success'] ?? false) !== true) {
      return $validated;
    }

    $parsed = $this->parseCsv($file);
    if (($parsed['success'] ?? false) !== true) {
      return $parsed;
    }

    $repository = $this->container->get(GoodsOriginRepository::class);
    $createdCount = 0;
    $updatedCount = 0;
    $skippedCount = 0;

    $this->db->beginTransaction();
    try {
      $maps = $this->buildOriginMaps($repository->getGoodsOrigins());
      foreach ($parsed['rows'] as $row) {
        $result = $this->applyRow($repository, $maps, $row);
        if (($result['success'] ?? false) !== true) {
          $this->db->rollBack();
          return $result;
        }

        if (($result['action'] ?? '') === 'created') {
          $createdCount++;
        } elseif (($result['action'] ?? '') === 'updated') {
          $updatedCount++;
        } else {
          $skippedCount++;
        }

        $maps = $this->buildOriginMaps($repository->getGoodsOrigins());
      }
      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollBack();
      throw $e;
    }

    return [
      'success' => true,
      'message' => sprintf('원산지 업로드가 완료되었습니다. 등록 %d개, 수정 %d개, 건너뜀 %d개', $createdCount, $updatedCount, $skippedCount),
      'data' => [
        'created_count' => $createdCount,
        'updated_count' => $updatedCount,
        'skipped_count' => $skippedCount,
      ],
    ];
  }

  /**
   * 업로드 파일의 기본 조건을 검증합니다.
   *
   * @param mixed $file 업로드 파일
   *
   * @return array
   */
  private function validateFile(mixed $file): array
  {
    if (!$file instanceof UploadedFileInterface) {
      return $this->fail('업로드할 CSV 파일을 선택해 주세요.', 'origin_file');
    }
    if ($file->getError() !== UPLOAD_ERR_OK) {
      return $this->fail('파일 업로드 중 오류가 발생했습니다. 다시 선택해 주세요.', 'origin_file');
    }

    $size = $file->getSize() ?? 0;
    if ($size < 1 || $size > self::MAX_FILE_SIZE) {
      return $this->fail('CSV 파일은 2MB 이하로 업로드해 주세요.', 'origin_file');
    }

    $filename = (string) $file->getClientFilename();
    if (preg_match('/\.csv$/i', $filename) !== 1) {
      return $this->fail('CSV 파일만 업로드할 수 있습니다.', 'origin_file');
    }

    $mediaType = strtolower((string) $file->getClientMediaType());
    $allowedTypes = ['', 'text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/octet-stream'];
    if (!in_array($mediaType, $allowedTypes, true)) {
      return $this->fail('CSV 파일 형식을 확인해 주세요.', 'origin_file');
    }

    return ['success' => true];
  }

  /**
   * CSV 파일을 행 배열로 파싱합니다.
   *
   * @param UploadedFileInterface $file 업로드 파일
   *
   * @return array
   */
  private function parseCsv(UploadedFileInterface $file): array
  {
    $contents = $file->getStream()->getContents();
    if (!mb_check_encoding($contents, 'UTF-8')) {
      $encoding = mb_detect_encoding($contents, ['UTF-8', 'CP949', 'EUC-KR'], true) ?: 'CP949';
      $contents = mb_convert_encoding($contents, 'UTF-8', $encoding);
    }
    $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;

    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
      return $this->fail('CSV 파일을 읽을 수 없습니다.', 'origin_file');
    }
    fwrite($handle, $contents);
    rewind($handle);

    $headers = fgetcsv($handle);
    if ($headers === false) {
      fclose($handle);
      return $this->fail('CSV 헤더를 확인해 주세요.', 'origin_file');
    }

    $headerMap = $this->makeHeaderMap($headers);
    foreach (['depth1'] as $required) {
      if (!isset($headerMap[$required])) {
        fclose($handle);
        return $this->fail('CSV 헤더는 ID, 1단 원산지명, 2단 원산지명, 3단 원산지명, 정렬순서 형식으로 업로드해 주세요.', 'origin_file');
      }
    }

    $rows = [];
    $line = 1;
    while (($columns = fgetcsv($handle)) !== false) {
      $line++;
      if ($this->isEmptyCsvRow($columns)) {
        continue;
      }
      if (count($rows) >= self::MAX_ROWS) {
        fclose($handle);
        return $this->fail(sprintf('한 번에 업로드할 원산지는 %d개 이하로 입력해 주세요.', self::MAX_ROWS), 'origin_file');
      }

      $rows[] = $this->makeRow($columns, $headerMap, $line, count($rows));
    }
    fclose($handle);

    if ($rows === []) {
      return $this->fail('업로드할 원산지 데이터가 없습니다.', 'origin_file');
    }

    usort($rows, static fn (array $a, array $b): int => $a['level'] <=> $b['level'] ?: $a['order'] <=> $b['order']);

    return ['success' => true, 'rows' => $rows];
  }

  /**
   * CSV 헤더를 내부 필드명으로 매핑합니다.
   *
   * @param array $headers CSV 헤더
   *
   * @return array
   */
  private function makeHeaderMap(array $headers): array
  {
    $aliases = [
      'id' => ['id', 'ID'],
      'level' => ['단계', 'level'],
      'depth1' => ['1단원산지', '1단 원산지', '1단 원산지명', 'depth1'],
      'depth2' => ['2단원산지', '2단 원산지', '2단 원산지명', 'depth2'],
      'depth3' => ['3단원산지', '3단 원산지', '3단 원산지명', 'depth3'],
      'sort' => ['정렬순서', 'sort'],
    ];

    $map = [];
    foreach ($headers as $index => $header) {
      $normalizedHeader = $this->normalizeHeader((string) $header);
      foreach ($aliases as $field => $names) {
        foreach ($names as $name) {
          if ($normalizedHeader === $this->normalizeHeader($name)) {
            $map[$field] = $index;
          }
        }
      }
    }

    return $map;
  }

  /**
   * CSV 헤더 비교용 문자열로 정리합니다.
   *
   * @param string $header 헤더 문자열
   *
   * @return string
   */
  private function normalizeHeader(string $header): string
  {
    return mb_strtolower(preg_replace('/\s+/u', '', trim($header)) ?? '');
  }

  /**
   * CSV 빈 행 여부를 확인합니다.
   *
   * @param array $columns CSV 컬럼
   *
   * @return bool
   */
  private function isEmptyCsvRow(array $columns): bool
  {
    foreach ($columns as $column) {
      if (trim((string) $column) !== '') {
        return false;
      }
    }

    return true;
  }

  /**
   * CSV 컬럼을 원산지 처리 행으로 변환합니다.
   *
   * @param array $columns CSV 컬럼
   * @param array $headerMap 헤더 매핑
   * @param int $line 파일 줄 번호
   * @param int $order 원본 순서
   *
   * @return array
   */
  private function makeRow(array $columns, array $headerMap, int $line, int $order): array
  {
    $id = $this->column($columns, $headerMap, 'id');
    $levelText = $this->column($columns, $headerMap, 'level');
    $depth1 = $this->column($columns, $headerMap, 'depth1');
    $depth2 = $this->column($columns, $headerMap, 'depth2');
    $depth3 = $this->column($columns, $headerMap, 'depth3');
    $level = $this->normalizeLevel($levelText, $depth2, $depth3);

    return [
      'line' => $line,
      'order' => $order,
      'id' => $id,
      'level' => $level,
      'depth1' => $depth1,
      'depth2' => $depth2,
      'depth3' => $depth3,
      'sort' => $this->column($columns, $headerMap, 'sort'),
    ];
  }

  /**
   * CSV 컬럼 값을 가져옵니다.
   *
   * @param array $columns CSV 컬럼
   * @param array $headerMap 헤더 매핑
   * @param string $field 필드명
   *
   * @return string
   */
  private function column(array $columns, array $headerMap, string $field): string
  {
    $index = $headerMap[$field] ?? null;
    if ($index === null) {
      return '';
    }

    return trim((string) ($columns[$index] ?? ''));
  }

  /**
   * 원산지 단계를 정규화합니다.
   *
   * @param string $levelText 단계 문자열
   * @param string $depth2 2단 원산지명
   * @param string $depth3 3단 원산지명
   *
   * @return int
   */
  private function normalizeLevel(string $levelText, string $depth2, string $depth3): int
  {
    if (preg_match('/^[1-3]$/', $levelText) === 1) {
      return (int) $levelText - 1;
    }
    if ($depth3 !== '') {
      return 2;
    }
    if ($depth2 !== '') {
      return 1;
    }

    return 0;
  }

  /**
   * 현재 원산지 목록을 ID 및 경로 조회용 맵으로 변환합니다.
   *
   * @param array $origins 원산지 목록
   *
   * @return array
   */
  private function buildOriginMaps(array $origins): array
  {
    $maps = [
      'by_id' => [],
      'root_by_name' => [],
      'second_by_path' => [],
      'third_by_path' => [],
    ];

    foreach ($origins as $origin) {
      $id = (int) ($origin['id'] ?? 0);
      $level = (int) ($origin['level'] ?? 0);
      $maps['by_id'][$id] = $origin;
      if ($level === 0) {
        $maps['root_by_name'][$this->pathKey((string) ($origin['nm'] ?? ''))] = $origin;
      } elseif ($level === 1) {
        $maps['second_by_path'][$this->pathKey((string) ($origin['pathnm0'] ?? ''), (string) ($origin['nm'] ?? ''))] = $origin;
      } elseif ($level === 2) {
        $maps['third_by_path'][$this->pathKey((string) ($origin['pathnm0'] ?? ''), (string) ($origin['pathnm1'] ?? ''), (string) ($origin['nm'] ?? ''))] = $origin;
      }
    }

    return $maps;
  }

  /**
   * 한 행의 원산지를 등록 또는 수정합니다.
   *
   * @param GoodsOriginRepository $repository 원산지 저장소
   * @param array $maps 원산지 조회 맵
   * @param array $row CSV 행
   *
   * @return array
   */
  private function applyRow(GoodsOriginRepository $repository, array $maps, array $row): array
  {
    $current = null;
    $id = $this->normalizeId($row['id']);
    if (($id['success'] ?? false) !== true) {
      return $this->lineFail($row, (string) $id['message']);
    }
    if ($id['value'] > 0) {
      $current = $maps['by_id'][$id['value']] ?? null;
      if ($current === null) {
        return $this->lineFail($row, '수정할 원산지 ID를 찾을 수 없습니다.');
      }
    }

    $payload = $this->makePayload($maps, $row, $current);
    if (($payload['success'] ?? false) !== true) {
      return $this->lineFail($row, (string) $payload['message']);
    }
    $data = $payload['data'];

    if ($current !== null) {
      $updateId = (int) $current['id'];
      $currentLevel = (int) ($current['level'] ?? 0);
      $nextLevel = (int) $data['level'];
      if ($currentLevel !== $nextLevel && $repository->hasChildren($updateId, $currentLevel)) {
        return $this->lineFail($row, '하위 원산지가 있는 항목은 단계를 변경할 수 없습니다.');
      }
      if ($nextLevel > 0 && (int) ($data['cd0'] ?? 0) === $updateId) {
        return $this->lineFail($row, '자기 자신을 1단 상위 원산지로 지정할 수 없습니다.');
      }
      if ($nextLevel > 1 && (int) ($data['cd1'] ?? 0) === $updateId) {
        return $this->lineFail($row, '자기 자신을 2단 상위 원산지로 지정할 수 없습니다.');
      }
      if ($repository->existsOriginName((string) $data['nm'], (int) $data['level'], $data['cd0'], $data['cd1'], $updateId)) {
        return $this->lineFail($row, '같은 단계에 이미 등록된 원산지명입니다.');
      }
      $repository->updateGoodsOrigin($updateId, $data);
      $this->syncChildren($repository, $updateId, $data);
      return ['success' => true, 'action' => 'updated'];
    }

    $existing = $this->findExistingByPayload($maps, $data);
    if ($existing !== null) {
      $repository->updateGoodsOrigin((int) $existing['id'], $data);
      $this->syncChildren($repository, (int) $existing['id'], $data);
      return ['success' => true, 'action' => 'updated'];
    }

    if ($repository->existsOriginName((string) $data['nm'], (int) $data['level'], $data['cd0'], $data['cd1'])) {
      return ['success' => true, 'action' => 'skipped'];
    }

    $repository->insertGoodsOrigin($data);

    return ['success' => true, 'action' => 'created'];
  }

  /**
   * CSV 행을 저장 데이터로 변환합니다.
   *
   * @param array $maps 원산지 조회 맵
   * @param array $row CSV 행
   * @param array|null $current 현재 원산지
   *
   * @return array
   */
  private function makePayload(array $maps, array $row, ?array $current): array
  {
    $level = (int) $row['level'];
    $name = $this->resolveName($row, $level);
    if ($name === '' || mb_strlen($name) > 50) {
      return $this->fail('원산지명은 50자 이하로 입력해 주세요.', 'origin_file');
    }

    $sort = $this->normalizeSort($row['sort']);
    if (($sort['success'] ?? false) !== true) {
      return $sort;
    }

    if ($level === 0) {
      return ['success' => true, 'data' => [
        'nm' => $name,
        'cd0' => null,
        'cd1' => null,
        'pathnm0' => null,
        'pathnm1' => null,
        'level' => 0,
        'sort' => $sort['value'],
        'last' => 'N',
      ]];
    }

    $root = $this->resolveRoot($maps, $row['depth1'], $current);
    if ($root === null) {
      return $this->fail('1단 원산지를 찾을 수 없습니다. 1단 원산지를 먼저 등록해 주세요.', 'origin_file');
    }

    if ($level === 1) {
      return ['success' => true, 'data' => [
        'nm' => $name,
        'cd0' => (int) $root['id'],
        'cd1' => null,
        'pathnm0' => (string) $root['nm'],
        'pathnm1' => null,
        'level' => 1,
        'sort' => $sort['value'],
        'last' => 'N',
      ]];
    }

    $second = $this->resolveSecond($maps, (string) $root['nm'], $row['depth2'], $current);
    if ($second === null) {
      return $this->fail('2단 원산지를 찾을 수 없습니다. 2단 원산지를 먼저 등록해 주세요.', 'origin_file');
    }

    return ['success' => true, 'data' => [
      'nm' => $name,
      'cd0' => (int) $root['id'],
      'cd1' => (int) $second['id'],
      'pathnm0' => (string) $root['nm'],
      'pathnm1' => (string) $second['nm'],
      'level' => 2,
      'sort' => $sort['value'],
      'last' => 'Y',
    ]];
  }

  /**
   * 행에서 원산지명을 결정합니다.
   *
   * @param array $row CSV 행
   * @param int $level 원산지 단계
   *
   * @return string
   */
  private function resolveName(array $row, int $level): string
  {
    return $level === 0 ? (string) $row['depth1'] : ($level === 1 ? (string) $row['depth2'] : (string) $row['depth3']);
  }

  /**
   * 1단 원산지를 조회합니다.
   *
   * @param array $maps 원산지 조회 맵
   * @param string $name 1단 원산지명
   * @param array|null $current 현재 원산지
   *
   * @return array|null
   */
  private function resolveRoot(array $maps, string $name, ?array $current): ?array
  {
    $root = $name !== '' ? ($maps['root_by_name'][$this->pathKey($name)] ?? null) : null;
    if ($root !== null) {
      return $root;
    }
    $rootId = $current === null ? 0 : (int) ($current['cd0'] ?? 0);

    return $rootId > 0 ? ($maps['by_id'][$rootId] ?? null) : null;
  }

  /**
   * 2단 원산지를 조회합니다.
   *
   * @param array $maps 원산지 조회 맵
   * @param string $rootName 1단 원산지명
   * @param string $name 2단 원산지명
   * @param array|null $current 현재 원산지
   *
   * @return array|null
   */
  private function resolveSecond(array $maps, string $rootName, string $name, ?array $current): ?array
  {
    $second = $name !== '' ? ($maps['second_by_path'][$this->pathKey($rootName, $name)] ?? null) : null;
    if ($second !== null) {
      return $second;
    }
    $secondId = $current === null ? 0 : (int) ($current['cd1'] ?? 0);

    return $secondId > 0 ? ($maps['by_id'][$secondId] ?? null) : null;
  }

  /**
   * 저장 데이터와 같은 기존 원산지를 찾습니다.
   *
   * @param array $maps 원산지 조회 맵
   * @param array $data 저장 데이터
   *
   * @return array|null
   */
  private function findExistingByPayload(array $maps, array $data): ?array
  {
    $level = (int) $data['level'];
    if ($level === 0) {
      return $maps['root_by_name'][$this->pathKey((string) $data['nm'])] ?? null;
    }
    if ($level === 1) {
      return $maps['second_by_path'][$this->pathKey((string) $data['pathnm0'], (string) $data['nm'])] ?? null;
    }

    return $maps['third_by_path'][$this->pathKey((string) $data['pathnm0'], (string) $data['pathnm1'], (string) $data['nm'])] ?? null;
  }

  /**
   * 하위 원산지 경로명을 동기화합니다.
   *
   * @param GoodsOriginRepository $repository 원산지 저장소
   * @param int $id 원산지 ID
   * @param array $data 저장 데이터
   *
   * @return void
   */
  private function syncChildren(GoodsOriginRepository $repository, int $id, array $data): void
  {
    if ((int) $data['level'] === 0) {
      $repository->syncRootChildren($id, (string) $data['nm']);
    }
    if ((int) $data['level'] === 1) {
      $repository->syncSecondDepthChildren($id, (int) $data['cd0'], (string) $data['pathnm0'], (string) $data['nm']);
    }
  }

  /**
   * ID 값을 정수로 검증합니다.
   *
   * @param string $value ID 문자열
   *
   * @return array
   */
  private function normalizeId(string $value): array
  {
    if ($value === '') {
      return ['success' => true, 'value' => 0];
    }
    if (preg_match('/^\d+$/', $value) !== 1) {
      return ['success' => false, 'message' => 'ID는 숫자로 입력해 주세요.'];
    }

    return ['success' => true, 'value' => (int) $value];
  }

  /**
   * 정렬 순서를 정수로 검증합니다.
   *
   * @param string $value 정렬 순서 문자열
   *
   * @return array
   */
  private function normalizeSort(string $value): array
  {
    $sortValue = str_replace(',', '', trim($value === '' ? '0' : $value));
    if (preg_match('/^\d+$/', $sortValue) !== 1 || (int) $sortValue > 999999) {
      return $this->fail('정렬 순서는 0~999999 사이의 숫자로 입력해 주세요.', 'origin_file');
    }

    return ['success' => true, 'value' => (int) $sortValue];
  }

  /**
   * 경로 비교 키를 생성합니다.
   *
   * @param string ...$parts 경로 조각
   *
   * @return string
   */
  private function pathKey(string ...$parts): string
  {
    return mb_strtolower(implode('>', array_map(static fn (string $part): string => trim($part), $parts)));
  }

  /**
   * 줄 번호를 포함한 실패 응답을 생성합니다.
   *
   * @param array $row CSV 행
   * @param string $message 오류 메시지
   *
   * @return array
   */
  private function lineFail(array $row, string $message): array
  {
    return $this->fail(sprintf('%d행: %s', (int) $row['line'], $message), 'origin_file');
  }

  /**
   * 실패 응답 배열을 생성합니다.
   *
   * @param string $message 오류 메시지
   * @param string $field 오류 필드
   * @param int $status HTTP 상태 코드
   *
   * @return array
   */
  private function fail(string $message, string $field, int $status = 400): array
  {
    return [
      'success' => false,
      'message' => $message,
      'field' => $field,
      'status' => $status,
    ];
  }
}