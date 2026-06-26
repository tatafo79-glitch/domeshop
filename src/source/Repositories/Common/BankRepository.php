<?php

declare(strict_types=1);

namespace App\Repositories\Common;

use App\Repositories\BaseRepository;

class BankRepository extends BaseRepository
{
  /**
   * 활성 은행 목록을 조회합니다.
   *
   * @return array
   */
  public function getActiveBanks(): array
  {
    return $this->db->fetchAll(
      'SELECT id, name FROM banks WHERE is_active = ? ORDER BY sort_order ASC, name ASC',
      [1]
    );
  }

  /**
   * 활성 은행명인지 확인합니다.
   *
   * @param string $bankName 은행명
   *
   * @return bool
   */
  public function activeBankExists(string $bankName): bool
  {
    $row = $this->db->fetchRow(
      'SELECT id FROM banks WHERE name = ? AND is_active = ? LIMIT 1',
      [$bankName, 1]
    );

    return $row !== null;
  }
}