<?php

declare(strict_types=1);

namespace App\Repositories\Admin;

use Generator;
use App\Repositories\BaseRepository;

class MemberRepository extends BaseRepository
{
  /**
   * ?뚯썝 ?듦퀎 ?뺣낫瑜?議고쉶?⑸땲??
   *
   * @return array
   */
  public function getMemberSummary(): array
  {
    $row = $this->db->fetchRow(
      'SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) AS seller,
        SUM(CASE WHEN role = ? THEN 1 ELSE 0 END) AS vendor,
        SUM(CASE WHEN approval_status = ? THEN 1 ELSE 0 END) AS pending
      FROM members',
      ['SELLER', 'VENDOR', 'PENDING']
    ) ?? [];

    return [
      'total' => (int) ($row['total'] ?? 0),
      'seller' => (int) ($row['seller'] ?? 0),
      'vendor' => (int) ($row['vendor'] ?? 0),
      'pending' => (int) ($row['pending'] ?? 0),
    ];
  }

  /**
   * 회원 목록을 조회합니다.
   *
   * @param array $filters 검색 조건
   * @param int $limit 페이지당 표시 개수
   * @param int $offset 조회 시작 위치
   *
   * @return array
   */
  public function getMemberList(array $filters, int $limit, int $offset): array
  {
    [$whereSql, $params] = $this->buildMemberWhere($filters);

    $sql = 'SELECT id, role, vendor_code, company_name, user_id, name, mobile, deposit, mileage, approval_status, status, created_at, last_login_at
      FROM members'
      . $whereSql
      . ' ORDER BY id DESC LIMIT ? OFFSET ?';

    $params[] = $limit;
    $params[] = $offset;

    return $this->db->fetchAll($sql, $params);
  }

  /**
   * 회원 목록 전체 개수를 조회합니다.
   *
   * @param array $filters 검색 조건
   *
   * @return int
   */
  public function countMemberList(array $filters): int
  {
    [$whereSql, $params] = $this->buildMemberWhere($filters);
    $row = $this->db->fetchRow('SELECT COUNT(*) AS total FROM members' . $whereSql, $params);

    return (int) ($row['total'] ?? 0);
  }

  /**
   * 허용된 검색 조건을 SQL WHERE 절로 변환합니다.
   *
   * @param array $filters 정규화된 검색 조건
   *
   * @return array{0:string,1:array}
   */
  private function buildMemberWhere(array $filters): array
  {
    $where = [];
    $params = [];

    if ($filters['role'] !== '') {
      $where[] = 'role = ?';
      $params[] = $filters['role'];
    }

    if ($filters['approval_status'] !== '') {
      $where[] = 'approval_status = ?';
      $params[] = $filters['approval_status'];
    }

    if ($filters['status'] !== '') {
      $where[] = 'status = ?';
      $params[] = $filters['status'];
    }

    if ($filters['keyword'] !== '') {
      $keyword = '%' . $filters['keyword'] . '%';
      $keywordColumns = [
        'company_name' => 'company_name',
        'user_id' => 'user_id',
        'name' => 'name',
        'mobile' => 'mobile',
      ];

      if ($filters['keyword_type'] !== '' && isset($keywordColumns[$filters['keyword_type']])) {
        $where[] = $keywordColumns[$filters['keyword_type']] . ' LIKE ?';
        $params[] = $keyword;
      } else {
        $where[] = '(company_name LIKE ? OR user_id LIKE ? OR name LIKE ? OR mobile LIKE ?)';
        array_push($params, $keyword, $keyword, $keyword, $keyword);
      }
    }

    $dateColumns = [
      'created_at' => 'created_at',
      'last_login_at' => 'last_login_at',
    ];

    if ($filters['date_type'] !== '' && isset($dateColumns[$filters['date_type']])) {
      if ($filters['date_start'] !== '') {
        $where[] = $dateColumns[$filters['date_type']] . ' >= ?';
        $params[] = $filters['date_start'] . ' 00:00:00';
      }

      if ($filters['date_end'] !== '') {
        $where[] = $dateColumns[$filters['date_type']] . ' <= ?';
        $params[] = $filters['date_end'] . ' 23:59:59';
      }
    }

    $amountColumns = [
      'deposit' => 'deposit',
      'mileage' => 'mileage',
    ];

    if ($filters['amount_type'] !== '' && isset($amountColumns[$filters['amount_type']])) {
      if ($filters['amount_min'] !== '') {
        $where[] = $amountColumns[$filters['amount_type']] . ' >= ?';
        $params[] = (int) $filters['amount_min'];
      }

      if ($filters['amount_max'] !== '') {
        $where[] = $amountColumns[$filters['amount_type']] . ' <= ?';
        $params[] = (int) $filters['amount_max'];
      }
    }

    return [count($where) > 0 ? ' WHERE ' . implode(' AND ', $where) : '', $params];
  }

