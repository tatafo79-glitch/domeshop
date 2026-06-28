<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use App\Repositories\BaseRepository;

class GoodsOriginRepository extends BaseRepository
{
  /**
   * 원산지 전체 목록을 조회합니다.
   *
   * @return array
   */
  public function getGoodsOrigins(): array
  {
    return $this->db->fetchAll(
      'SELECT id, nm, cd0, cd1, pathnm0, pathnm1, level, sort, last, create_at, update_at
        FROM goods_origins
        ORDER BY level ASC, cd0 ASC, cd1 ASC, sort ASC, id ASC'
    );
  }

  /**
   * 원산지 단건을 조회합니다.
   *
   * @param int $id 원산지 ID
   *
   * @return array|null
   */
  public function getGoodsOriginById(int $id): ?array
  {
    return $this->db->fetchRow(
      'SELECT id, nm, cd0, cd1, pathnm0, pathnm1, level, sort, last, create_at, update_at
        FROM goods_origins
        WHERE id = ?
        LIMIT 1',
      [$id]
    );
  }


  /**
   * 경로 기준으로 원산지 단건을 조회합니다.
   *
   * @param int $level 원산지 단계
   * @param string $name 원산지명
   * @param int|null $cd0 1차 상위 ID
   * @param int|null $cd1 2차 상위 ID
   *
   * @return array|null
   */
  public function getGoodsOriginByPath(int $level, string $name, ?int $cd0 = null, ?int $cd1 = null): ?array
  {
    $sql = 'SELECT id, nm, cd0, cd1, pathnm0, pathnm1, level, sort, last, create_at, update_at
      FROM goods_origins
      WHERE level = ? AND nm = ? AND ' . ($cd0 === null ? 'cd0 IS NULL' : 'cd0 = ?') . ' AND ' . ($cd1 === null ? 'cd1 IS NULL' : 'cd1 = ?') . '
      LIMIT 1';
    $params = [(string) $level, $name];

    if ($cd0 !== null) {
      $params[] = $cd0;
    }
    if ($cd1 !== null) {
      $params[] = $cd1;
    }

    return $this->db->fetchRow($sql, $params);
  }

  /**
   * 같은 단계와 상위 경로에 동일한 원산지명이 있는지 확인합니다.
   *
   * @param string $name 원산지명
   * @param int $level 단계
   * @param int|null $cd0 1차 상위 ID
   * @param int|null $cd1 2차 상위 ID
   * @param int|null $excludeId 수정 시 제외할 ID
   *
   * @return bool
   */
  public function existsOriginName(string $name, int $level, ?int $cd0, ?int $cd1, ?int $excludeId = null): bool
  {
    $sql = 'SELECT COUNT(*) AS cnt
      FROM goods_origins
      WHERE nm = ? AND level = ? AND ' . ($cd0 === null ? 'cd0 IS NULL' : 'cd0 = ?') . ' AND ' . ($cd1 === null ? 'cd1 IS NULL' : 'cd1 = ?');
    $params = [$name, (string) $level];

    if ($cd0 !== null) {
      $params[] = $cd0;
    }
    if ($cd1 !== null) {
      $params[] = $cd1;
    }

    if ($excludeId !== null && $excludeId > 0) {
      $sql .= ' AND id <> ?';
      $params[] = $excludeId;
    }

    $row = $this->db->fetchRow($sql, $params);

    return (int) ($row['cnt'] ?? 0) > 0;
  }

  /**
   * 하위 원산지가 존재하는지 확인합니다.
   *
   * @param int $id 원산지 ID
   * @param int $level 단계
   *
   * @return bool
   */
  public function hasChildren(int $id, int $level): bool
  {
    if ($level === 0) {
      $row = $this->db->fetchRow('SELECT COUNT(*) AS cnt FROM goods_origins WHERE cd0 = ?', [$id]);
    } elseif ($level === 1) {
      $row = $this->db->fetchRow('SELECT COUNT(*) AS cnt FROM goods_origins WHERE cd1 = ?', [$id]);
    } else {
      return false;
    }

    return (int) ($row['cnt'] ?? 0) > 0;
  }

