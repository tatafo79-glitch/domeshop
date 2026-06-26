<?php

declare(strict_types=1);

namespace App\Core;

use Generator;
use PDO;

class SecureDb
{
  /**
   * Method __construct
   *
   * @param PDO $pdo [explicit description]
   *
   * @return void
   */
  public function __construct(private readonly PDO $pdo)
  {
  }

  /**
   * Method fetchRow
   *
   * @param string $sql [explicit description]
   * @param array $params [explicit description]
   *
   * @return ?array
   */
  public function fetchRow(string $sql, array $params = []): ?array
  {
    $statement = $this->pdo->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();

    return $row === false ? null : $row;
  }

  /**
   * Method fetchAll
   *
   * @param string $sql [explicit description]
   * @param array $params [explicit description]
   *
   * @return array
   */
  public function fetchAll(string $sql, array $params = []): array
  {
    $statement = $this->pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
  }


  /**
   * 대용량 조회 결과를 한 행씩 반환합니다.
   *
   * @param string $sql 실행할 SQL
   * @param array $params 바인딩 파라미터
   *
   * @return Generator<array>
   */
  public function fetchGenerator(string $sql, array $params = []): Generator
  {
    $previousBuffered = null;

    if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
      try {
        $previousBuffered = $this->pdo->getAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY);
        $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
      } catch (\Throwable) {
        $previousBuffered = null;
      }
    }

    $statement = $this->pdo->prepare($sql);
    $statement->execute($params);

    try {
      while (($row = $statement->fetch()) !== false) {
        yield $row;
      }
    } finally {
      $statement->closeCursor();

      if ($previousBuffered !== null && defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
        $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, $previousBuffered);
      }
    }
  }

  /**
   * Method execute
   *
   * @param string $sql [explicit description]
   * @param array $params [explicit description]
   *
   * @return bool
   */
  public function execute(string $sql, array $params = []): bool
  {
    $statement = $this->pdo->prepare($sql);

    return $statement->execute($params);
  }

  /**
   * Method lastInsertId
   *
   * @return string
   */
  public function lastInsertId(): string
  {
    return $this->pdo->lastInsertId();
  }

  /**
   * Method beginTransaction
   *
   * @return bool
   */
  public function beginTransaction(): bool
  {
    return $this->pdo->beginTransaction();
  }

  /**
   * Method commit
   *
   * @return bool
   */
  public function commit(): bool
  {
    return $this->pdo->commit();
  }

  /**
   * Method rollBack
   *
   * @return bool
   */
  public function rollBack(): bool
  {
    return $this->pdo->rollBack();
  }
}