  /**
   * 회원 목록 다운로드 데이터를 한 행씩 조회합니다.
   *
   * @param array $filters 검색 조건
   *
   * @return Generator<array>
   */
  public function getMemberDownloadGenerator(array $filters): Generator
  {
    [$whereSql, $params] = $this->buildMemberWhere($filters);

    $sql = 'SELECT id, role, vendor_code, company_name, user_id, name, email, mobile, company_phone,
        business_number, deposit, mileage, approval_status, status, created_at, last_login_at
      FROM members'
      . $whereSql
      . ' ORDER BY id DESC';

    return $this->db->fetchGenerator($sql, $params);
  }

  /**
   * 회원 상세 정보를 조회합니다.
   *
   * @param int $id 회원 ID
   *
   * @return array|null
   */
  public function getMemberById(int $id): ?array
  {
    return $this->db->fetchRow(
      'SELECT id, user_id, vendor_code, password, role, name, email, company_name, business_number,
        business_type, business_item, business_license_file, zipcode, address, address_detail,
        bank_name, account_number, account_holder, bank_book_file, is_email_agreed,
        is_sms_agreed, approval_status, deposit, mileage, level_id, mobile, company_phone,
        fax, status, login_fail_count, locked_until, last_login_at, created_at, updated_at
      FROM members
      WHERE id = ?
      LIMIT 1',
      [$id]
    );
  }

  /**
   * 본인을 제외한 이메일 중복 여부를 확인합니다.
   *
   * @param string $email 이메일
   * @param int $memberId 제외할 회원 ID
   *
   * @return bool
   */
  public function isEmailDuplicatedExceptId(string $email, int $memberId): bool
  {
    $row = $this->db->fetchRow(
      'SELECT id FROM members WHERE email = ? AND id <> ? LIMIT 1',
      [$email, $memberId]
    );

    return $row !== null;
  }


  /**
   * 회원 상세 수정 데이터를 저장합니다.
   *
   * @param int $id 회원 ID
   * @param array $data 수정 데이터
   *
   * @return bool
   */
  public function updateMemberDetail(int $id, array $data): bool
  {
    $allowedColumns = [
      'password',
      'role',
      'name',
      'email',
      'company_name',
      'business_number',
      'business_type',
      'business_item',
      'business_license_file',
      'zipcode',
      'address',
      'address_detail',
      'bank_name',
      'account_number',
      'account_holder',
      'bank_book_file',
      'is_email_agreed',
      'is_sms_agreed',
      'approval_status',
      'mobile',
      'company_phone',
      'fax',
      'status',
    ];

    $sets = [];
    $params = [];
    foreach ($allowedColumns as $column) {
      if (!array_key_exists($column, $data)) {
        continue;
      }

      $sets[] = $column . ' = ?';
      $params[] = $data[$column];
    }

    if (count($sets) === 0) {
      return true;
    }

    $sets[] = 'updated_at = NOW()';
    $params[] = $id;

    return $this->db->execute(
      'UPDATE members SET ' . implode(', ', $sets) . ' WHERE id = ?',
      $params
    );
  }
  /**
   * 회원 관리자 메모 목록을 최신순으로 조회합니다.
   *
   * @param int $memberId 조회할 회원 ID
   * @param int $limit 조회 개수
   *
   * @return array
   */
  public function getAdminMemosByMemberId(int $memberId, int $limit = 50): array
  {
    return $this->db->fetchAll(
      'SELECT id, member_id, admin_name, memo_content, created_at
        FROM member_admin_memos
        WHERE member_id = ?
        ORDER BY id DESC
        LIMIT ?',
      [$memberId, $limit]
    );
  }