  /**
   * 원산지를 등록합니다.
   *
   * @param array $data 저장할 데이터
   *
   * @return int
   */
  public function insertGoodsOrigin(array $data): int
  {
    $now = time();
    $this->db->execute(
      'INSERT INTO goods_origins (nm, cd0, cd1, pathnm0, pathnm1, level, sort, last, create_at, update_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
      [
        $data['nm'],
        $data['cd0'],
        $data['cd1'],
        $data['pathnm0'],
        $data['pathnm1'],
        (string) $data['level'],
        $data['sort'],
        $data['last'],
        $now,
        $now,
      ]
    );

    return (int) $this->db->lastInsertId();
  }

  /**
   * 원산지를 수정합니다.
   *
   * @param int $id 원산지 ID
   * @param array $data 저장할 데이터
   *
   * @return bool
   */
  public function updateGoodsOrigin(int $id, array $data): bool
  {
    return $this->db->execute(
      'UPDATE goods_origins
        SET nm = ?,
            cd0 = ?,
            cd1 = ?,
            pathnm0 = ?,
            pathnm1 = ?,
            level = ?,
            sort = ?,
            last = ?,
            update_at = ?
        WHERE id = ?',
      [
        $data['nm'],
        $data['cd0'],
        $data['cd1'],
        $data['pathnm0'],
        $data['pathnm1'],
        (string) $data['level'],
        $data['sort'],
        $data['last'],
        time(),
        $id,
      ]
    );
  }

  /**
   * 1차 원산지를 참조하는 하위 경로명을 동기화합니다.
   *
   * @param int $rootId 1차 원산지 ID
   * @param string $rootName 1차 원산지명
   *
   * @return bool
   */
  public function syncRootChildren(int $rootId, string $rootName): bool
  {
    return $this->db->execute(
      'UPDATE goods_origins SET pathnm0 = ?, update_at = ? WHERE cd0 = ?',
      [$rootName, time(), $rootId]
    );
  }

  /**
   * 2차 원산지를 참조하는 하위 경로를 동기화합니다.
   *
   * @param int $parentId 2차 원산지 ID
   * @param int $rootId 1차 원산지 ID
   * @param string $rootName 1차 원산지명
   * @param string $parentName 2차 원산지명
   *
   * @return bool
   */
  public function syncSecondDepthChildren(int $parentId, int $rootId, string $rootName, string $parentName): bool
  {
    return $this->db->execute(
      'UPDATE goods_origins SET cd0 = ?, pathnm0 = ?, pathnm1 = ?, update_at = ? WHERE cd1 = ?',
      [$rootId, $rootName, $parentName, time(), $parentId]
    );
  }

  /**
   * 선택한 원산지와 하위 원산지를 삭제합니다.
   *
   * @param int $id 원산지 ID
   * @param int $level 원산지 단계
   *
   * @return int
   */
  public function deleteGoodsOriginBranch(int $id, int $level): int
  {
    if ($level === 0) {
      $countRow = $this->db->fetchRow('SELECT COUNT(*) AS cnt FROM goods_origins WHERE id = ? OR cd0 = ?', [$id, $id]);
      $this->db->execute('DELETE FROM goods_origins WHERE id = ? OR cd0 = ?', [$id, $id]);
      return (int) ($countRow['cnt'] ?? 0);
    }

    if ($level === 1) {
      $countRow = $this->db->fetchRow('SELECT COUNT(*) AS cnt FROM goods_origins WHERE id = ? OR cd1 = ?', [$id, $id]);
      $this->db->execute('DELETE FROM goods_origins WHERE id = ? OR cd1 = ?', [$id, $id]);
      return (int) ($countRow['cnt'] ?? 0);
    }

    $countRow = $this->db->fetchRow('SELECT COUNT(*) AS cnt FROM goods_origins WHERE id = ?', [$id]);
    $this->db->execute('DELETE FROM goods_origins WHERE id = ?', [$id]);

    return (int) ($countRow['cnt'] ?? 0);
  }
}