  /**
   * 관리자 메모 한 건을 조회합니다.
   *
   * @param int $memoId 조회할 메모 ID
   *
   * @return array|null
   */
  public function getAdminMemoById(int $memoId): ?array
  {
    return $this->db->fetchRow(
      'SELECT id, member_id, admin_name, memo_content, created_at
        FROM member_admin_memos
        WHERE id = ?
        LIMIT 1',
      [$memoId]
    );
  }

  /**
   * 관리자 메모를 등록합니다.
   *
   * @param int $memberId 대상 회원 ID
   * @param string $adminName 작성 관리자명
   * @param string $memoContent 메모 내용
   *
   * @return int
   */
  public function insertAdminMemo(int $memberId, string $adminName, string $memoContent): int
  {
    $this->db->execute(
      'INSERT INTO member_admin_memos (member_id, admin_name, memo_content)
        VALUES (?, ?, ?)',
      [$memberId, $adminName, $memoContent]
    );

    return (int) $this->db->lastInsertId();
  }

  /**
   * 관리자 메모를 삭제합니다.
   *
   * @param int $memoId 삭제할 메모 ID
   * @param int $memberId 대상 회원 ID
   *
   * @return bool
   */
  public function deleteAdminMemo(int $memoId, int $memberId): bool
  {
    return $this->db->execute(
      'DELETE FROM member_admin_memos WHERE id = ? AND member_id = ?',
      [$memoId, $memberId]
    );
  }

  /**
   * 아이디 중복 여부를 확인합니다.
   *
   * @param string $userId 로그인 아이디
   *
   * @return bool
   */
  public function isUserIdDuplicated(string $userId): bool
  {
    $row = $this->db->fetchRow(
      'SELECT id FROM members WHERE user_id = ? LIMIT 1',
      [$userId]
    );

    return $row !== null;
  }

  /**
   * 이메일 중복 여부를 확인합니다.
   *
   * @param string $email 이메일
   *
   * @return bool
   */
  public function isEmailDuplicated(string $email): bool
  {
    $row = $this->db->fetchRow(
      'SELECT id FROM members WHERE email = ? LIMIT 1',
      [$email]
    );

    return $row !== null;
  }


  /**
   * 전체 회원 자산 이력 목록을 조회합니다.
   *
   * @param array $filters 검색 조건
   * @param int $limit 페이지당 표시 개수
   * @param int $offset 조회 시작 위치
   *
   * @return array
   */
  public function getMemberAssetHistoryList(array $filters, int $limit, int $offset): array
  {
    [$whereSql, $params] = $this->buildMemberAssetHistoryWhere($filters);

    $sql = 'SELECT h.id, h.member_id, h.reason, h.order_no, h.change_amount, h.balance_after, h.actor_name, h.created_at,
        m.role, m.company_name, m.user_id, m.name
      FROM member_assets_history h
      INNER JOIN members m ON m.id = h.member_id'
      . $whereSql
      . ' ORDER BY h.id DESC LIMIT ? OFFSET ?';

    $params[] = $limit;
    $params[] = $offset;

    return $this->db->fetchAll($sql, $params);
  }

  /**
   * 전체 회원 자산 이력 개수를 조회합니다.
   *
   * @param array $filters 검색 조건
   *
   * @return int
   */
  public function countMemberAssetHistoryList(array $filters): int
  {
    [$whereSql, $params] = $this->buildMemberAssetHistoryWhere($filters);
    $row = $this->db->fetchRow(
      'SELECT COUNT(*) AS total
        FROM member_assets_history h
        INNER JOIN members m ON m.id = h.member_id'
        . $whereSql,
      $params
    );

    return (int) ($row['total'] ?? 0);
  }
  /**
   * 전체 회원 자산 이력 다운로드 데이터를 최신순으로 조회합니다.
   *
   * @param array $filters 검색 조건
   *
   * @return Generator<array>
   */
  public function getMemberAssetHistoryDownloadListGenerator(array $filters): Generator
  {
    [$whereSql, $params] = $this->buildMemberAssetHistoryWhere($filters);

    $sql = 'SELECT h.id, h.member_id, h.reason, h.order_no, h.change_amount, h.balance_after, h.actor_name, h.created_at,
        m.role, m.company_name, m.user_id, m.name
      FROM member_assets_history h
      INNER JOIN members m ON m.id = h.member_id'
      . $whereSql
      . ' ORDER BY h.id DESC';

    return $this->db->fetchGenerator($sql, $params);
  }

  /**
   * 전체 회원 자산 이력 검색 조건을 SQL WHERE 절로 변환합니다.
   *
   * @param array $filters 정규화된 검색 조건
   *
   * @return array{0:string,1:array}
   */
  private function buildMemberAssetHistoryWhere(array $filters): array
  {
    $where = ['h.asset_type = ?'];
    $params = [(string) ($filters['asset_type'] ?? '')];

    if (($filters['role'] ?? '') !== '') {
      $where[] = 'm.role = ?';
      $params[] = (string) $filters['role'];
    }

    if (($filters['change_type'] ?? '') === 'PLUS') {
      $where[] = 'h.change_amount > 0';
    }

    if (($filters['change_type'] ?? '') === 'MINUS') {
      $where[] = 'h.change_amount < 0';
    }

    if (($filters['keyword'] ?? '') !== '') {
      $keyword = '%' . (string) $filters['keyword'] . '%';
      $keywordColumns = [
        'company_name' => 'm.company_name',
        'user_id' => 'm.user_id',
        'name' => 'm.name',
        'reason' => 'h.reason',
        'order_no' => 'h.order_no',
        'actor_name' => 'h.actor_name',
      ];

      if (($filters['keyword_type'] ?? '') !== '' && isset($keywordColumns[$filters['keyword_type']])) {
        $where[] = $keywordColumns[$filters['keyword_type']] . ' LIKE ?';
        $params[] = $keyword;
      } else {
        $where[] = '(m.company_name LIKE ? OR m.user_id LIKE ? OR m.name LIKE ? OR h.reason LIKE ? OR h.order_no LIKE ? OR h.actor_name LIKE ?)';
        array_push($params, $keyword, $keyword, $keyword, $keyword, $keyword, $keyword);
      }
    }

    if (($filters['date_start'] ?? '') !== '') {
      $where[] = 'h.created_at >= ?';
      $params[] = (string) $filters['date_start'] . ' 00:00:00';
    }

    if (($filters['date_end'] ?? '') !== '') {
      $where[] = 'h.created_at <= ?';
      $params[] = (string) $filters['date_end'] . ' 23:59:59';
    }

    return [' WHERE ' . implode(' AND ', $where), $params];
  }
  /**
   * 회원 자산 이력을 최신순으로 조회합니다.
   *
   * @param int $memberId 회원 ID
   * @param string $assetType 자산 구분(DEPOSIT/POINT)
   * @param int $limit 조회 개수
   * @param int $offset 조회 시작 위치
   *
   * @return array
   */
  public function getMemberAssetHistories(int $memberId, string $assetType, int $limit = 50, int $offset = 0): array
  {
    return $this->db->fetchAll(
      'SELECT id, member_id, asset_type, reason, order_no, change_amount, balance_after, actor_name, created_at
        FROM member_assets_history
        WHERE member_id = ? AND asset_type = ?
        ORDER BY id DESC
        LIMIT ? OFFSET ?',
      [$memberId, $assetType, $limit, $offset]
    );
  }


  /**
   * 회원 자산 이력 다운로드 데이터를 최신순으로 조회합니다.
   *
   * @param int $memberId 회원 ID
   * @param string $assetType 자산 구분(DEPOSIT/POINT)
   *
   * @return Generator<array>
   */
  public function getMemberAssetHistoryDownloadGenerator(int $memberId, string $assetType): Generator
  {
    return $this->db->fetchGenerator(
      'SELECT id, reason, order_no, change_amount, balance_after, actor_name, created_at
        FROM member_assets_history
        WHERE member_id = ? AND asset_type = ?
        ORDER BY id DESC',
      [$memberId, $assetType]
    );
  }

  /**
   * 회원 자산 이력 개수를 조회합니다.
   *
   * @param int $memberId 회원 ID
   * @param string $assetType 자산 구분(DEPOSIT/POINT)
   *
   * @return int
   */
  public function countMemberAssetHistories(int $memberId, string $assetType): int
  {
    $row = $this->db->fetchRow(
      'SELECT COUNT(*) AS total
        FROM member_assets_history
        WHERE member_id = ? AND asset_type = ?',
      [$memberId, $assetType]
    );

    return (int) ($row['total'] ?? 0);
  }

  /**
   * 자산 변경을 위해 회원 잔액 행을 잠금 조회합니다.
   *
   * @param int $memberId 회원 ID
   *
   * @return array|null
   */
  public function getMemberAssetForUpdate(int $memberId): ?array
  {
    return $this->db->fetchRow(
      'SELECT id, role, deposit, mileage
        FROM members
        WHERE id = ?
        LIMIT 1
        FOR UPDATE',
      [$memberId]
    );
  }

  /**
   * 회원 자산 잔액을 저장합니다.
   *
   * @param int $memberId 회원 ID
   * @param string $column 자산 컬럼명(deposit/mileage)
   * @param int $balanceAfter 변경 후 잔액
   *
   * @return bool
   */
  public function updateMemberAssetBalance(int $memberId, string $column, int $balanceAfter): bool
  {
    $allowedColumns = [
      'deposit' => 'deposit',
      'mileage' => 'mileage',
    ];

    if (!isset($allowedColumns[$column])) {
      return false;
    }

    return $this->db->execute(
      'UPDATE members SET ' . $allowedColumns[$column] . ' = ?, updated_at = NOW() WHERE id = ?',
      [$balanceAfter, $memberId]
    );
  }

  /**
   * 회원 자산 변동 이력을 등록합니다.
   *
   * @param array $data 이력 데이터
   *
   * @return int
   */
  public function insertMemberAssetHistory(array $data): int
  {
    $this->db->execute(
      'INSERT INTO member_assets_history
        (member_id, asset_type, reason, order_no, change_amount, balance_after, actor_name)
        VALUES (?, ?, ?, ?, ?, ?, ?)',
      [
        $data['member_id'],
        $data['asset_type'],
        $data['reason'],
        $data['order_no'],
        $data['change_amount'],
        $data['balance_after'],
        $data['actor_name'],
      ]
    );

    return (int) $this->db->lastInsertId();
  }
  /**
   * 회원 고유 벤더코드를 발급합니다.
   *
   * @return string
   */
  public function issueVendorCode(): string
  {
    $this->db->execute('INSERT INTO member_vendor_code_sequences () VALUES ()');
    $sequenceId = (int) $this->db->lastInsertId();

    return 'V' . str_pad((string) $sequenceId, 6, '0', STR_PAD_LEFT) . $this->makeVendorCodeSuffix();
  }

  /**
   * 회원 데이터를 등록합니다.
   *
   * @param array $data 등록 데이터
   *
   * @return int
   */
  public function insertMember(array $data): int
  {
    $columns = [
      'user_id',
      'vendor_code',
      'password',
      'role',
      'name',
      'email',
      'company_name',
      'business_number',
      'business_type',
      'business_item',
      'business_license_file',
      'zipcode',
      'address',
      'address_detail',
      'bank_name',
      'account_number',
      'account_holder',
      'bank_book_file',
      'is_email_agreed',
      'is_sms_agreed',
      'approval_status',
      'deposit',
      'mileage',
      'level_id',
      'mobile',
      'company_phone',
      'fax',
      'status',
      'login_fail_count',
      'locked_until',
      'last_login_at',
    ];

    $placeholders = [];
    $params = [];
    foreach ($columns as $column) {
      $placeholders[] = '?';
      $params[] = $data[$column] ?? null;
    }

    $this->db->execute(
      'INSERT INTO members (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
      $params
    );

    return (int) $this->db->lastInsertId();
  }

  /**
   * 벤더코드 뒤에 붙일 임의 suffix를 생성합니다.
   *
   * @return string
   */
  private function makeVendorCodeSuffix(): string
  {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $suffix = '';
    $maxIndex = strlen($characters) - 1;

    for ($i = 0; $i < 3; $i++) {
      $suffix .= $characters[random_int(0, $maxIndex)];
    }

    return $suffix;
  }
}
